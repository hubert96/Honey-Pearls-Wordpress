<?php
/** Standalone regression tests: php tests/instagram.php. No live API or database. */
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$options = [];
$responses = [];
function register_activation_hook(...$args) {}
function register_deactivation_hook(...$args) {}
function add_action(...$args) {}
function wp_salt($scheme) { return 'test-only-salt'; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; return true; }
function add_option($key, $value, ...$args) { if (isset($GLOBALS['options'][$key])) return false; return update_option($key, $value); }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function sanitize_text_field($value) { return strip_tags($value); }
function sanitize_textarea_field($value) { return strip_tags($value); }
function esc_url_raw($value) { return filter_var($value, FILTER_SANITIZE_URL); }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function shortcode_atts($defaults, $values) { return array_merge($defaults, $values); }
function wp_unique_id($prefix) { static $i = 0; return $prefix . ++$i; }
function wp_trim_words($value, $count) { return implode(' ', array_slice(explode(' ', $value), 0, $count)); }
function wp_date($format, $timestamp) { return date($format, $timestamp); }
function plugins_url($path, $file) { return 'assets/' . basename($path); }
function current_user_can($cap) { return false; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function is_wp_error($value) { return $value instanceof Exception; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests'][] = ['url' => $url, 'args' => $args];
    if (!$GLOBALS['responses']) throw new RuntimeException('Unexpected HTTP request in test');
    return array_shift($GLOBALS['responses']);
}
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return json_encode($r['body']); }
require __DIR__ . '/../web/app/plugins/honey-pearls-instagram/honey-pearls-instagram.php';
use function HoneyPearls\Instagram\{seal, unseal, normalize, image_host_allowed, instagram_link, sync, render};
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); echo "PASS $message\n"; }

