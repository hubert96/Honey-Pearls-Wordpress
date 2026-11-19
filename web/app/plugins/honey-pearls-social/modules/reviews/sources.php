<?php
namespace HoneyPearls\Reviews;
defined('ABSPATH') || exit;

/** Read source connectors; never mutate their widgets or refresh settings. */
function sources(): array {
    global $trustindex_pm_google, $trustindex_pm_tripadvisor, $wpdb;
    $sources = [];
    foreach (['google' => $trustindex_pm_google ?? null, 'tripadvisor' => $trustindex_pm_tripadvisor ?? null] as $id => $connector) {
        if (!$connector || !method_exists($connector, 'get_tablename') || !$connector->is_table_exists('reviews')) continue;
        $details = get_option($connector->get_option_name('page-details'), []);
        if (empty($details['id'])) continue;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE hidden = 0 ORDER BY date DESC, id DESC LIMIT 100', $connector->get_tablename('reviews')), ARRAY_A) ?: [];
        $url = $connector->getPageUrl();
        // The Tripadvisor connector stores regional URLs as "com.au|Restaurant_Review-...".
        if ($id === 'tripadvisor' && str_contains($details['id'], '|')) {
            [$domain, $path] = explode('|', $details['id'], 2);
            if (in_array($domain, ['com', 'co.uk', 'com.au'], true)) $url = 'https://www.tripadvisor.' . $domain . '/' . ltrim($path, '/');
        }
        $source = ['name' => $id === 'google' ? 'Google Maps' : 'Tripadvisor', 'url' => $url, 'rating' => isset($details['rating_score']) ? (float) $details['rating_score'] : null, 'count' => (int) ($details['rating_number'] ?? count($rows)), 'reviews' => []];
        foreach ($rows as $row) {
            if (!is_numeric($row['rating']) || $row['rating'] < 1 || $row['rating'] > 5) continue;
            $timestamp = strtotime($row['date'] ?? '');
            $source['reviews'][] = ['author' => $row['user'], 'avatar' => $row['user_photo'] ?? '', 'rating' => (float) $row['rating'], 'date' => $timestamp ? gmdate('Y-m-d', $timestamp) : '', 'photos' => review_photos($row['photos'] ?? null, $connector), 'title' => '', 'text' => wp_strip_all_tags($connector->getReviewPlainText((object) $row)), 'url' => $url];
        }
        if ($source['reviews']) $sources[$id] = $source;
    }
    return $sources;
}

/** Only real attached photos; missing or malformed source data stays empty. */
function review_photos($raw, $connector): array {
    $urls = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($urls)) return [];
    $photos = [];
    foreach ($urls as $url) {
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') continue;
        $thumbnail = $url;
        if (method_exists($connector, 'getAssetProxy')) {
            $proxy = $connector->getAssetProxy();
            if (method_exists($proxy, 'getSizedGoogleImageUrl')) $thumbnail = $proxy->getSizedGoogleImageUrl($url, 'thumbnail');
        }
        $photos[] = ['url' => $url, 'thumbnail' => $thumbnail];
        if (count($photos) === 3) break;
    }
    return $photos;
}
