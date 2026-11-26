<?php

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;

function guest_rows(int $id): array
{
    global $wpdb;
    // Line items use the same tables with legacy storage and HPOS. Read orders via Woo CRUD.
    $matches = $wpdb->get_results($wpdb->prepare(
        "SELECT i.order_item_id, i.order_id FROM {$wpdb->prefix}woocommerce_order_items i
         JOIN {$wpdb->prefix}woocommerce_order_itemmeta m ON m.order_item_id=i.order_item_id
         WHERE m.meta_key='_product_id' AND m.meta_value=%s AND i.order_item_type='line_item'
         ORDER BY i.order_id, i.order_item_id",
        (string) $id,
    ));
    $rows = [];
    foreach ($matches as $match) {
        $order = wc_get_order($match->order_id);
        if (!$order instanceof \WC_Order || !$order->has_status(array_merge(wc_get_is_paid_statuses(), ['on-hold','pending']))) {
            continue;
        }
        $item = $order->get_item($match->order_item_id);
        if (!$item) {
            continue;
        }
        $places = max(0, $item->get_quantity() + $order->get_qty_refunded_for_item($item->get_id()));
        if ($places) {
            $rows[$item->get_id()] = ['number' => $order->get_order_number(),'name' => $order->get_formatted_billing_full_name(),
                'paid' => $order->is_paid(),'places' => (int) $places,'reduced' => (int) $item->get_meta('_reduced_stock'),
                'arrived' => min($places, (int) $item->get_meta('_hp_arrived'))];
        }
    }
    return $rows;
}

function verify_event(int $id): void
{
    if (!current_user_can(CAP) || !details($id)) {
        wp_die('Event unavailable.', '', ['response' => 403]);
    }
}

function guest_screen(int $id): void
{
    verify_event($id);
    $csv = wp_nonce_url(add_query_arg(['action' => 'hp_event_csv','id' => $id], admin_url('admin-post.php')), 'hp_event_csv_' . $id);
    echo '<div class="wrap"><h1>' . esc_html(get_the_title($id)) . ' &mdash; Guests</h1><p><a href="' . esc_url($csv) . '">Download guest list (CSV)</a></p>';
    $rows = guest_rows($id);
    if (!$rows) {
        echo '<p>No reservations yet.</p>';
    }
    foreach ($rows as $line => $row) {
        echo '<form style="padding:12px;background:white;margin:8px 0" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><strong>' . esc_html($row['name']) . '</strong> &middot; Order #' . esc_html($row['number']) . ' &middot; ' . (int) $row['places'] . ' guest(s)';
        if ($row['paid']) {
            wp_nonce_field('hp_event_arrived_' . $id . '_' . $line);
            printf('<input type="hidden" name="action" value="hp_event_arrived"><input type="hidden" name="id" value="%d"><input type="hidden" name="line" value="%d">
                <p><label>Arrived <input type="number" name="arrived" value="%d" min="0" max="%d" required></label> <button class="button">Save</button></p>', $id, $line, $row['arrived'], $row['places']);
        } else {
            echo ' &middot; Awaiting payment';
        }
        echo '</form>';
    }
    echo '</div>';
}

add_action('admin_post_hp_event_arrived', function () {
    $id = absint(field('id'));
    $line = absint(field('line'));
    verify_event($id);
    check_admin_referer('hp_event_arrived_' . $id . '_' . $line);
    $row = guest_rows($id)[$line] ?? null;
    $arrived = filter_var(field('arrived'), FILTER_VALIDATE_INT);
    if (!$row || !$row['paid'] || $arrived === false || $arrived < 0 || $arrived > $row['places']) {
        wp_die('Check the current guest count.', '', ['back_link' => true]);
    }
    wc_update_order_item_meta($line, '_hp_arrived', $arrived);
    wp_safe_redirect(admin_link(['guests' => $id]));
    exit;
});

add_action('admin_post_hp_event_csv', function () {
    $id = absint($_GET['id'] ?? 0);
    verify_event($id);
    check_admin_referer('hp_event_csv_' . $id);
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="event-' . $id . '-guests.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order','Booking name','Payment','Guests','Arrived'], ',', '"', '');
    foreach (guest_rows($id) as $row) {
        $cells = [$row['number'],$row['name'],$row['paid'] ? 'Confirmed' : 'Awaiting payment',$row['places'],$row['arrived']];
        foreach ($cells as &$cell) {
            if (preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', (string) $cell)) {
                $cell = "'" . $cell;
            }
        }
        unset($cell);
        fputcsv($out, $cells, ',', '"', '');
    }
    fclose($out);
    exit;
});
