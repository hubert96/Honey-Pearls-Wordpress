<?php

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;
require_once __DIR__ . '/guests.php';

const CAP = 'manage_woocommerce';
const RESTAURANT_ADDRESS = '73 Park View, Whitley Bay NE26 3RL, United Kingdom';

function admin_link(array $args = []): string
{
    return add_query_arg(['page' => 'hp-events'] + $args, admin_url('admin.php'));
}

add_action('admin_menu', function () {
    add_menu_page('Events', 'Events', CAP, 'hp-events', __NAMESPACE__ . '\admin_screen', 'dashicons-tickets-alt', 56);
    add_submenu_page('hp-events', 'Add event', 'Add event', CAP, 'hp-event-new', static function (): void {
        edit_screen(0, absint($_GET['copy'] ?? 0));
    });
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (in_array($hook, ['toplevel_page_hp-events', 'events_page_hp-event-new'], true)) {
        wp_enqueue_style('hp-events-admin', plugins_url('admin.css', __FILE__), [], '1.0.0');
    }
});

function admin_screen(): void
{
    if (isset($_GET['edit'])) {
        edit_screen(absint($_GET['edit']));
        return;
    }
    if (isset($_GET['guests'])) {
        guest_screen(absint($_GET['guests']));
        return;
    }
    $posts = get_posts(['post_type' => 'product','post_status' => ['publish','draft'],'meta_key' => KEY,'posts_per_page' => -1]);
    echo '<div class="wrap"><h1>Events <a class="page-title-action" href="' . esc_url(admin_url('admin.php?page=hp-event-new')) . '">Add event</a></h1><table class="widefat striped hp-event-list"><thead><tr><th>Event</th><th>Date (UK)</th><th>Confirmed</th><th>Awaiting payment</th><th>Available</th><th>Actions</th></tr></thead><tbody>';
    foreach ($posts as $post) {
        $p = wc_get_product($post);
        $pending = (int) wc_get_held_stock_quantity($p);
        $confirmed = 0;
        foreach (guest_rows($p->get_id()) as $row) {
            if ($row['paid']) {
                $confirmed += $row['places'];
            } else {
                $pending += $row['reduced'];
            }
        }
        printf(
            '<tr><td class="hp-event-name"><a href="%s">%s</a> <small>%s</small></td><td class="hp-event-date">%s</td><td data-label="Confirmed">%d</td><td data-label="Awaiting payment">%d</td><td data-label="Available">%d</td><td class="hp-event-actions"><a class="button" href="%s">Guest list</a> <a class="button" href="%s">Copy event</a></td></tr>',
            esc_url(admin_link(['edit' => $post->ID])),
            esc_html($p->get_name()),
            esc_html(get_post_status_object($p->get_status())->label),
            esc_html(display_date(details($post->ID))),
            $confirmed,
            $pending,
            available($p),
            esc_url(admin_link(['guests' => $post->ID])),
            esc_url(add_query_arg(['page' => 'hp-event-new', 'copy' => $post->ID], admin_url('admin.php'))),
        );
    }
    echo '</tbody></table></div>';
}

