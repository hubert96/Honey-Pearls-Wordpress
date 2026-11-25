<?php
/**
 * Plugin Name: Honey Pearls Privacy
 * Description: Renders the public privacy notice and limits optional third-party content.
 */

namespace HoneyPearls\Privacy;

defined('ABSPATH') || exit;

add_shortcode('honey_pearls_privacy_policy', function (): string {
    return file_get_contents(__DIR__ . '/honey-pearls-privacy/policy.html');
});

add_action('wp_enqueue_scripts', function (): void {
    if (is_privacy_policy()) {
        wp_enqueue_style(
            'honey-pearls-privacy',
            plugin_dir_url(__FILE__) . 'honey-pearls-privacy/policy.css',
            [],
            '1.0.0'
        );
    }
});

// Keep the site's footer social links while retiring its demo newsletter widgets.
add_filter('elementor/frontend/widget/should_render', function (bool $shouldRender, $widget): bool {
    return $shouldRender && !in_array($widget->get_id(), ['b99cf70', '6c5bb04', '443453a'], true);
}, 10, 2);

add_filter('elementor/widget/render_content', function (string $content, $widget): string {
    if ($widget->get_name() !== 'google_maps' || $widget->get_id() !== '3cbba3b') {
        return $content;
    }

    $directions = 'https://www.google.com/maps/dir/?api=1&destination='
        . rawurlencode('Honey Pearls, 73 Park View, Whitley Bay NE26 3RL, United Kingdom');

    return '<div class="elementor-button-wrapper"><a class="elementor-button" href="'
        . esc_url($directions) . '">Get directions on Google Maps</a></div>';
}, 10, 2);

add_filter('nav_menu_link_attributes', function (array $attributes, $item): array {
    if ((int) $item->ID === 5123 && ($url = get_privacy_policy_url())) {
        $attributes['href'] = $url;
    }

    return $attributes;
}, 10, 2);
