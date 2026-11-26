<?php

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;
require_once __DIR__ . '/guests.php';

const CAP = 'manage_woocommerce';

function admin_link(array $args = []): string
{
    return add_query_arg(['page' => 'hp-events'] + $args, admin_url('admin.php'));
}

add_action('admin_menu', function () {
    add_menu_page('Events', 'Events', CAP, 'hp-events', __NAMESPACE__ . '\admin_screen', 'dashicons-tickets-alt', 56);
    add_submenu_page('hp-events', 'Add event', 'Add event', CAP, 'hp-event-new', __NAMESPACE__ . '\edit_screen');
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
    echo '<div class="wrap"><h1>Events <a class="page-title-action" href="' . esc_url(admin_url('admin.php?page=hp-event-new')) . '">Add event</a></h1><table class="widefat striped"><thead><tr><th>Event</th><th>Date (UK)</th><th>Confirmed</th><th>Awaiting payment</th><th>Available</th><th>Guests</th></tr></thead><tbody>';
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
            '<tr><td><a href="%s">%s</a> (%s)</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td><a href="%s">Guest list</a></td></tr>',
            esc_url(admin_link(['edit' => $post->ID])),
            esc_html($p->get_name()),
            esc_html($p->get_status()),
            esc_html(display_date(details($post->ID))),
            $confirmed,
            $pending,
            available($p),
            esc_url(admin_link(['guests' => $post->ID])),
        );
    }
    echo '</tbody></table></div>';
}

function edit_screen(int $id = 0): void
{
    $p = $id ? wc_get_product($id) : null;
    $event = details($id);
    if ($id && (!$p || !$event)) {
        wp_die('Event not found.');
    }
    wp_enqueue_media();
    echo '<div class="wrap"><h1>' . ($id ? 'Edit event' : 'Add event') . '</h1>';
    if (isset($_GET['saved'])) {
        echo '<div class="notice notice-success"><p>Event saved.</p></div>';
    }
    if ($id) {
        $url = $p->get_status() === 'publish' ? event_url($id) : get_preview_post_link($id);
        echo '<p><a href="' . esc_url($url) . '">View event</a> &nbsp; <a href="' . esc_url(admin_link(['guests' => $id])) . '">Guest list</a></p>';
    }
    echo '<form action="' . esc_url(admin_url('admin-post.php')) . '" method="post">';
    wp_nonce_field('hp_event_save_' . $id);
    wp_nonce_field('hp_event_version_' . md5(serialize($event)), 'hp_event_version', false);
    printf('<input type="hidden" name="action" value="hp_event_save"><input type="hidden" name="id" value="%d">', $id);
    foreach (['title' => ['Event name','text',$p?->get_name()], 'start' => ['Starts (UK local time)','datetime-local',$event['start'] ?? ''],
        'close' => ['Close bookings (defaults to start time)','datetime-local',$event['close'] ?? ''], 'venue' => ['Venue and address','text',$event['venue'] ?? ''],
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
    <p><button class="button button-primary" name="status" value="publish"><?= $p?->get_status() === 'publish' ? 'Update event' : 'Publish event' ?></button>
    <button class="button" name="status" value="draft">Save draft</button></p></form></div>
    <?php
}

function field(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim(wp_unslash($_POST[$key])) : '';
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
        wp_die('This event was edited elsewhere. Reload it before saving.', '', ['back_link' => true]);
    }
    $capacity = filter_var(field('capacity') ?: '0', FILTER_VALIDATE_INT);
    $price = field('price');
    $event = ['start' => field('start'), 'close' => field('close'), 'venue' => sanitize_text_field(field('venue')),
        'menu' => sanitize_textarea_field(field('menu')), 'allergens' => sanitize_textarea_field(field('allergens')), 'capacity' => $capacity];
    $start = local_date($event['start']);
    $close = local_date($event['close']);
    $name = sanitize_text_field(field('title'));
    if (!$name || $capacity === false || $capacity < 0 || ($price !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/D', $price))
        || ($event['start'] && !$start) || ($event['close'] && (!$close || !$start || $close > $start))) {
        wp_die('Check the name, price, seat count and booking dates.', '', ['back_link' => true]);
    }
    if ($status === 'publish' && (!$capacity || $price === '' || !$start || !$event['venue'] || !$event['menu'] || !$event['allergens']
        || get_option('woocommerce_manage_stock') !== 'yes' || (int) get_option('woocommerce_hold_stock_minutes', 60) < 1)) {
        wp_die('Publishing requires a date, venue, menu, allergen information, price, seats and active WooCommerce stock holds.', '', ['back_link' => true]);
    }
    // Once sales have opened, lowering capacity can race a concurrent checkout reservation.
    if ($id && $p->get_meta('_hp_opened') && $capacity < $old['capacity']) {
        wp_die('To stop new reservations, change the booking closing time.', '', ['back_link' => true]);
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
    wp_safe_redirect(admin_link(['edit' => $p->get_id(),'saved' => 1]));
    exit;
});
