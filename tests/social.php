<?php
// Exercise bundle/legacy load order and cron lifecycle without touching live data.
const ABSPATH = __DIR__;
$hooks = $activation = $deactivation = $shortcodes = $scheduled = [];
function add_action($hook, $callback, ...$args) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_filter(...$args) {}
function add_shortcode($name, $callback) { $GLOBALS['shortcodes'][$name] = $callback; }
function register_activation_hook($file, $callback) { $GLOBALS['activation'][$file] = $callback; }
function register_deactivation_hook($file, $callback) { $GLOBALS['deactivation'][$file] = $callback; }
function get_option($key, $default = false) { return $default; }
function wp_next_scheduled($hook) { return $GLOBALS['scheduled'][$hook] ?? false; }
function wp_schedule_event($time, $frequency, $hook) { if (isset($GLOBALS['scheduled'][$hook])) throw new Exception('Duplicate job'); $GLOBALS['scheduled'][$hook] = $frequency; }
function wp_clear_scheduled_hook($hook) { unset($GLOBALS['scheduled'][$hook]); }
$base = __DIR__ . '/../web/app/plugins/';
$bundle = $base . 'honey-pearls-social/honey-pearls-social.php';
$files = [$bundle];
foreach (['instagram', 'facebook', 'reviews'] as $name) $files[] = $base . "honey-pearls-$name/honey-pearls-$name.php";
if (($argv[1] ?? '') === 'legacy-first') $files = array_reverse($files);
foreach ($files as $file) require_once $file;
foreach ($hooks['init'] as $callback) $callback();
foreach ($hooks['init'] as $callback) $callback();
if (count($shortcodes) !== 3) throw new Exception('Missing or duplicate feed registration');
if (count($hooks['admin_post_hp_instagram_callback']) !== 1 || count($hooks['admin_post_hp_facebook_callback']) !== 1) throw new Exception('OAuth callback registered twice');
if (count($activation) !== 1 || count($deactivation) !== 1) throw new Exception('Lifecycle must belong to bundle only');
if (($scheduled['hp_instagram_sync'] ?? '') !== 'hourly') throw new Exception('Instagram cron not restored');
$scheduled['hp_fb_sync'] = 'hp_fb_quarter_hour';
foreach ($deactivation as $callback) $callback();
if ($scheduled) throw new Exception('Cron jobs survive bundle deactivation');
echo 'PASS bundle and legacy loading, shortcode/OAuth registration and cron lifecycle (' . ($argv[1] ?? 'bundle-first') . ")\n";
