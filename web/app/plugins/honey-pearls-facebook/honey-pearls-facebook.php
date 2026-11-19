<?php
/**
 * Plugin Name: Honey Pearls Facebook
 * Description: Genuine Facebook post embeds in a responsive Honey Pearls carousel.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Honey Pearls
 * License: GPL-2.0-or-later
 */
namespace HoneyPearls\Facebook;
defined('ABSPATH') || exit;
const VERSION = '1.0.0';
const FILE = __FILE__;
require_once __DIR__ . '/includes.php';
add_shortcode('honey_pearls_facebook', __NAMESPACE__ . '\\render');
add_action('admin_menu', function () { add_options_page('Honey Pearls Facebook', 'Honey Pearls Facebook', 'manage_options', 'hp-facebook', __NAMESPACE__ . '\\admin_page'); });
add_action('admin_init', function () {
    register_setting('hp_facebook', 'hp_fb_settings', ['type'=>'array', 'sanitize_callback'=>__NAMESPACE__ . '\\sanitize_settings', 'default'=>[]]);
});
function assets(): void {
    wp_enqueue_style('hp-facebook', plugins_url('facebook-1.css', FILE), [], VERSION);
    wp_enqueue_script('hp-facebook', plugins_url('facebook-1.js', FILE), [], VERSION, true);
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\assets');
add_action('admin_enqueue_scripts', function ($hook) { if ($hook === 'settings_page_hp-facebook') assets(); });
function settings(): array { return array_merge(['posts'=>[], 'height'=>740], (array)get_option('hp_fb_settings', [])); }
function sanitize_settings($input): array {
    $input=is_array($input) ? $input : [];
    $lines=is_array($input['posts'] ?? null) ? $input['posts'] : preg_split('/\R/', (string)($input['posts'] ?? ''));
    $posts=[];
    foreach ($lines as $line) {
        $url=post_url(trim((string)$line));
        if ($url) $posts[]=$url;
        elseif (trim((string)$line)!=='') add_settings_error('hp_fb_settings','invalid_post','Use a public Facebook post, photo or video permalink. Profile and share links are not post permalinks.');
    }
    return ['posts'=>array_slice(array_values(array_unique($posts)),0,12), 'height'=>max(400,min(1400,(int)($input['height'] ?? 740)))];
}
function render($atts=[]): string {
    $s=settings(); $atts=shortcode_atts(['url'=>'','height'=>$s['height']],$atts,'honey_pearls_facebook');
    $posts=$atts['url'] ? array_filter([post_url($atts['url'])]) : array_filter(array_map(__NAMESPACE__.'\\post_url',$s['posts']));
    if (!$posts) return current_user_can('manage_options') ? '<p>Honey Pearls Facebook: add public post links in Settings → Honey Pearls Facebook.</p>' : '';
    $height=max(400,min(1400,(int)$atts['height'])); assets(); ob_start(); ?>
    <section class="hp-fb" aria-label="Facebook posts" aria-roledescription="carousel" data-height="<?php echo $height; ?>">
      <div class="hp-fb-slides">
      <?php foreach(array_values($posts) as $i=>$url): ?>
        <article class="hp-fb-slide" aria-roledescription="slide" aria-label="<?php echo ($i+1).' of '.count($posts); ?>">
          <div class="hp-fb-stage"><iframe data-post="<?php echo esc_url($url); ?>" src="<?php echo esc_url(embed_url($url,500,$height)); ?>" width="500" height="<?php echo $height; ?>" title="<?php echo esc_attr('Facebook post '.($i+1)); ?>" loading="lazy" allow="encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
          <a class="hp-fb-original" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">Open post on Facebook ↗</a>
        </article>
      <?php endforeach; ?>
      </div>
      <?php if(count($posts)>1): ?><div class="hp-fb-nav" hidden><button type="button" data-step="-1" aria-label="Previous Facebook post">‹</button><span class="hp-fb-count" aria-live="polite" aria-atomic="true">1 / <?php echo count($posts); ?></span><button type="button" data-step="1" aria-label="Next Facebook post">›</button></div><?php endif; ?>
    </section>
    <?php return ob_get_clean();
}
function admin_page(): void {
    if (!current_user_can('manage_options')) return;
    $s=settings(); ?>
    <div class="wrap"><h1>Honey Pearls Facebook</h1>
      <p>Original Facebook embeds: typography, reactions, comments and sharing are rendered by Meta. This is not a CSS imitation.</p>
      <p>Only public posts can be embedded. Facebook may require a visitor to sign in, allow cookies, or turn off content blocking. Use the direct link if an embed is unavailable.</p>
      <?php settings_errors('hp_fb_settings'); ?>
      <form method="post" action="options.php"><?php settings_fields('hp_facebook'); ?>
      <table class="form-table"><tr><th><label for="hp-fb-posts">Public post links</label></th><td><textarea id="hp-fb-posts" name="hp_fb_settings[posts]" rows="9" class="large-text code"><?php echo esc_textarea(implode("\n",$s['posts'])); ?></textarea><p class="description">One permalink per line, up to 12 posts. Open the post's date on Facebook and copy its URL. Selected posts stay in this order; new posts are not imported automatically.</p></td></tr>
      <tr><th><label for="hp-fb-height">Post viewport height</label></th><td><input id="hp-fb-height" type="number" min="400" max="1400" name="hp_fb_settings[height]" value="<?php echo (int)$s['height']; ?>"> px</td></tr></table>
      <?php submit_button(); ?></form>
      <p>Elementor Shortcode widget: <code>[honey_pearls_facebook]</code></p>
      <p>Single post: <code>[honey_pearls_facebook url="https://www.facebook.com/your-page/posts/your-post" height="740"]</code></p>
      <h2>Live preview</h2><div style="max-width:580px"><?php echo render(); ?></div>
    </div><?php
}
