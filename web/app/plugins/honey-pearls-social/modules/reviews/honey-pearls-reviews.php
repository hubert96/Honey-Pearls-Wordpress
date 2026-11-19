<?php
namespace HoneyPearls\Reviews;
defined('ABSPATH') || exit;
const VERSION = '1.1.3';
require_once __DIR__ . '/sources.php';
require_once __DIR__ . '/admin.php';
add_shortcode('honey_pearls_reviews', __NAMESPACE__ . '\\render');
// Retire the unused Trustpilot job from version 1.0.
add_action('init', function () { if (wp_next_scheduled('hp_reviews_sync')) wp_clear_scheduled_hook('hp_reviews_sync'); });

function stars(float $rating, string $source): string {
    if ($source === 'tripadvisor') {
        $html = '<span class="hpr-bubbles" role="img" aria-label="' . esc_attr($rating . ' out of 5 bubbles') . '">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $rating >= $i - .25 ? 'f' : ($rating >= $i - .75 ? 'h' : 'e');
            $html .= '<img src="' . esc_url(plugins_url('assets/tripadvisor-' . $fill . '.svg', __FILE__)) . '" width="18" height="18" alt="">';
        }
        return $html . '</span>';
    }
    $html = '<span class="hpr-stars hpr-stars--' . esc_attr($source) . '" role="img" aria-label="' . esc_attr($rating . ' out of 5 stars') . '">';
    for ($i = 1; $i <= 5; $i++) $html .= '<span aria-hidden="true" class="' . ($i <= round($rating) ? 'is-filled' : '') . '">★</span>';
    return $html . '</span>';
}

function render($atts = []): string {
    $atts = shortcode_atts(['height' => 650], $atts, 'honey_pearls_reviews');
    $height = max(400, min(1000, (int) $atts['height']));
    $sources = sources();
    $reviews = interleave($sources);
    if (!$reviews) return current_user_can('manage_options') ? '<p>Honey Pearls Reviews: connect a review source in Settings → Honey Pearls Reviews.</p>' : '';
    wp_enqueue_style('hp-reviews', plugins_url('reviews-1.1.3.css', __FILE__), [], VERSION);
    wp_enqueue_script('hp-reviews', plugins_url('reviews-1.1.3.js', __FILE__), [], VERSION, true);
    ob_start(); ?>
    <section class="hpr" aria-label="Customer reviews" aria-roledescription="carousel" style="--hpr-height:<?php echo $height; ?>px">
      <header class="hpr-header"><span class="hpr-eyebrow">Kind words from our customers</span><h2>Made with love.<br>Loved by you.</h2>
        <div class="hpr-sources"><?php foreach ($sources as $id => $source): if (!$source['reviews']) continue; ?>
          <a class="hpr-source hpr-source--<?php echo esc_attr($id); ?>" href="<?php echo esc_url($source['url']); ?>" target="_blank" rel="noopener noreferrer"><b><?php if ($id === 'tripadvisor'): ?><img class="hpr-brand" src="<?php echo esc_url(plugins_url('assets/tripadvisor-logo.png', __FILE__)); ?>" alt="Tripadvisor"><?php else: echo esc_html($source['name']); endif; ?></b><?php if ($source['rating'] !== null): ?><?php if ($id === 'tripadvisor'): echo stars($source['rating'], $id); else: ?><span><?php echo esc_html(number_format_i18n($source['rating'], 1)); ?> / 5</span><?php endif; ?><?php endif; ?><small><?php echo esc_html($source['count'] . ' reviews'); ?></small></a>
        <?php endforeach; ?></div>
      </header>
      <div class="hpr-viewport">
      <?php foreach ($reviews as $index => $review): $source = $sources[$review['source']]; ?>
        <article class="hpr-card hpr-card--<?php echo esc_attr($review['source']); ?>" aria-roledescription="slide" aria-label="<?php echo ($index + 1) . ' of ' . count($reviews); ?>">
          <div class="hpr-author"><?php if ($review['avatar']): ?><img src="<?php echo esc_url($review['avatar']); ?>" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer"><span class="hpr-avatar" data-avatar-fallback hidden aria-hidden="true"><?php echo esc_html(mb_substr($review['author'], 0, 1)); ?></span><?php else: ?><span class="hpr-avatar" aria-hidden="true"><?php echo esc_html(mb_substr($review['author'], 0, 1)); ?></span><?php endif; ?><div><b><?php echo esc_html($review['author']); ?></b><time datetime="<?php echo esc_attr($review['date']); ?>"><?php echo esc_html($review['date'] ? wp_date('j F Y', strtotime($review['date'])) : ''); ?></time></div><span class="hpr-platform"><?php echo esc_html($source['name']); ?></span></div>
          <?php echo stars($review['rating'], $review['source']); ?>
          <?php if ($review['title']): ?><h3><?php echo esc_html($review['title']); ?></h3><?php endif; ?>
          <div class="hpr-text" tabindex="0" aria-label="Review text"><p><?php echo nl2br(esc_html($review['text'])); ?></p>
            <?php if ($review['photos']): ?><div class="hpr-photos"><?php foreach ($review['photos'] as $photo): ?>
              <a href="<?php echo esc_url($photo['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr('Open photo attached to the review by ' . $review['author']); ?>"><img src="<?php echo esc_url($photo['thumbnail']); ?>" alt="<?php echo esc_attr('Photo attached to the review by ' . $review['author']); ?>" width="128" height="96" loading="lazy" referrerpolicy="no-referrer"></a>
            <?php endforeach; ?></div><?php endif; ?>
          </div>
          <a class="hpr-original" href="<?php echo esc_url($review['url'] ?: $source['url']); ?>" target="_blank" rel="noopener noreferrer">Read on <?php echo esc_html($source['name']); ?> <span aria-hidden="true">↗</span></a>
        </article>
      <?php endforeach; ?>
      </div>
      <footer class="hpr-footer"><span class="hpr-position" aria-live="polite" aria-atomic="true">1 / <?php echo count($reviews); ?></span><div class="hpr-controls" hidden><button type="button" data-dir="-1" aria-label="Previous review">↑</button><button type="button" class="hpr-pause" aria-label="Pause automatic rotation" aria-pressed="false">Ⅱ</button><button type="button" data-dir="1" aria-label="Next review">↓</button></div></footer>
    </section>
    <?php return ob_get_clean();
}

/** Alternate sources; never average unrelated platform scores or filter by stars. */
function interleave(array $sources): array {
    $result = [];
    $length = max(array_map(fn($s) => count($s['reviews']), $sources) ?: [0]);
    for ($i = 0; $i < $length; $i++) foreach ($sources as $id => $source) if (isset($source['reviews'][$i])) $result[] = $source['reviews'][$i] + ['source' => $id];
    return $result;
}
