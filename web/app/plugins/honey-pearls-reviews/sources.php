<?php
namespace HoneyPearls\Reviews;
defined('ABSPATH') || exit;

function sources(): array {
    global $trustindex_pm_google, $wpdb;
    $sources = [];
    // Read the existing connector's data; leave its widget, settings and refresh jobs untouched.
    if (isset($trustindex_pm_google) && method_exists($trustindex_pm_google, 'get_tablename') && $trustindex_pm_google->is_table_exists('reviews')) {
        $details = get_option($trustindex_pm_google->get_option_name('page-details'), []);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE hidden = 0 ORDER BY date DESC, id DESC LIMIT 100', $trustindex_pm_google->get_tablename('reviews')), ARRAY_A) ?: [];
        $url = 'https://www.google.com/maps/search/?api=1&query=Honey+Pearls&query_place_id=' . rawurlencode($details['id'] ?? 'ChIJt1kjxKVtfkgRgkueXPCsPeI');
        $sources['google'] = ['name' => 'Google Maps', 'url' => $url, 'rating' => isset($details['rating_score']) ? (float) $details['rating_score'] : null, 'count' => (int) ($details['rating_number'] ?? count($rows)), 'reviews' => []];
        foreach ($rows as $row) {
            if (!is_numeric($row['rating']) || $row['rating'] < 1 || $row['rating'] > 5) continue;
            $sources['google']['reviews'][] = ['author' => $row['user'], 'avatar' => $row['user_photo'] ?? '', 'rating' => (float) $row['rating'], 'date' => $row['date'], 'title' => '', 'text' => wp_strip_all_tags($trustindex_pm_google->getReviewPlainText((object) $row)), 'url' => $url];
        }
    }
    $tp = get_option('hp_reviews_trustpilot', []);
    // Drop stale content, including reviews that might have been deleted at the source.
    if (!empty($tp['reviews']) && ($tp['fetched_at'] ?? 0) > time() - 7 * DAY_IN_SECONDS) $sources['trustpilot'] = $tp;
    return $sources;
}

function crypt_key(string $value, bool $decrypt = false): string {
    $key = hash('sha256', wp_salt('auth'), true);
    if ($decrypt) {
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 29) return '';
        return openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16)) ?: '';
    }
    $iv = random_bytes(12); $tag = '';
    $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $encrypted === false ? '' : base64_encode($iv . $tag . $encrypted);
}

function sync_trustpilot(): void {
    $options = get_option('hp_reviews_settings', []);
    if (empty($options['key']) || empty($options['unit'])) return;
    if (get_transient('hp_reviews_sync_lock')) return;
    set_transient('hp_reviews_sync_lock', 1, 90);
    try {
        $base = 'https://api.trustpilot.com/v1/business-units/' . rawurlencode($options['unit']);
        $data = [];
        foreach (['info' => '', 'reviews' => '/all-reviews'] as $kind => $path) {
            $response = wp_remote_get($base . $path, ['timeout' => 15, 'redirection' => 0, 'headers' => ['apikey' => crypt_key($options['key'], true)]]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) throw new \RuntimeException('Trustpilot could not be refreshed. Check the API key, business unit and API entitlement.');
            $data[$kind] = json_decode(wp_remote_retrieve_body($response), true);
        }
        if (!is_array($data['reviews']['reviews'] ?? null) || empty($data['info']['displayName'])) throw new \RuntimeException('Unexpected Trustpilot response.');
        $source = ['name' => 'Trustpilot', 'url' => 'https://www.trustpilot.com/review/' . rawurlencode($data['info']['name']['identifying'] ?? ''), 'rating' => isset($data['info']['score']['trustScore']) ? (float) $data['info']['score']['trustScore'] : null, 'count' => (int) ($data['info']['numberOfReviews']['total'] ?? 0), 'fetched_at' => time(), 'reviews' => []];
        foreach (array_slice($data['reviews']['reviews'], 0, 100) as $r) {
            if (empty($r['id']) || !is_numeric($r['stars'] ?? null) || $r['stars'] < 1 || $r['stars'] > 5) continue;
            $source['reviews'][] = ['author' => sanitize_text_field($r['consumer']['displayName'] ?? ''), 'avatar' => '', 'rating' => (float) $r['stars'], 'date' => substr($r['createdAt'] ?? '', 0, 10), 'title' => sanitize_text_field($r['title'] ?? ''), 'text' => wp_strip_all_tags($r['text'] ?? ''), 'url' => 'https://www.trustpilot.com/reviews/' . rawurlencode($r['id'])];
        }
        usort($source['reviews'], fn($a, $b) => strcmp($b['date'], $a['date']));
        update_option('hp_reviews_trustpilot', $source, false);
        delete_option('hp_reviews_error');
    } catch (\Throwable $e) { update_option('hp_reviews_error', $e->getMessage(), false); }
    finally { delete_transient('hp_reviews_sync_lock'); }
}
