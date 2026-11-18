<?php
/**
 * Plugin Name: Honey Pearls Instagram
 * Description: Instagram feed with local image caching, automatic updates and an accessible gallery.
 * Version: 1.3.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Author: Honey Pearls
 */

namespace HoneyPearls\Instagram;

defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/feed.php';
require_once __DIR__ . '/includes/admin.php';

const VERSION = '1.3.0';
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

function icon(string $name): string
{
    // Geometry measured from the publicly rendered Instagram post UI, 2026-11-18.
    if (in_array($name, ['heart', 'comment', 'share', 'save', 'more'], true)) {
        static $svg = [];
        return $svg[$name] ??= (string) file_get_contents(__DIR__ . '/assets/' . $name . '.svg');
    }
    $paths = [
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'comment' => '<path d="M21 11.5a9 9 0 0 1-9.5 9L3 22l1.5-6.5A9 9 0 1 1 21 11.5Z"/>',
        'share' => '<path d="m22 2-8 20-4-8-8-4 20-8ZM10 14 22 2"/>',
        'save' => '<path d="M5 3h14v19l-7-5-7 5V3Z"/>',
        'camera' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r=".7" fill="currentColor"/>',
        'left' => '<path d="m14 5-7 7 7 7"/>', 'right' => '<path d="m10 5 7 7-7 7"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

function render($atts = []): string
{
    $s = settings();
    $atts = shortcode_atts(['layout' => $s['layout'], 'limit' => $s['limit'], 'height' => 'auto'], (array) $atts);
    $layout = $atts['layout'] === 'grid' ? 'grid' : 'carousel';
    $posts = array_values(array_filter((array) get_option('hp_ig_posts', []),
        fn ($p) => !in_array($p['id'], $s['hidden'], true)));
    $posts = array_slice($posts, 0, max(1, min(50, (int) $atts['limit'])));
    $height = $atts['height'] === 'auto' ? 'auto' : (string) max(280, min(1600, (int) $atts['height']));
    $profile = 'https://www.instagram.com/' . rawurlencode($s['username']) . '/';
    $id = wp_unique_id('hp-ig-');
    ob_start();
    ?>
    <section class="hp-ig hp-ig--<?php echo esc_attr($layout); ?>" data-hp-height="<?php echo esc_attr($height); ?>" aria-label="<?php echo esc_attr($s['title']); ?>" style="--hp-ig-columns:<?php echo (int) $s['columns']; ?>;--hp-ig-accent:<?php echo esc_attr($s['accent']); ?>">
        <header class="hp-ig__header">
            <a class="hp-ig__brand" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open Instagram profile"><img src="<?php echo esc_url(plugins_url('assets/instagram-logo.svg', FILE)); ?>" width="24" height="24" alt="Instagram"><span>Instagram</span></a>
            <a class="hp-ig__follow" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer">Follow</a>
        </header>
        <?php if (!$posts): ?>
            <p class="hp-ig__empty">Discover our latest bakes and everyday moments on Instagram.</p>
            <?php if (current_user_can('manage_options')): ?><p class="hp-ig__admin">Connect Instagram or import saved posts in Settings → Honey Pearls Instagram.</p><?php endif; ?>
        <?php else: ?>
            <div id="<?php echo esc_attr($id); ?>" class="hp-ig__posts hp-ig__posts--<?php echo esc_attr($layout); ?>" tabindex="0" aria-label="Instagram posts">
            <?php foreach ($posts as $index => $post): ?>
                <article class="hp-ig__card" aria-label="<?php echo esc_attr('Post ' . ($index + 1) . ' of ' . count($posts)); ?>">
                    <div class="hp-ig__profile"><a class="hp-ig__avatar" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open profile"><img src="<?php echo esc_url(plugins_url('assets/profile.jpg', FILE)); ?>" alt="Honey Pearls profile" width="32" height="32"></a><div><a class="hp-ig__username" href="<?php echo esc_url($profile); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($s['username']); ?></a><span class="hp-ig__location">Honey Pearls - Greek Patisserie &amp; Bakery</span></div><a class="hp-ig__more" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Open original post"><?php echo icon('more'); ?></a></div>
                    <a class="hp-ig__photo" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer"
                        data-hp-image="<?php echo esc_url($post['image']); ?>" data-hp-caption="<?php echo esc_attr($post['caption']); ?>">
                        <img src="<?php echo esc_url($post['image']); ?>" alt="<?php echo esc_attr($post['caption'] ? wp_trim_words($post['caption'], 18) : 'Instagram post by @' . $s['username']); ?>" loading="lazy" decoding="async" width="640" height="640">
                        <?php if ($post['type'] !== 'IMAGE'): ?><span class="hp-ig__badge" aria-hidden="true"><?php echo $post['type'] === 'VIDEO' ? '▶' : '▧'; ?></span><?php endif; ?>
                    </a>
                    <div class="hp-ig__actions"><?php foreach (['heart' => 'Like on Instagram', 'comment' => 'Comment on Instagram', 'share' => 'Open post on Instagram', 'save' => 'Save on Instagram'] as $symbol => $label): ?><a class="hp-ig__action hp-ig__action--<?php echo esc_attr($symbol); ?>" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($label); ?>"><?php echo icon($symbol); ?></a><?php endforeach; ?></div>
                    <div class="hp-ig__body" tabindex="0" role="region" aria-label="Post caption and comments">
                    <?php if (isset($post['like_count'])): ?><div class="hp-ig__likes"><?php echo esc_html(number_format((int) $post['like_count']) . ((int) $post['like_count'] === 1 ? ' like' : ' likes')); ?></div><?php endif; ?>
                    <?php if ($s['captions']): ?><p><strong><?php echo esc_html($s['username']); ?></strong> <?php echo esc_html($post['caption']); ?></p><?php endif; ?>
                    <a class="hp-ig__comments" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer"><?php echo isset($post['comments_count']) ? esc_html('View all ' . number_format((int) $post['comments_count']) . ((int) $post['comments_count'] === 1 ? ' comment on Instagram' : ' comments on Instagram')) : 'View post on Instagram'; ?></a>
                    <?php $comments = (time() - ($post['comments_checked'] ?? 0) < DAY_IN_SECONDS) ? array_slice($post['comments'] ?? [], 0, 5) : []; ?>
                    <?php if ($comments): ?><div class="hp-ig__comment-list" aria-label="Comment preview">
                        <?php foreach (array_slice($comments, 0, 2) as $comment): ?><div class="hp-ig__comment"><strong><?php echo esc_html($comment['username'] ?: 'Instagram user'); ?></strong> <?php echo esc_html($comment['text']); ?></div><?php endforeach; ?>
                        <?php if (count($comments) > 2): ?><details><summary>View more comments</summary><?php foreach (array_slice($comments, 2) as $comment): ?><div class="hp-ig__comment"><strong><?php echo esc_html($comment['username'] ?: 'Instagram user'); ?></strong> <?php echo esc_html($comment['text']); ?></div><?php endforeach; ?></details><?php endif; ?>
                    </div><?php endif; ?>
                    <a class="hp-ig__add-comment" href="<?php echo esc_url($post['permalink']); ?>" target="_blank" rel="noopener noreferrer">Add a comment on Instagram…</a>
                    <?php if ($post['timestamp']): ?><time datetime="<?php echo esc_attr($post['timestamp']); ?>"><?php echo esc_html(wp_date('j F', strtotime($post['timestamp']))); ?></time><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
            </div>
            <?php if ($layout === 'carousel' && count($posts) > 1): ?><div class="hp-ig__controls"><button type="button" data-hp-prev aria-controls="<?php echo esc_attr($id); ?>" aria-label="Previous post"><?php echo icon('left'); ?></button><div class="hp-ig__dots" aria-label="Choose post"><?php foreach ($posts as $index => $_): ?><button type="button" data-hp-dot="<?php echo (int) $index; ?>" aria-label="<?php echo esc_attr('Go to post ' . ($index + 1)); ?>" aria-controls="<?php echo esc_attr($id); ?>" <?php echo $index === 0 ? 'aria-current="true"' : ''; ?>></button><?php endforeach; ?></div><button type="button" data-hp-next aria-controls="<?php echo esc_attr($id); ?>" aria-label="Next post"><?php echo icon('right'); ?></button><button type="button" data-hp-play aria-label="Pause automatic slideshow">Ⅱ</button></div><span class="hp-ig__sr" data-hp-status aria-live="polite"></span><?php endif; ?>
        <?php endif; ?>
        <dialog class="hp-ig__dialog" aria-label="Instagram post">
            <button type="button" class="hp-ig__close" aria-label="Close post">×</button>
            <img class="hp-ig__detail-image" alt=""><div class="hp-ig__detail"><p></p><a target="_blank" rel="noopener noreferrer">View on Instagram ↗</a></div>
        </dialog>
    </section>
    <?php
    return (string) ob_get_clean();
}
