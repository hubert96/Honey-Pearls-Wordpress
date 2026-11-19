<?php
const ABSPATH = __DIR__;
const ARRAY_A = 'ARRAY_A';
function add_action(...$a) {} function add_shortcode(...$a) {}
function get_option($k,$default=false) { return $GLOBALS['options'][$k] ?? $default; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function esc_attr($s) { return htmlspecialchars((string)$s,ENT_QUOTES); }
function esc_url($s) { return esc_attr($s); }
function plugins_url($s,...$a) { return '/plugin/'.$s; }
require __DIR__.'/../web/app/plugins/honey-pearls-social/modules/reviews/honey-pearls-reviews.php';
function check($value,$message) { if (!$value) throw new Exception($message); echo "PASS $message\n"; }
class Connector {
 function __construct(public string $id,public bool $exists=true) {}
 function get_tablename($s) { return $this->id; }
 function is_table_exists($s) { return $this->exists; }
 function get_option_name($s) { return $this->id; }
 function getPageUrl() { return 'https://example.com/'.$this->id; }
 function getReviewPlainText($r) { return $r->text; }
}
$wpdb = new class {
 function prepare($sql,$table) { if (!str_contains($sql,'hidden = 0')) throw new Exception('Visibility filter missing'); return $table; }
 function get_results($table,...$args) { return $GLOBALS['rows'][$table] ?? []; }
};
$options=['google'=>['id'=>'g','rating_score'=>4.7,'rating_number'=>27], 'tripadvisor'=>['id'=>'t','rating_score'=>5,'rating_number'=>3]];
$row=['user'=>'Customer','text'=>'<b>Original</b> review','date'=>'2026-11-01','rating'=>2];
$rows=['google'=>[$row,$row+['user_photo'=>'']], 'tripadvisor'=>[$row, array_merge($row,['rating'=>9])]];
$trustindex_pm_google=new Connector('google');$trustindex_pm_tripadvisor=new Connector('tripadvisor');
$s=HoneyPearls\Reviews\sources();
check(count($s['tripadvisor']['reviews'])===1,'Reject invalid ratings but keep low ratings');
check($s['google']['count']===27 && count($s['google']['reviews'])===2,'Overall total distinct from stored subset');
check($s['google']['reviews'][0]['text']==='Original review','Keep original text without markup');
$combined=HoneyPearls\Reviews\interleave($s);
check(array_column($combined,'source')===['google','tripadvisor','google'],'Alternate Google and Tripadvisor');
$trustindex_pm_google->exists=false;
check(array_keys(HoneyPearls\Reviews\sources())===['tripadvisor'],'Missing Google table does not hide Tripadvisor');
$options['tripadvisor']=[];
check(HoneyPearls\Reviews\sources()===[],'Unconnected sources are omitted');
check(HoneyPearls\Reviews\interleave([])===[],'Empty sources do not invent reviews');
$bubbles=HoneyPearls\Reviews\stars(4.5,'tripadvisor');
check(substr_count($bubbles,'tripadvisor-f.svg')===4 && substr_count($bubbles,'tripadvisor-h.svg')===1,'Tripadvisor half-bubble rating');
check(!str_contains($bubbles,'★'),'Tripadvisor does not use stars');
$options['tripadvisor']=['id'=>'com.au|Restaurant_Review-g190932-d34459636-Reviews.html'];
check(HoneyPearls\Reviews\sources()['tripadvisor']['url']==='https://www.tripadvisor.com.au/Restaurant_Review-g190932-d34459636-Reviews.html','Regional Tripadvisor source link');

check(HoneyPearls\Reviews\review_photos('invalid', $trustindex_pm_google) === [], 'Malformed photos are omitted');
$photos=HoneyPearls\Reviews\review_photos('["https://example.com/photo.jpg","javascript:alert(1)",null,"http://example.com/no.jpg"]', $trustindex_pm_google);
check(count($photos) === 1 && $photos[0]['thumbnail'] === 'https://example.com/photo.jpg', 'Only valid HTTPS source photos are accepted');
check(count(HoneyPearls\Reviews\review_photos(array_fill(0,5,'https://example.com/photo.jpg'), $trustindex_pm_google)) === 3, 'Photo count is bounded');
