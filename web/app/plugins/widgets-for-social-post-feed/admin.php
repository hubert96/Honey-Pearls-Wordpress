<?php
defined('ABSPATH') or die('No script kiddies please!');
$pluginManager = 'TRUSTINDEX_Feed_Facebook';
$pluginManagerInstance = $trustindex_feed_facebook;
$pluginNameForEmails = 'Facebook feed';
$noContainerElementTabs = [ 'feed-configurator' ];
$logoCampaignId = 'wp-feed-facebook-l';
$logoFile = 'assets/img/trustindex.svg';
$assetCheckJs = [
'common' => 'assets/js/admin.js',
];
$assetCheckCssId = 'trustindex-feed-admin-facebook';
$assetCheckCssFile = 'assets/css/admin.css';
include(plugin_dir_path(__FILE__) . 'include' . DIRECTORY_SEPARATOR . 'admin.php');
?>