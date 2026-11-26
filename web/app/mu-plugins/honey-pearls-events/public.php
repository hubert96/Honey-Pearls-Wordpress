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

function upcoming(int $limit = -1): array
{
    return get_posts(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => $limit,
        'meta_key' => '_hp_event_start', 'meta_value' => time(), 'meta_compare' => '>', 'meta_type' => 'NUMERIC',
        'orderby' => 'meta_value_num', 'order' => 'ASC']);
}

add_action('wp_enqueue_scripts', function () {
    if (is_front_page()) {
        wp_enqueue_style('hp-next-event', plugins_url('next-event.css', __FILE__), [], '1.1.0');
    }
});

add_filter('the_content', function ($content) {
    if (!is_front_page() || !in_the_loop() || !is_main_query() || get_the_ID() !== get_queried_object_id()) {
        return $content;
    }
    $next = upcoming(1);
    if (!$next) {
        return $content;
    }
    $product = wc_get_product($next[0]);
    $event = details($next[0]->ID);
    if (!$product || !$event) {
        return $content;
    }
    $status = '';
    if (!open_for_booking($product, $event)) {
        $status = ' · Bookings closed';
    } elseif (!available($product)) {
        $status = ' · Sold out';
    }
    $widget = sprintf(
        '<aside class="hp-next-event" aria-label="Upcoming event">%s<div><span>Next event</span><h2>%s</h2><p>%s%s</p></div><a href="%s">View event <span aria-hidden="true">→</span></a></aside>',
        event_image($product, 'medium'),
        esc_html($product->get_name()),
        esc_html(local_date($event['start'])->format('D j M Y · H:i T')),
        esc_html($status),
        esc_url(event_url($product->get_id())),
    );
    return $widget . $content;
}, 99);
