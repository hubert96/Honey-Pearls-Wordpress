<?php
/**
 * Plugin Name: Honey Pearls Social
 * Description: Instagram, Facebook and Google/Tripadvisor reviews in one Honey Pearls dashboard.
 * Version: 2.0.0
 * Requires PHP: 8.1
 * Author: Honey Pearls
 * License: GPL-2.0-or-later
 */
namespace HoneyPearls\Social;
defined('ABSPATH') || exit;

require_once __DIR__ . '/modules/instagram/honey-pearls-instagram.php';
require_once __DIR__ . '/modules/reviews/honey-pearls-reviews.php';
require_once __DIR__ . '/modules/facebook/honey-pearls-facebook.php';

function schedule(): void {
    if (!wp_next_scheduled('hp_instagram_sync')) {
        wp_schedule_event(time() + 60, 'hourly', 'hp_instagram_sync');
    }
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\schedule');
// Also repairs scheduling when upgrading through one of the legacy loaders.
add_action('init', __NAMESPACE__ . '\\schedule');
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('hp_instagram_sync');
    wp_clear_scheduled_hook('hp_fb_sync');
});

add_action('admin_menu', function () {
    add_menu_page('Honey Pearls Social', 'Honey Pearls Social', 'manage_options', 'hp-social', __NAMESPACE__ . '\\dashboard', 'dashicons-share', 58);
    add_submenu_page('hp-social', 'Overview', 'Overview', 'manage_options', 'hp-social', __NAMESPACE__ . '\\dashboard');
    foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'reviews' => 'Google & Tripadvisor'] as $slug => $label) {
        // Preserve registered Settings URLs, Settings API permissions and OAuth return links.
        remove_submenu_page('options-general.php', 'hp-' . $slug);
        add_submenu_page('hp-social', $label, $label, 'manage_options', 'options-general.php?page=hp-' . $slug);
    }
}, 99);
add_filter('parent_file', function ($parent) {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return in_array($page, ['hp-instagram', 'hp-facebook', 'hp-reviews'], true) ? 'hp-social' : $parent;
});

function dashboard(): void {
    if (!current_user_can('manage_options')) return;
    $instagram = (array) get_option('hp_ig_posts', []);
    $facebook = (array) get_option('hp_fb_feed', []);
    $auth = (array) get_option('hp_fb_auth', []);
    $sources = \HoneyPearls\Reviews\sources();
    echo '<div class="wrap"><h1>Honey Pearls Social</h1><p>Manage your social feeds and customer reviews in one place. Existing Elementor shortcodes and saved connections are preserved.</p>';
    $rows = [
        ['instagram', 'Instagram', count($instagram) . ' cached posts', 'honey_pearls_instagram'],
        ['facebook', 'Facebook', empty($auth['token']) ? 'Not connected — saved previews only' : 'Connected: ' . ($auth['page_name'] ?? '') . ' · ' . count($facebook['posts'] ?? []) . ' cached posts', 'honey_pearls_facebook'],
        ['reviews', 'Google & Tripadvisor', count(\HoneyPearls\Reviews\interleave($sources)) . ' cached reviews', 'honey_pearls_reviews'],
    ];
    echo '<table class="widefat striped"><thead><tr><th>Module</th><th>Status</th><th>Elementor shortcode</th><th>Settings</th></tr></thead><tbody>';
    foreach ($rows as [$slug, $label, $status, $shortcode]) {
        echo '<tr><td><strong>' . esc_html($label) . '</strong></td><td>' . esc_html($status) . '</td><td><code>[' . esc_html($shortcode) . ']</code></td><td><a class="button" href="' . esc_url(admin_url('options-general.php?page=hp-' . $slug)) . '">Manage ' . esc_html($label) . '</a></td></tr>';
    }
    echo '</tbody></table><p>Facebook refreshes every 15 minutes; Instagram refreshes hourly through WordPress cron. Google and Tripadvisor use the existing free source connectors, which must stay active.</p><p>Deactivation preserves connections, cached posts, reviews and settings.</p></div>';
}
