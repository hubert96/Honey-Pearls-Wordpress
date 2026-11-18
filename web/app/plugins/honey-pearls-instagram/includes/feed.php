<?php
namespace HoneyPearls\Instagram;
defined('ABSPATH') || exit;

/** Secrets are encrypted at rest; never sent to the frontend or included in status. */
function seal(string $value): string
{
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($value, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) { throw new \RuntimeException('Could not encrypt Instagram credentials.'); }
    return base64_encode($iv . $tag . $cipher);
}

function unseal(string $value): string
{
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 29) { return ''; }
    return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
}

function secret(): string
{
    return unseal((string) get_option('hp_ig_app_secret', ''));
}

function response($response): array
{
    if (is_wp_error($response)) { throw new \RuntimeException('Instagram could not be reached. The previous feed has been kept.'); }
    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if ($status !== 200 || !is_array($body) || isset($body['error'])) {
        $code = (int) ($body['error']['code'] ?? 0);
        throw new \RuntimeException($code === 190 ? 'Instagram authorization expired or was revoked. Reconnect your account.' : 'Instagram request failed (HTTP ' . (int) $status . ', code ' . $code . '). The previous feed has been kept.');
    }
    return $body;
}

function api(string $path, string $token, array $params = [], ?string $provider = null): array
{
    $provider ??= get_option('hp_ig_auth', [])['provider'] ?? 'instagram';
    $base = $provider === 'unified' ? 'https://graph.facebook.com/v27.0/' : 'https://graph.instagram.com/v25.0/';
    $url = add_query_arg($params, $base . ltrim($path, '/'));
    return response(wp_remote_get($url, ['timeout' => 20, 'redirection' => 0, 'headers' => ['Authorization' => 'Bearer ' . $token]]));
}

function instagram_link(string $url): string
{
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    return in_array($host, ['instagram.com', 'www.instagram.com'], true) && wp_parse_url($url, PHP_URL_SCHEME) === 'https' ? esc_url_raw($url) : '';
}

function image_host_allowed(string $url): bool
{
    if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https') { return false; }
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    foreach (['cdninstagram.com', 'fbcdn.net', 'cdn.trustindex.io'] as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) { return true; }
    }
    return false;
}

/** Download only images, into a dedicated persistent folder, never executable files. */
function local_image(string $url, string $id): string
{
    if (!image_host_allowed($url)) { return ''; }
    $uploads = wp_upload_dir();
    if ($uploads['error']) { return ''; }
    $folder = $uploads['basedir'] . '/honey-pearls-instagram';
    if (!wp_mkdir_p($folder)) { return ''; }
    $key = hash('sha256', $id);
    foreach (['jpg', 'png', 'webp'] as $ext) {
        if (is_file($folder . '/' . $key . '.' . $ext)) {
            return $uploads['baseurl'] . '/honey-pearls-instagram/' . $key . '.' . $ext;
        }
    }
    $temp = wp_tempnam('hp-ig');
    if (!$temp) { return ''; }
    try {
        $r = wp_safe_remote_get($url, ['timeout' => 12, 'redirection' => 0, 'stream' => true, 'filename' => $temp, 'limit_response_size' => 8 * 1024 * 1024]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200 || filesize($temp) >= 8 * 1024 * 1024) { return ''; }
        $mime = wp_get_image_mime($temp);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? '';
        if (!$ext || !rename($temp, $folder . '/' . $key . '.' . $ext)) { return ''; }
        return $uploads['baseurl'] . '/honey-pearls-instagram/' . $key . '.' . $ext;
    } finally {
        if (is_file($temp)) { wp_delete_file($temp); }
    }
}

