<?php
namespace Elementor;
use TRUSTINDEX_Feed_Facebook;
use Elementor\Widget_Base;
defined('ABSPATH') or die('No script kiddies please!');
class TrustrindexFeedWidget_Facebook extends Widget_Base {
public function get_name() {
return 'widgets-for-social-post-feed';
}
public function get_title() {
return __('Facebook Feed', 'widgets-for-social-post-feed');
}
public function get_icon() {

return 'eicon-facebook';
}
public function get_categories() {
return ['trustindex'];
}
protected function render() {
$pluginManagerInstance = new TRUSTINDEX_Feed_Facebook("facebook", __FILE__, "1.7.8", "Widgets for Social Post Feed", "Facebook");
echo do_shortcode('['.$pluginManagerInstance->getShortcodeName().']');
}
}
