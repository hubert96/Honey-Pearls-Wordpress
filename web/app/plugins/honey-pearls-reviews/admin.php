<?php
namespace HoneyPearls\Reviews;
defined('ABSPATH') || exit;
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'settings_page_hp-reviews') return;
    wp_enqueue_style('hp-reviews', plugins_url('reviews.css', __FILE__), [], VERSION);
    wp_enqueue_script('hp-reviews', plugins_url('reviews.js', __FILE__), [], VERSION, true);
    wp_add_inline_style('hp-reviews', '.wrap>.hpr{max-width:580px}');
});
add_action('admin_menu', function () { add_options_page('Honey Pearls Reviews', 'Honey Pearls Reviews', 'manage_options', 'hp-reviews', __NAMESPACE__ . '\\admin_page'); });
add_action('admin_post_hp_reviews_save', function () {
    if (!current_user_can('manage_options')) wp_die('Not allowed', '', ['response' => 403]);
    check_admin_referer('hp_reviews_save');
    $options = get_option('hp_reviews_settings', []);
    $unit = sanitize_text_field(wp_unslash($_POST['unit'] ?? ''));
    if ($unit !== '' && !preg_match('/^[a-f0-9]{24}$/i', $unit)) wp_die('Invalid Trustpilot business unit ID.');
    if (($options['unit'] ?? '') !== $unit) delete_option('hp_reviews_trustpilot');
    $options['unit'] = $unit;
    $key = trim(wp_unslash($_POST['key'] ?? ''));
    if ($key !== '') $options['key'] = crypt_key($key);
    if (!empty($_POST['disconnect'])) { $options = []; delete_option('hp_reviews_trustpilot'); delete_option('hp_reviews_error'); }
    update_option('hp_reviews_settings', $options, false);
    sync_trustpilot();
    wp_safe_redirect(admin_url('options-general.php?page=hp-reviews&saved=1')); exit;
});
function admin_page(): void {
    if (!current_user_can('manage_options')) return;
    $options = get_option('hp_reviews_settings', []);
    echo '<div class="wrap"><h1>Honey Pearls Reviews</h1><p>Shortcode: <code>[honey_pearls_reviews height="650"]</code>. Add it to an Elementor Shortcode widget.</p>';
    echo '<p>Google uses the existing Widgets for Google Reviews connector and its stored reviews. Keep that connector active and refresh reviews in its panel. No additional Google API key is needed.</p>';
    foreach (sources() as $source) echo '<p><strong>' . esc_html($source['name']) . '</strong>: ' . count($source['reviews']) . ' available reviews.</p>';
    if ($error = get_option('hp_reviews_error')) echo '<div class="notice notice-warning"><p>' . esc_html($error) . '</p></div>';
    echo '<h2>Trustpilot</h2><p>Optional second source. Requires your Trustpilot API key with access to the Business Units API. Enter credentials here, never in a shortcode. The key is encrypted and never sent to visitors.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="hp_reviews_save">';
    wp_nonce_field('hp_reviews_save');
    echo '<table class="form-table"><tr><th><label for="hpr-unit">Business unit ID</label></th><td><input id="hpr-unit" name="unit" class="regular-text" value="' . esc_attr($options['unit'] ?? '') . '"></td></tr><tr><th><label for="hpr-key">API key</label></th><td><input id="hpr-key" type="password" name="key" class="regular-text" autocomplete="new-password" value=""><p class="description">' . (!empty($options['key']) ? 'Key saved. Leave blank to keep it.' : 'No key saved.') . '</p></td></tr></table>';
    submit_button('Save and refresh');
    echo '<label><input type="checkbox" name="disconnect" value="1"> Disconnect Trustpilot and remove its cached reviews</label></form><hr><h2>Preview with your stored reviews</h2>' . render() . '</div>';
}