function normalize(array $item): array
{
    $stamp = strtotime((string) ($item['timestamp'] ?? ''));
    return [
        'id' => sanitize_text_field((string) ($item['id'] ?? '')),
        'caption' => sanitize_textarea_field((string) ($item['caption'] ?? '')),
        'type' => in_array($item['media_type'] ?? '', ['IMAGE', 'VIDEO', 'CAROUSEL_ALBUM'], true) ? $item['media_type'] : 'IMAGE',
        'permalink' => instagram_link((string) ($item['permalink'] ?? '')),
        'timestamp' => $stamp ? gmdate('c', $stamp) : '',
        'like_count' => engagement_count($item['like_count'] ?? null),
        'comments_count' => engagement_count($item['comments_count'] ?? null),
        'remote_image' => ($item['media_type'] ?? '') === 'VIDEO' ? ($item['thumbnail_url'] ?? '') : ($item['media_url'] ?? ''),
    ];
}

function engagement_count($value): ?int
{
    return is_numeric($value) && $value >= 0 ? (int) $value : null;
}

function normalize_comments(array $items): array
{
    $comments = [];
    foreach ($items as $item) {
        if (!is_array($item) || !empty($item['hidden']) || empty($item['id']) || !isset($item['text'])) { continue; }
        $comments[] = [
            'id' => sanitize_text_field((string) $item['id']),
            'username' => sanitize_text_field((string) ($item['username'] ?? $item['from']['username'] ?? '')),
            'text' => sanitize_textarea_field((string) $item['text']),
        ];
        if (count($comments) === 5) { break; }
    }
    return $comments;
}

/** Read a bounded preview, oldest checked first so larger feeds do not starve. */
function sync_comments(array &$posts, string $token): bool
{
    $order = array_keys($posts);
    usort($order, fn ($a, $b) => ($posts[$a]['comments_checked'] ?? 0) <=> ($posts[$b]['comments_checked'] ?? 0));
    $started = microtime(true);
    foreach (array_slice($order, 0, 12) as $index) {
        if (microtime(true) - $started > 35) { break; }
        try {
            $r = api(rawurlencode($posts[$index]['id']) . '/comments', $token, ['fields' => 'id,text,username,hidden', 'limit' => 5]);
            if (!isset($r['data']) || !is_array($r['data'])) { throw new \RuntimeException('Incomplete comments'); }
            $posts[$index]['comments'] = normalize_comments($r['data']);
            $posts[$index]['comments_checked'] = time();
        } catch (\Throwable $e) {
            // Permission failures must not discard images or prevent metadata updates.
            return false;
        }
    }
    return true;
}

function set_status(string $message): void
{
    update_option('hp_ig_status', ['message' => $message, 'attempt' => time()], false);
}

