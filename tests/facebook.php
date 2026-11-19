<?php
const ABSPATH=__DIR__;
require __DIR__.'/../web/app/plugins/honey-pearls-social/modules/facebook/includes.php';
use function HoneyPearls\Facebook\post_url;
use function HoneyPearls\Facebook\embed_url;
function check($ok,$label){if(!$ok)throw new Exception($label);echo "PASS $label\n";}
check(post_url('https://m.facebook.com/honeypearls.patisserie/posts/123?tracking=abc')==='https://www.facebook.com/honeypearls.patisserie/posts/123','Canonical post permalink strips tracking');
check(post_url('https://www.facebook.com/permalink.php?story_fbid=pfbid123&id=812011511999815&extra=x')==='https://www.facebook.com/permalink.php?story_fbid=pfbid123&id=812011511999815','Preserve story identifiers');
check(post_url('https://www.facebook.com/photo/?fbid=123&set=abc')==='https://www.facebook.com/photo.php?fbid=123','Photo permalink supported');
foreach(['https://facebook.com.evil.test/a/posts/1','https://evil.test/a/posts/1','javascript:alert(1)','https://user:pass@facebook.com/a/posts/1','https://www.facebook.com/honeypearls.patisserie/','https://www.facebook.com/share/p/123','https://www.facebook.com/permalink.php?story_fbid[]=1&id=2'] as $bad)check(post_url($bad)==='','Reject invalid or non-post link');
parse_str(parse_url(embed_url('https://www.facebook.com/a/posts/1',100,99999),PHP_URL_QUERY),$query);
check($query['width']==='350'&&$query['height']==='1400'&&$query['href']==='https://www.facebook.com/a/posts/1','Bound dimensions and preserve target in official embed URL');
