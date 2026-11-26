<?php

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;

function listing(): bool
{
    $id = (int) get_option('hp_events_page');
    return $id && is_page($id);
}

add_filter('wp_nav_menu_items', function ($items, $args) {
    $menu = wp_get_nav_menu_object($args->menu ?? '');
    if (!$menu || $menu->slug !== 'header-menu') {
        return $items;
    }
    $url = get_permalink(get_option('hp_events_page'));
    if (!$url || str_contains($items, 'href="' . esc_url($url) . '"')) {
        return $items;
    }
    $current = listing() || (is_singular('product') && details(get_queried_object_id()));
    return $items . '<li class="menu-item"><a class="ekit-menu-nav-link" ' . ($current ? 'aria-current="page" ' : '') . 'href="' . esc_url($url) . '">Events</a></li>';
}, 20, 2);

add_action('template_redirect', function () {
    if (get_query_var('hp_event_route') && !details(get_queried_object_id())) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
        remove_action('template_redirect', 'redirect_canonical');
    }
}, 1);

add_filter('template_include', function ($template) {
    if (!is_404() && (listing() || (is_singular('product') && details(get_queried_object_id())))) {
        wp_enqueue_style('hp-events', plugins_url('events.css', __FILE__), [], '1.0.0');
        return __DIR__ . '/template.php';
    }
    return $template;
}, 999);

add_filter('woocommerce_add_to_cart_redirect', function ($url, $product) {
    return $product && details($product->get_id()) && isset($_POST['hp_book']) ? wc_get_checkout_url() : $url;
}, 10, 2);

function event_image($product, string $size = 'large'): string
{
    $id = $product ? $product->get_image_id() : 0;
    if (!$id) {
        $id = attachment_url_to_postid(content_url('uploads/2026/11/hp-home-greek-table-wraps-scaled.jpg'));
    }
    return $id ? wp_get_attachment_image($id, $size, false, ['class' => 'hp-event-photo']) : '';
}

function upcoming(): array
{
    return get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1,
        'meta_key' => '_hp_event_start', 'meta_value' => time(), 'meta_compare' => '>', 'meta_type' => 'NUMERIC',
        'orderby' => 'meta_value_num', 'order' => 'ASC']);
}
