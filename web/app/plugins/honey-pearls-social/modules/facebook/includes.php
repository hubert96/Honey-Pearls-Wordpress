<?php
namespace HoneyPearls\Facebook;
defined('ABSPATH') || exit;
/** Accept only explicit public-content permalinks, never arbitrary iframe URLs. */
function post_url(string $raw): string {
    $u=parse_url(trim($raw));
    if (!$u || strtolower($u['scheme'] ?? '')!=='https' || isset($u['user']) || isset($u['pass']) || isset($u['port'])) return '';
    if (!in_array(strtolower($u['host'] ?? ''),['facebook.com','www.facebook.com','m.facebook.com'],true)) return '';
    $path=$u['path'] ?? ''; $query=[]; parse_str($u['query'] ?? '',$query);
    if (preg_match('~^/[a-zA-Z0-9.]+/(?:posts|videos)/[a-zA-Z0-9]+/?$~',$path)) return 'https://www.facebook.com'.rtrim($path,'/');
    if (in_array($path,['/permalink.php','/story.php'],true) && is_string($query['story_fbid'] ?? null) && is_string($query['id'] ?? null) && preg_match('/^[a-zA-Z0-9]+$/',$query['story_fbid']) && ctype_digit($query['id'])) return 'https://www.facebook.com/permalink.php?'.http_build_query(['story_fbid'=>$query['story_fbid'],'id'=>$query['id']]);
    if (in_array($path,['/photo.php','/photo/'],true) && is_string($query['fbid'] ?? null) && ctype_digit($query['fbid'])) return 'https://www.facebook.com/photo.php?fbid='.$query['fbid'];
    return '';
}
