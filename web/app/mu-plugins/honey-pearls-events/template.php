<?php

namespace HoneyPearls\Events;

defined('ABSPATH') || exit;
get_header();
?>
<main id="content" class="hp-events">
<?php if (listing()) : $upcoming = upcoming(); ?>
<section class="hp-event-panel">
<h1>Events</h1>
<?php if (!$upcoming) : ?>
<div class="hp-event-empty">
<div><h2>Join us at the table</h2><p>Our next dining dates will appear here.</p><a href="<?= esc_url(home_url('/')) ?>">Explore Honey Pearls &rarr;</a></div>
<?= event_image(null) ?>
</div>
<?php else : ?>
<div class="hp-event-grid">
<?php foreach ($upcoming as $post_event) : $product = wc_get_product($post_event);
    $event = details($post_event->ID); ?>
<article><a href="<?= esc_url(event_url($post_event->ID)) ?>"><?= event_image($product) ?><h2><?= esc_html($product->get_name()) ?></h2></a>
<p><?= esc_html(display_date($event)) ?></p><p><?= wp_kses_post(wc_price(wc_get_price_to_display($product))) ?> per person</p>
<?php if (!open_for_booking($product, $event)) : ?><strong>Bookings closed</strong><?php elseif (!available($product)) : ?><strong>Sold out</strong><?php endif; ?>
<a href="<?= esc_url(event_url($post_event->ID)) ?>">View event &rarr;</a></article>
<?php endforeach; ?></div><?php endif; ?></section>
<?php else : $product = wc_get_product(get_queried_object_id());
    $event = details($product->get_id());
    $places = available($product); ?>
<section class="hp-event-panel">
<a href="<?= esc_url(get_permalink(get_option('hp_events_page'))) ?>">&larr; All events</a>
<div class="hp-event-detail"><div><h1><?= esc_html($product->get_name()) ?></h1>
<p><strong><?= esc_html(display_date($event)) ?></strong><br><?= esc_html($event['venue']) ?></p>
<p><?= wp_kses_post(wc_price(wc_get_price_to_display($product))) ?> per person</p></div><?= event_image($product) ?></div>
<?php foreach (['menu' => 'Menu', 'allergens' => 'Allergen information'] as $key => $label) : if ($event[$key] !== '') : ?>
<h2><?= esc_html($label) ?></h2><?= wpautop(esc_html($event[$key])) ?>
<?php endif; endforeach; ?>
<p><a href="<?= esc_url(wc_get_page_permalink('terms')) ?>">Booking and cancellation terms</a></p>
<?php wc_print_notices();
if (open_for_booking($product, $event) && $places > 0) : $price = wc_get_price_to_display($product); ?>
<form method="post" class="hp-event-book"><label>Guests <input type="number" name="quantity" min="1" max="<?= esc_attr($places) ?>" value="1" required></label>
<input type="hidden" name="add-to-cart" value="<?= esc_attr($product->get_id()) ?>">
<output aria-live="polite">Total <?= wp_kses_post(wc_price($price)) ?></output>
<button type="submit" name="hp_book" value="1">Book places</button></form>
<script>
(() => {
 const form = document.querySelector('.hp-event-book'), qty = form.elements.quantity;
 const money = new Intl.NumberFormat('en-GB', {style:'currency', currency:<?= wp_json_encode(get_woocommerce_currency()) ?>});
 qty.addEventListener('input', () => {form.querySelector('output').textContent = qty.validity.valid ? 'Total ' + money.format(qty.valueAsNumber * <?= wp_json_encode((float) $price) ?>) : '';});
})();
</script>
<?php else : ?><p><strong><?= open_for_booking($product, $event) ? 'Sold out' : 'Bookings closed' ?></strong></p><?php endif; ?>
</section><?php endif; ?></main>
<?php get_footer();
