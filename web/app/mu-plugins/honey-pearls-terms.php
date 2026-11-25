<?php
/**
 * Plugin Name: Honey Pearls Terms and Conditions
 * Description: Publishes the shop terms and includes the applicable version in order emails.
 */

namespace HoneyPearls\Terms;

defined('ABSPATH') || exit;

const CURRENT_VERSION = '2026-11-25';
const ORDER_META_KEY = '_hp_terms_version';
const PAYMENT_LABEL = 'Place order and pay';

function document_path(string $version): string
{
    return __DIR__ . '/honey-pearls-privacy/terms-' . $version . '.html';
}

function terms_page_id(): int
{
    if (!function_exists('wc_get_page_id')) {
        return 0;
    }

    $id = wc_get_page_id('terms');
    return $id > 0 && get_post_status($id) === 'publish' ? $id : 0;
}

add_shortcode('honey_pearls_terms', function (): string {
    $document = file_get_contents(document_path(CURRENT_VERSION));
    return preg_match('~<body>\s*(.*?)\s*</body>~s', $document, $parts) ? $parts[1] : '';
});

add_action('wp_enqueue_scripts', function (): void {
    $termsPageId = terms_page_id();
    $checkout = function_exists('is_checkout') && is_checkout();
    $cart = function_exists('is_cart') && is_cart();
    $product = function_exists('is_product') && is_product();

    if (($termsPageId && is_page($termsPageId)) || $checkout || $cart || $product) {
        wp_enqueue_style(
            'honey-pearls-legal',
            plugin_dir_url(__FILE__) . 'honey-pearls-privacy/policy.css',
            [],
            '1.1.0'
        );
    }

    if ($checkout) {
        wp_enqueue_script(
            'honey-pearls-order-button',
            plugin_dir_url(__FILE__) . 'honey-pearls-privacy/checkout-terms.js',
            ['wc-blocks-checkout'],
            '1.0.0',
            true
        );
    }
});

add_filter('wp_nav_menu_objects', function (array $items): array {
    $termsPageId = terms_page_id();
    if (!$termsPageId) {
        return $items;
    }

    foreach ($items as $item) {
        if ((int) $item->ID === 5127 && $item->url === '#') {
            $item->title = 'Terms and Conditions';
            $item->url = get_permalink($termsPageId);
        }
    }

    return $items;
});

function purchase_notice(): string
{
    $termsPageId = terms_page_id();
    if (!$termsPageId) {
        return '';
    }

    return '<p class="hp-express-terms">Orders are for collection. Read our <a href="'
        . esc_url(get_permalink($termsPageId))
        . '" target="_blank" rel="noopener">Terms and Conditions (opens in a new tab)</a> before paying.</p>';
}

function express_terms_notice(string $content): string
{
    return $content === '' ? '' : purchase_notice() . $content;
}

add_filter('render_block_woocommerce/cart-express-payment-block', __NAMESPACE__ . '\express_terms_notice');
add_filter('render_block_woocommerce/checkout-express-payment-block', __NAMESPACE__ . '\express_terms_notice');
add_action('woocommerce_before_add_to_cart_button', function (): void {
    echo purchase_notice();
});
add_filter('woocommerce_order_button_text', function (): string {
    return PAYMENT_LABEL;
});

function record_terms_version(\WC_Order $order): void
{
    if (terms_page_id() && !$order->get_meta(ORDER_META_KEY, true)) {
        $order->update_meta_data(ORDER_META_KEY, CURRENT_VERSION);
    }
}

add_action('woocommerce_checkout_create_order', __NAMESPACE__ . '\record_terms_version');
add_action('woocommerce_store_api_checkout_update_order_from_request', function (\WC_Order $order, \WP_REST_Request $request): void {
    if ($request->get_method() === 'POST') {
        record_terms_version($order);
    }
}, 10, 2);

add_filter('woocommerce_email_attachments', function (array $attachments, string $emailId, $order): array {
    if (!$order instanceof \WC_Order || !in_array($emailId, [
        'customer_on_hold_order',
        'customer_processing_order',
        'customer_completed_order',
    ], true)) {
        return $attachments;
    }

    $version = $order->get_meta(ORDER_META_KEY, true);
    if (is_string($version) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $version)) {
        $path = document_path($version);
        if (is_readable($path)) {
            $attachments[] = $path;
        }
    }

    return $attachments;
}, 10, 3);