$secret = seal('private-token');
check($secret !== 'private-token' && unseal($secret) === 'private-token', 'Credentials encrypted and recovered');
$raw = base64_decode($secret); $raw[28] = chr(ord($raw[28]) ^ 1);
check(unseal(base64_encode($raw)) === '', 'Tampered credentials rejected');
check(image_host_allowed('https://scontent.cdninstagram.com/a.jpg'), 'Instagram CDN accepted');
foreach (['http://scontent.cdninstagram.com/a', 'https://cdninstagram.com.attacker.test/a', 'https://127.0.0.1/a', 'file:///etc/passwd'] as $url) {
    check(!image_host_allowed($url), 'Unsafe image host rejected: ' . $url);
}
check(instagram_link('https://instagram.com.attacker.test/p/a') === '', 'External post links rejected');
$post = normalize(['id' => '123', 'caption' => '<script>x</script> Cake', 'media_type' => 'VIDEO', 'media_url' => 'movie.mp4', 'thumbnail_url' => 'thumb.jpg', 'permalink' => 'https://www.instagram.com/p/abc/', 'timestamp' => '2026-11-18T10:00:00Z']);
check($post['remote_image'] === 'thumb.jpg' && !str_contains($post['caption'], '<script>'), 'Video thumbnail selected and captions sanitized');
unset($post['remote_image']); $post['image'] = 'https://honeypearls.co.uk/app/uploads/test.jpg';
$options['hp_ig_posts'] = [$post];
$options['hp_ig_auth'] = ['token' => seal('private-token'), 'refreshed' => time(), 'expires' => time() + 86400];
$responses[] = ['status' => 400, 'body' => ['error' => ['code' => 190, 'message' => 'sensitive token content']]];
sync();
check($options['hp_ig_posts'] === [$post], 'Expired token preserves existing feed');
check(!str_contains($options['hp_ig_status']['message'], 'sensitive'), 'Raw API errors never disclosed');
check(!isset($options['hp_ig_sync_lock']), 'Lock released after API failure');
$responses[] = ['status' => 200, 'body' => ['data' => [['id' => '123', 'caption' => 'New caption', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/a.jpg', 'permalink' => 'https://www.instagram.com/p/abc/', 'timestamp' => '2026-11-18T12:00:00Z']]]];
sync();
check($options['hp_ig_posts'][0]['caption'] === 'New caption' && $options['hp_ig_posts'][0]['image'] === $post['image'], 'Successful sync updates metadata and reuses local image');
check(!str_contains($GLOBALS['requests'][0]['url'], 'private-token'), 'Media API token only sent in authorization header');
$options['hp_ig_sync_lock'] = time(); $before = count($GLOBALS['requests']); sync();
check(count($GLOBALS['requests']) === $before, 'Overlapping sync does not call API');
unset($options['hp_ig_sync_lock']);
$options['hp_ig_posts'][0]['caption'] = '"><script>alert(1)</script>';
$html = render();
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Rendered text and attributes escaped');
check(!str_contains($html, 'private-token'), 'Frontend does not contain credentials');
$options['hp_ig_settings'] = ['hidden' => ['123']];
check(!str_contains(render(), 'data-hp-image'), 'Hidden posts excluded from frontend');
$options['hp_ig_settings'] = [];
$options['hp_ig_auth']['refreshed'] = time() - 8 * DAY_IN_SECONDS;
$responses[] = ['status' => 200, 'body' => ['access_token' => 'refreshed-token', 'expires_in' => 5184000]];
$responses[] = ['status' => 200, 'body' => ['data' => []]];
sync();
check(unseal($options['hp_ig_auth']['token']) === 'refreshed-token', 'Long-lived token refreshed automatically');
check($options['hp_ig_posts'] === [], 'Authoritative empty feed removes obsolete posts');
echo "Instagram regression tests passed.\n";

check(HoneyPearls\Instagram\engagement_count(null) === null && HoneyPearls\Instagram\engagement_count(0) === 0, 'Unknown engagement differs from zero');
$clean = HoneyPearls\Instagram\normalize_comments([
    ['id'=>'1','hidden'=>true,'text'=>'hidden'],
    ['id'=>'2','username'=>'<b>guest</b>','text'=>'<script>x</script>Nice'],
]);
check(count($clean) === 1 && $clean[0]['username'] === 'guest' && $clean[0]['text'] === 'xNice', 'Hidden comments excluded and comment text sanitized');
$post['like_count'] = 0; $post['comments_count'] = 1;
$post['comments'] = [['username'=>'guest','text'=>'<script>unsafe</script>']]; $post['comments_checked'] = time();
$options['hp_ig_posts'] = [$post];
check(str_contains(render(), '0 likes') && str_contains(render(), '&lt;script&gt;unsafe') && !str_contains(render(), '<script>unsafe'), 'Zero likes and escaped comment preview rendered');
$options['hp_ig_posts'][0]['comments_checked'] = time() - 2 * DAY_IN_SECONDS;
check(!str_contains(render(), 'unsafe'), 'Stale comment previews expire');
$options['hp_ig_auth']['comments_requested'] = true;
$media = ['id'=>'123','caption'=>'Updated','permalink'=>$post['permalink'],'like_count'=>12,'comments_count'=>1];
$responses[] = ['status'=>200,'body'=>['data'=>[$media]]];
$responses[] = ['status'=>403,'body'=>['error'=>['code'=>10,'message'=>'secret']]];
sync();
check($options['hp_ig_posts'][0]['like_count'] === 12 && str_contains($options['hp_ig_status']['message'], 'comments could not'), 'Comment permission failure preserves feed and updates likes');
$responses[] = ['status'=>200,'body'=>['data'=>[$media]]];
$responses[] = ['status'=>200,'body'=>['data'=>[['id'=>'3','username'=>'guest','text'=>'Lovely!','hidden'=>false]]]];
sync();
check($options['hp_ig_posts'][0]['comments'][0]['text'] === 'Lovely!', 'Comment sync replaces old preview');
$responses[] = ['status'=>200,'body'=>['data'=>[$media]]];
$responses[] = ['status'=>200,'body'=>['data'=>[]]];
sync();
check($options['hp_ig_posts'][0]['comments'] === [], 'Authoritative empty comments remove deleted comments');
