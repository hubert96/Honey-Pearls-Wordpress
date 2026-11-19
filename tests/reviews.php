<?php
// Isolated tests of the source boundary. No network, WordPress or credentials needed.
define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
$options = []; $transients = []; $responses = [];
function add_action(...$args) {} function add_shortcode(...$args) {}
function register_activation_hook(...$args) {} function register_deactivation_hook(...$args) {}
function wp_salt($scheme) { return 'test-only-salt'; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value, ...$args) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function get_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient($key, $value, ...$args) { $GLOBALS['transients'][$key] = $value; }
function delete_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_remote_get($url, $args) {
    if (!str_starts_with($url, 'https://api.trustpilot.com/v1/business-units/')) throw new Exception('Unexpected host');
    if ($args['headers']['apikey'] !== 'test-key') throw new Exception('Incorrect credential');
    return array_shift($GLOBALS['responses']);
}
function is_wp_error($value) { return false; }
function wp_remote_retrieve_response_code($r) { return $r['status']; }
function wp_remote_retrieve_body($r) { return json_encode($r['body']); }
function sanitize_text_field($s) { return strip_tags($s); }
function wp_strip_all_tags($s) { return strip_tags($s); }
require __DIR__ . '/../web/app/plugins/honey-pearls-reviews/honey-pearls-reviews.php';
function check($value, $label) { if (!$value) throw new Exception($label); echo "PASS $label\n"; }
$encrypted = HoneyPearls\Reviews\crypt_key('test-key');
check($encrypted !== 'test-key' && HoneyPearls\Reviews\crypt_key($encrypted, true) === 'test-key', 'Encrypted credential round trip');
check(HoneyPearls\Reviews\crypt_key('bad', true) === '', 'Reject invalid ciphertext');
$mixed = HoneyPearls\Reviews\interleave(['google'=>['reviews'=>[['id'=>1],['id'=>2]]], 'trustpilot'=>['reviews'=>[['id'=>3]]]]);
check(array_column($mixed,'id') === [1,3,2], 'Alternate sources without losing reviews');
check(HoneyPearls\Reviews\interleave([]) === [], 'No invented empty-state reviews');
$options['hp_reviews_settings'] = ['unit'=>str_repeat('a',24),'key'=>$encrypted];
$responses = [['status'=>200,'body'=>['displayName'=>'Honey Pearls','name'=>['identifying'=>'honeypearls.co.uk'],'score'=>['trustScore'=>4.2],'numberOfReviews'=>['total'=>31]]],['status'=>200,'body'=>['reviews'=>[['id'=>'r1','stars'=>2,'consumer'=>['displayName'=>'A'],'text'=>'<script>bad</script>Real review','createdAt'=>'2026-11-18T00:00:00Z'],['id'=>'invalid','stars'=>8]]]]];
HoneyPearls\Reviews\sync_trustpilot();
check(count($options['hp_reviews_trustpilot']['reviews']) === 1, 'Reject invalid ratings, retain low ratings');
check($options['hp_reviews_trustpilot']['count'] === 31, 'Keep platform total separate from sample size');
check(!str_contains($options['hp_reviews_trustpilot']['reviews'][0]['text'],'<script>'), 'Strip review markup');
$before = $options['hp_reviews_trustpilot'];
$responses = [['status'=>401,'body'=>[]]];
HoneyPearls\Reviews\sync_trustpilot();
check($before === $options['hp_reviews_trustpilot'] && isset($options['hp_reviews_error']), 'Failed refresh preserves last successful data and records error');
check(!get_transient('hp_reviews_sync_lock'), 'Always release sync lock');
$options['hp_reviews_trustpilot']['fetched_at'] = time() - 8*DAY_IN_SECONDS;
check(HoneyPearls\Reviews\sources() === [], 'Expired source data is not displayed');
