<?php
namespace HoneyPearls\Reviews;
defined('ABSPATH') || exit;
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'settings_page_hp-reviews') return;
    wp_enqueue_style('hp-reviews', plugins_url('reviews-1.1.3.css', __FILE__), [], VERSION);
    wp_enqueue_script('hp-reviews', plugins_url('reviews-1.1.3.js', __FILE__), [], VERSION, true);
    wp_add_inline_style('hp-reviews', '.wrap>.hpr{max-width:580px}');
});
add_action('admin_menu', function () { add_options_page('Honey Pearls Reviews', 'Honey Pearls Reviews', 'manage_options', 'hp-reviews', __NAMESPACE__ . '\\admin_page'); });
function admin_page(): void {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Honey Pearls Reviews</h1><p>Shortcode: <code>[honey_pearls_reviews height="650"]</code></p><p>The carousel combines Google Maps and Tripadvisor reviews from the free Trustindex source connectors. Keep both connectors active. Their download limits and refresh schedules still apply; no paid widget subscription or additional API key is required for this presentation.</p>';
    foreach (['Google Maps' => 'wp-reviews-plugin-for-google', 'Tripadvisor' => 'review-widgets-for-tripadvisor'] as $name => $slug) echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=' . $slug . '/settings.php&tab=free-widget-configurator')) . '">Connect / manage ' . esc_html($name) . '</a></p>';
    $sources = sources();
    foreach (['google' => 'Google Maps', 'tripadvisor' => 'Tripadvisor'] as $id => $name) echo '<p><strong>' . esc_html($name) . '</strong>: ' . (isset($sources[$id]) ? count($sources[$id]['reviews']) . ' available reviews' : 'Not connected / no stored reviews') . '</p>';
    echo '<hr><h2>Preview with your stored reviews</h2>' . render() . '</div>';
}
