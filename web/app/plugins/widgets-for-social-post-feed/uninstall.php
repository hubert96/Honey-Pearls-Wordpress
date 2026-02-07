<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
die;
}
require_once plugin_dir_path( __FILE__ ) . 'trustindex-feed-plugin.class.php';
$trustindex_feed_facebook = new TRUSTINDEX_Feed_Facebook("facebook", __FILE__, "1.7.8", "Widgets for Social Post Feed", "Facebook");
$trustindex_feed_facebook->uninstall();
?>