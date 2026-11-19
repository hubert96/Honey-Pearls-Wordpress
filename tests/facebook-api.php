<?php
define('ABSPATH', __DIR__);
function add_action(...$args){} function add_filter(...$args){} function wp_salt($scheme){return 'test-only-secret';}
require __DIR__.'/../web/app/plugins/honey-pearls-facebook/includes.php';
require __DIR__.'/../web/app/plugins/honey-pearls-facebook/api.php';
use function HoneyPearls\Facebook\normalize_post;
use function HoneyPearls\Facebook\seal_token;
use function HoneyPearls\Facebook\unseal_token;
function check($pass,$message){if(!$pass)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
$token='test-token-not-a-real-secret';$sealed=seal_token($token);
check($sealed!==$token && unseal_token($sealed)===$token,'Credential encryption round trip');
$bytes=base64_decode($sealed);$bytes[15]=chr(ord($bytes[15])^1);
check(unseal_token(base64_encode($bytes))==='','Reject tampered credentials');
check(unseal_token('invalid')==='','Reject malformed credentials');
$post=normalize_post(['permalink_url'=>'https://www.facebook.com/honeypearls.patisserie/posts/123','created_time'=>'2026-11-19T12:00:00+0000','full_picture'=>'https://scontent.xx.fbcdn.net/photo.jpg','message'=>'Real text','reactions'=>['summary'=>['total_count'=>21]],'comments'=>['summary'=>['total_count'=>3],'data'=>[['is_hidden'=>true,'message'=>'Do not show','from'=>['name'=>'Hidden']],['message'=>'Public comment','from'=>['name'=>'Carol']],['message'=>'Second comment']]],'shares'=>['count'=>5]]);
check($post['reactions']===21 && $post['comment_count']===3 && $post['shares']===5,'Preserve actual engagement totals');
check(count($post['comments'])===2 && $post['comments'][0]['author']==='Carol','Exclude hidden comments');
check($post['comments'][1]['author']==='Facebook user','Do not invent missing author names');
check($post['date']==='19 November 2026','Normalize API date');
check(normalize_post(['permalink_url'=>'https://evil.example/posts/123'])===[],'Reject external permalinks');
$unsafe=normalize_post(['permalink_url'=>'https://www.facebook.com/page/posts/123','full_picture'=>'https://fbcdn.net.evil.example/photo']);
check($unsafe['image']==='','Reject non-Facebook media hosts');
check($unsafe['reactions']===null,'Missing totals stay unknown');
