<?php
/**
 * Plugin Name: Honey Pearls Instagram
 * Description: Instagram feed with local image caching, automatic updates and an accessible gallery.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Author: Honey Pearls
 */

namespace HoneyPearls\Instagram;

defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/feed.php';
require_once __DIR__ . '/includes/admin.php';

const VERSION = '1.0.0';
const FILE = __FILE__;
const CRON = 'hp_instagram_sync';

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled(CRON)) {
        wp_schedule_event(time() + 60, 'hourly', CRON);
    }
});
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook(CRON); });
add_action(CRON, __NAMESPACE__ . '\\sync');
add_action('admin_menu', __NAMESPACE__ . '\\admin_menu');
add_action('admin_post_hp_instagram', __NAMESPACE__ . '\\admin_action');
add_action('admin_post_hp_instagram_callback', __NAMESPACE__ . '\\oauth_callback');
add_action('init', function () {
    add_shortcode('honey_pearls_instagram', __NAMESPACE__ . '\\render');
    if (!empty(settings()['replace_legacy'])) {
        add_shortcode('trustindex-feed-instagram', __NAMESPACE__ . '\\render');
    }
}, 99);
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\assets');

function assets(): void
{
    wp_enqueue_style('hp-instagram', plugins_url('assets/feed.css', FILE), [], VERSION);
    wp_enqueue_script('hp-instagram', plugins_url('assets/feed.js', FILE), [], VERSION, ['in_footer' => true, 'strategy' => 'defer']);
}

function settings(): array
{
    return array_merge([
        'username' => 'honeypearls.patisserie', 'title' => 'Fresh from our Instagram',
        'layout' => 'carousel', 'columns' => 4, 'limit' => 12, 'accent' => '#976d45',
        'captions' => true, 'replace_legacy' => false, 'hidden' => [], 'app_id' => '',
    ], (array) get_option('hp_ig_settings', []));
}

function render($atts = []): string
{
    $s = settings();
    $atts = shortcode_atts(['layout' => $s['layout'], 'limit' => $s['limit']], (array) $atts);
    $layout = $atts['layout'] === 'grid' ? 'grid' : 'carousel';
    $posts = array_values(array_filter((array) get_option('hp_ig_posts', []),
        fn ($p) => !in_array($p['id'], $s['hidden'], true)));
    $posts = array_slice($posts, 0, max(1, min(50, (int) $atts['limit'])));
    $profile = 'https://www.instagram.com/' . rawurlencode($s['username']) . '/';
    $id = wp_unique_id('hp-ig-');
    ob_start();
    ?>
    <section class="hp-ig" aria-label="Instagram feed" style="--hp-ig-columns:<?php echo (int) $s['columns']; ?>;--hp-ig-accent:<?php echo esc_attr($s['accent']); ?>">
        <header class="hp-ig__header">
            <div><span class="hp-ig__eyebrow">A little taste of our day</span><h2><?php echo esc_html($s['title']); ?></h2>
                <a href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">@<?php echo esc_html($s['username']); ?></a></div>
            <a class="hp-ig__follow" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">Follow on Instagram <span aria-hidden="true">↗</span></a>
        </header>
        <?php if (!$posts): ?>
            <p class="hp-ig__empty">Discover our latest bakes and everyday moments on Instagram.</p>
            <?php if (current_user_can('manage_options')): ?><p class="hp-ig__admin">Connect Instagram or import saved posts in Settings → Honey Pearls Instagram.</p><?php endif; ?>
        <?php else: ?>
            <div id="<?php echo esc_attr($id); ?>" class="hp-ig__posts hp-ig__posts--<?php echo esc_attr($layout); ?>" tabindex="0" aria-label="Instagram posts">
            <?php foreach ($posts as $post): ?>
                <article class="hp-ig__card">
                    <a class="hp-ig__photo" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer"
                        data-hp-image="<?php echo esc_url($post['image']); ?>" data-hp-caption="<?php echo esc_attr($post['caption']); ?>">
                        <img src="<?php echo esc_url($post['image']); ?>" alt="<?php echo esc_attr($post['caption'] ? wp_trim_words($post['caption'], 18) : 'Instagram post by @' . $s['username']); ?>" loading="lazy" decoding="async" width="640" height="640">
                        <span class="hp-ig__badge" aria-hidden="true"><?php echo $post['type'] === 'VIDEO' ? '▶' : ($post['type'] === 'CAROUSEL_ALBUM' ? '▧' : '↗'); ?></span>
                        <span class="hp-ig__overlay">View post</span>
                    </a>
                    <?php if ($s['captions']): ?><div class="hp-ig__body"><p><?php echo esc_html($post['caption']); ?></p>
                    <?php if ($post['timestamp']): ?><time datetime="<?php echo esc_attr($post['timestamp']); ?>"><?php echo esc_html(wp_date('j M Y', strtotime($post['timestamp']))); ?></time><?php endif; ?></div><?php endif; ?>
                </article>
            <?php endforeach; ?>
            </div>
            <?php if ($layout === 'carousel'): ?><div class="hp-ig__controls"><button type="button" data-hp-prev aria-controls="<?php echo esc_attr($id); ?>" aria-label="Previous posts">←</button><span>Made with love. Shared with you.</span><button type="button" data-hp-next aria-controls="<?php echo esc_attr($id); ?>" aria-label="Next posts">→</button></div><?php endif; ?>
        <?php endif; ?>
        <dialog class="hp-ig__dialog" aria-label="Instagram post">
            <button type="button" class="hp-ig__close" aria-label="Close post">×</button>
            <img class="hp-ig__detail-image" alt=""><div class="hp-ig__detail"><p></p><a target="_blank" rel="noopener noreferrer">View on Instagram ↗</a></div>
        </dialog>
    </section>
    <?php
    return (string) ob_get_clean();
}