function edit_screen(int $id = 0, int $copy = 0): void
{
    $source = $id ?: $copy;
    $p = $source ? wc_get_product($source) : null;
    $event = details($source);
    if ($source && (!$p || !$event || !current_user_can('edit_post', $source))) {
        wp_die('Event unavailable.');
    }
    if ($copy) {
        $event['start'] = '';
        $event['close'] = '';
    }
    $venue = $event['venue'] ?? '';
    if ($venue === '' && (!$id || $p?->get_status() === 'draft')) {
        $venue = RESTAURANT_ADDRESS;
    }
    wp_enqueue_media();
    wp_enqueue_script('hp-event-validation', plugins_url('validation.js', __FILE__), [], '1.0.0', true);
    echo '<div class="wrap"><h1>' . ($copy ? 'Copy event' : ($id ? 'Edit event' : 'Add event')) . '</h1>';
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success"><p>Event saved.</p></div>';
    }
    if ($id) {
        $url = $p->get_status() === 'publish' ? event_url($id) : get_preview_post_link($id);
        echo '<p><a href="' . esc_url($url) . '">View event</a> &nbsp; <a href="' . esc_url(admin_link(['guests' => $id])) . '">Guest list</a></p>';
    }
    printf(
        '<form id="hp-event-editor" action="%s" method="post" data-min-seats="%d" data-stock-ready="%d">',
        esc_url(admin_url('admin-post.php')),
        $id && $p->get_meta('_hp_opened') ? $event['capacity'] : 0,
        stock_ready(),
    );
    echo '<div id="hp-event-message" role="alert"></div>';
    wp_nonce_field('hp_event_save_' . $id);
    wp_nonce_field('hp_event_version_' . md5(serialize($copy ? [] : $event)), 'hp_event_version', false);
    printf('<input type="hidden" name="action" value="hp_event_save"><input type="hidden" name="id" value="%d">', $id);
    foreach (['title' => ['Event name','text',$p?->get_name()], 'start' => ['Starts (UK local time)','datetime-local',$event['start'] ?? ''],
        'close' => ['Close bookings (defaults to start time)','datetime-local',$event['close'] ?? ''], 'venue' => ['Venue and address','text',$venue],
        'price' => ['Price per guest (' . get_woocommerce_currency() . ')','number',$p?->get_regular_price()], 'capacity' => ['Total seats','number',$event['capacity'] ?? '']] as $key => $field) {
        printf(
            '<p><label for="hp-%1$s">%2$s</label><br><input id="hp-%1$s" class="regular-text" name="%1$s" type="%3$s" value="%4$s" %5$s></p>',
            esc_attr($key),
            esc_html($field[0]),
            esc_attr($field[1]),
            esc_attr($field[2] ?? ''),
            $key === 'price' ? 'min="0" step="0.01"' : ($key === 'capacity' ? 'min="0" step="1"' : ''),
        );
    }
    foreach (['menu' => 'Menu', 'allergens' => 'Allergen information'] as $key => $label) {
        printf('<p><label>%s<br><textarea class="large-text" rows="4" name="%s">%s</textarea></label></p>', $label, $key, esc_textarea($event[$key] ?? ''));
    }
    ?>
    <p><input type="hidden" name="image" id="hp-image" value="<?= (int) ($p?->get_image_id() ?? 0) ?>">
    <span id="hp-photo"><?= $p && $p->get_image_id() ? wp_get_attachment_image($p->get_image_id(), 'thumbnail') : '' ?></span>
    <button type="button" class="button" id="hp-select">Choose event photo</button></p>
    <script>document.getElementById('hp-select').onclick = () => {
        const picker = wp.media({title:'Event photo', library:{type:'image'}, multiple:false});
        picker.on('select', () => {const photo = picker.state().get('selection').first().toJSON();
            document.querySelector('#hp-image').value = photo.id;
            const img = new Image(100); img.src = photo.url; document.querySelector('#hp-photo').replaceChildren(img);
        }); picker.open();
    };</script>
    <p><button class="button button-primary" name="status" value="publish"><?= $id && $p->get_status() === 'publish' ? 'Update event' : 'Publish event' ?></button>
    <button class="button" name="status" value="draft">Save draft</button></p></form></div>
    <?php
}

function field(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim(wp_unslash($_POST[$key])) : '';
}

function stock_ready(): bool
{
    return get_option('woocommerce_manage_stock') === 'yes' && (int) get_option('woocommerce_hold_stock_minutes', 60) > 0;
}

function editor_error(string $message, string $key = ''): void
{
    global $wpdb;
    $wpdb->query('ROLLBACK');
    if (field('inline_validation') === '1') {
        wp_send_json_error(['message' => $message, 'field' => $key], 422);
    }
    wp_die(esc_html($message), '', ['back_link' => true]);
}

