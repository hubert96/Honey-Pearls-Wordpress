<?php

/**
 * Plugin Name: Honey Pearls Events
 * Description: Dining events, seat reservations and guest lists in WooCommerce.
 * Version: 1.0.0
 */

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;

const KEY = '_hp_dining_event';
const FOLDER = __DIR__ . '/honey-pearls-events/';

function details($id): array
{
    $data = get_post_meta($id, KEY, true);
    return is_array($data) ? $data : [];
}

function local_date(string $value): ?\DateTimeImmutable
{
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new \DateTimeZone('Europe/London'));
    return $date && $date->format('Y-m-d\TH:i') === $value ? $date : null;
}

function display_date(array $event): string
{
    return local_date($event['start'] ?? '')?->format('l j F Y, H:i T') ?? '';
}

function open_for_booking($product, array $event): bool
{
    $close = local_date($event['close'] ?: $event['start']);
    $start = local_date($event['start']);
    return $product->get_status() === 'publish' && $product->managing_stock()
        && get_option('woocommerce_hold_stock_minutes', 60) > 0
        && $close && $start && min($close->getTimestamp(), $start->getTimestamp()) > time();
}

function available($product): int
{
    return max(0, (int) $product->get_stock_quantity() - (int) wc_get_held_stock_quantity($product));
}

function event_url($id): string
{
    return home_url(user_trailingslashit('/events/' . get_post_field('post_name', $id)));
}

add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        return;
    }
    require_once FOLDER . 'public.php';
    if (is_admin()) {
        require_once FOLDER . 'admin.php';
    }
});

add_action('init', function () {
    if (!class_exists('WC_Product_Simple')) {
        return;
    }
    add_rewrite_rule('^events/([^/]+)/?$', 'index.php?post_type=product&name=$matches[1]&hp_event_route=1', 'top');
    if (get_option('hp_events_installed') || wp_installing()) {
        return;
    }
    // Serialize first-request setup so simultaneous visitors cannot create duplicate pages.
    if (!add_option('hp_events_setup_lock', time(), '', false)) {
        if ((int) get_option('hp_events_setup_lock') < time() - 120) {
            delete_option('hp_events_setup_lock');
        }
        return;
    }
    try {
        $page = get_page_by_path('events', OBJECT, 'page');
        $id = $page ? $page->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Events', 'post_name' => 'events']);
        if (!$id) {
            return;
        }
        update_option('hp_events_page', $id);
        $draft = new \WC_Product_Simple();
        $draft->set_name('Greek dining evening');
        $draft->set_status('draft');
        $draft->set_virtual(true);
        $draft->set_catalog_visibility('hidden');
        $draft->set_manage_stock(true);
        $draft->set_stock_quantity(0);
        $draft->update_meta_data(KEY, ['start' => '','close' => '','venue' => '','menu' => '','allergens' => '','capacity' => 0]);
        $draft->save();
        flush_rewrite_rules(false);
        update_option('hp_events_installed', 1);
    } finally {
        delete_option('hp_events_setup_lock');
    }
}, 99);

add_filter('query_vars', function ($vars) {
    $vars[] = 'hp_event_route';
    return $vars;
});

add_filter('post_type_link', function ($url, $post) {
    return $post->post_type === 'product' && $post->post_status === 'publish' && details($post->ID)
        ? event_url($post->ID) : $url;
}, 10, 2);

add_filter('woocommerce_is_purchasable', function ($allowed, $product) {
    $event = details($product->get_id());
    return $allowed && (!$event || open_for_booking($product, $event));
}, 10, 2);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $key, $values) {
    $event = details($values['product_id']);
    if ($event) {
        // Visible line metadata is included in both HTML and plain-text order confirmations.
        $item->add_meta_data('Event date', display_date($event), true);
        $item->add_meta_data('Venue', $event['venue'], true);
    }
}, 10, 3);