function sync(): void
{
    $auth = (array) get_option('hp_ig_auth', []);
    $token = unseal($auth['token'] ?? '');
    if (!$token) { return; }
    // Atomic option lock prevents cron and the admin refresh from overlapping.
    $lock = (int) get_option('hp_ig_sync_lock', 0);
    if ($lock && $lock < time() - 900) { delete_option('hp_ig_sync_lock'); }
    if (!add_option('hp_ig_sync_lock', time(), '', false)) { return; }
    try {
        if (!empty($auth['expires']) && $auth['expires'] <= time()) { throw new \RuntimeException('Instagram authorization expired. Reconnect your account.'); }
        if (($auth['provider'] ?? 'instagram') !== 'unified' && time() - ($auth['refreshed'] ?? time()) > 7 * DAY_IN_SECONDS) {
            $r = response(wp_remote_get(add_query_arg(['grant_type' => 'ig_refresh_token', 'access_token' => $token], 'https://graph.instagram.com/refresh_access_token'), ['timeout' => 20, 'redirection' => 0]));
            if (empty($r['access_token']) || empty($r['expires_in'])) { throw new \RuntimeException('Instagram returned an incomplete token refresh. Reconnect the account.'); }
            $token = $r['access_token'];
            $auth = array_merge($auth, ['token' => seal($token), 'refreshed' => time(), 'expires' => time() + (int) $r['expires_in']]);
            update_option('hp_ig_auth', $auth, false);
        }
        $account = ($auth['provider'] ?? 'instagram') === 'unified' ? rawurlencode($auth['user_id']) : 'me';
        $r = api($account . '/media', $token, ['fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count', 'limit' => 50]);
        if (!isset($r['data']) || !is_array($r['data'])) { throw new \RuntimeException('Instagram returned an incomplete feed. The previous feed has been kept.'); }
        $old = array_column((array) get_option('hp_ig_posts', []), null, 'id');
        $posts = [];
        $failed = 0;
        // Bound each run; later runs finish caching the remainder without blocking page rendering.
        $started = microtime(true);
        foreach ($r['data'] as $item) {
            $p = normalize($item);
            if (!$p['id'] || !$p['permalink']) { continue; }
            $p['image'] = $old[$p['id']]['image'] ?? '';
            $p['comments'] = $old[$p['id']]['comments'] ?? [];
            $p['comments_checked'] = $old[$p['id']]['comments_checked'] ?? 0;
            if ($p['comments_count'] === 0) { $p['comments'] = []; }
            if (!$p['image'] && microtime(true) - $started < 35) {
                $p['image'] = local_image((string) $p['remote_image'], $p['id']);
            }
            unset($p['remote_image']);
            if ($p['image']) { $posts[] = $p; } else { $failed++; }
        }
        if ($r['data'] && !$posts) { throw new \RuntimeException('Could not cache Instagram images. The previous feed has been kept.'); }
        usort($posts, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
        $comments_ok = empty($auth['comments_requested']) || sync_comments($posts, $token);
        update_option('hp_ig_posts', $posts, false);
        update_option('hp_ig_last_sync', time(), false);
        set_status(!$comments_ok ? 'Feed updated, but comments could not be refreshed. Check instagram_business_manage_comments in your Meta app and reconnect Instagram.' : ($failed ? 'Feed updated. Some images are pending; the next sync will retry them.' : 'Feed updated successfully.'));
    } catch (\Throwable $e) {
        set_status($e->getMessage());
    } finally {
        delete_option('hp_ig_sync_lock');
    }
}

/** Import only the site's content, not third-party scripts, CSS or credentials. */
function import_legacy(): void
{
    $data = json_decode((string) get_option('trustindex-feed-instagram-feed-data', ''), true);
    if (empty($data['posts']) || !is_array($data['posts'])) { set_status('No saved Instagram posts were found.'); return; }
    $posts = array_column((array) get_option('hp_ig_posts', []), null, 'id');
    $added = 0;
    $started = microtime(true);
    foreach ($data['posts'] as $old) {
        if (microtime(true) - $started > 35) { break; }
        $id = sanitize_text_field((string) ($old['id'] ?? ''));
        if (!$id || isset($posts[$id])) { continue; }
        $media = $old['media_content'][0] ?? [];
        $path = $media['image_urls']['large'] ?? $media['image_urls']['medium'] ?? $media['image_urls']['small'] ?? '';
        $url = $path ? 'https://cdn.trustindex.io/' . ltrim($path, '/') : ($media['image_url'] ?? '');
        $p = normalize(['id' => $id, 'caption' => $old['text'] ?? '', 'permalink' => $old['url'] ?? '', 'timestamp' => $old['created_at'] ?? '', 'media_type' => 'IMAGE']);
        if (!$p['permalink']) { continue; }
        $p['image'] = local_image($url, $id);
        unset($p['remote_image']);
        if ($p['image']) { $posts[$id] = $p; $added++; }
    }
    $posts = array_values($posts);
    usort($posts, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
    update_option('hp_ig_posts', $posts, false);
    $s = settings();
    $s['hidden'] = array_values(array_unique(array_merge($s['hidden'], array_map('strval', $data['style']['settings']['hidden_posts'] ?? []))));
    update_option('hp_ig_settings', $s, false);
    set_status('Imported ' . $added . ' locally cached posts. This does not connect Instagram. Repeat the import if more posts remain.');
}