add_action('admin_post_hp_event_save', function () {
    $id = absint(field('id'));
    check_admin_referer('hp_event_save_' . $id);
    if (!current_user_can(CAP) || ($id && (!details($id) || !current_user_can('edit_post', $id)))) {
        wp_die('Permission denied.', '', ['response' => 403]);
    }
    $status = field('status') === 'publish' ? 'publish' : 'draft';
    global $wpdb;
    // Lock concurrent editor saves and commit the capacity and stock adjustment together.
    $wpdb->query('START TRANSACTION');
    if ($id) {
        $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $id));
        clean_post_cache($id);
    }
    $p = $id ? wc_get_product($id) : new \WC_Product_Simple();
    $old = details($id);
    if (!wp_verify_nonce(field('hp_event_version'), 'hp_event_version_' . md5(serialize($old)))) {
        editor_error('This event was edited elsewhere. Reload it before saving.');
    }
    $capacity = filter_var(field('capacity') ?: '0', FILTER_VALIDATE_INT);
    $price = field('price');
    $event = ['start' => field('start'), 'close' => field('close'), 'venue' => sanitize_text_field(field('venue')),
        'menu' => sanitize_textarea_field(field('menu')), 'allergens' => sanitize_textarea_field(field('allergens')), 'capacity' => $capacity];
    $start = local_date($event['start']);
    $close = local_date($event['close']);
    $name = sanitize_text_field(field('title'));
    if ($name === '') {
        editor_error('Enter the event name.', 'title');
    }
    if ($capacity === false || $capacity < 0 || ($status === 'publish' && !$capacity)) {
        editor_error('Enter a whole number of seats. At least one seat is required to publish.', 'capacity');
    }
    if (($price !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/D', $price)) || ($status === 'publish' && $price === '')) {
        editor_error('Enter a non-negative price with up to two decimal places.', 'price');
    }
    if (($event['start'] !== '' || $status === 'publish') && !$start) {
        editor_error('Enter a valid UK start date and time.', 'start');
    }
    if ($event['close'] !== '' && (!$close || !$start || $close > $start)) {
        editor_error('Choose a valid UK closing time at or before the event starts.', 'close');
    }
    if ($status === 'publish') {
        foreach (['venue' => 'Enter the venue and address.', 'menu' => 'Enter the event menu.', 'allergens' => 'Enter the allergen information.'] as $key => $message) {
            if ($event[$key] === '') {
                editor_error($message, $key);
            }
        }
        if (!stock_ready()) {
            editor_error('Enable stock management and a positive stock hold time in WooCommerce before publishing.');
        }
    }
    // Once sales have opened, lowering capacity can race a concurrent checkout reservation.
    if ($id && $p->get_meta('_hp_opened') && $capacity < $old['capacity']) {
        editor_error('Once bookings open, you can only increase the seat limit.', 'capacity');
    }
    $p->set_name($name);
    $p->set_status($status);
    $p->set_virtual(true);
    $p->set_catalog_visibility('hidden');
    $p->set_manage_stock(true);
    $p->set_backorders('no');
    $p->set_regular_price($price);
    $p->set_price($price);
    $photo = absint(field('image'));
    $p->set_image_id(wp_attachment_is_image($photo) ? $photo : 0);
    if (!$id) {
        $p->set_stock_quantity($capacity);
    }
    $p->update_meta_data(KEY, $event);
    $p->update_meta_data('_hp_event_start', $start?->getTimestamp() ?? 0);
    if ($status === 'publish') {
        $p->update_meta_data('_hp_opened', 1);
    }
    $p->save();
    if ($id && $capacity !== $old['capacity']) {
        $delta = $capacity - $old['capacity'];
        wc_update_product_stock($p, abs($delta), $delta > 0 ? 'increase' : 'decrease');
    }
    $wpdb->query('COMMIT');
    if (field('inline_validation') === '1') {
        wp_send_json_success(['redirect' => admin_link(['edit' => $p->get_id(), 'saved' => 1])]);
    }
    wp_safe_redirect(admin_link(['edit' => $p->get_id(),'saved' => 1]));
    exit;
});
