<?php
namespace HoneyPearls\Instagram;
defined('ABSPATH') || exit;

function admin_url(): string { return \admin_url('options-general.php?page=hp-instagram'); }
function callback_url(): string { return \admin_url('admin-post.php?action=hp_instagram_callback'); }
function guard(): void
{
    if (!current_user_can('manage_options')) { wp_die('Administrator access required.', '', ['response' => 403]); }
}
function finish(): void { wp_safe_redirect(admin_url()); exit; }

function admin_menu(): void
{
    add_options_page('Honey Pearls Instagram', 'Honey Pearls Instagram', 'manage_options', 'hp-instagram', __NAMESPACE__ . '\\admin_page');
}

function action_form(string $command, string $label): void
{
    echo '<form method="post" action="' . esc_url(\admin_url('admin-post.php')) . '" class="hp-ig-admin-action">';
    wp_nonce_field('hp_instagram');
    echo '<input type="hidden" name="action" value="hp_instagram"><input type="hidden" name="command" value="' . esc_attr($command) . '">';
    submit_button($label, 'secondary', 'submit', false);
    echo '</form>';
}

function admin_action(): void
{
    guard();
    check_admin_referer('hp_instagram');
    $command = sanitize_key($_POST['command'] ?? '');
    try {
        if ($command === 'save') {
            $s = settings();
            $s['username'] = preg_replace('/[^a-zA-Z0-9_.]/', '', wp_unslash($_POST['username'] ?? $s['username'])) ?: $s['username'];
            $s['title'] = sanitize_text_field(wp_unslash($_POST['title'] ?? $s['title']));
            $s['layout'] = ($_POST['layout'] ?? '') === 'grid' ? 'grid' : 'carousel';
            $s['columns'] = max(2, min(6, (int) ($_POST['columns'] ?? 4)));
            $s['limit'] = max(1, min(50, (int) ($_POST['limit'] ?? 12)));
            $s['accent'] = sanitize_hex_color($_POST['accent'] ?? '') ?: '#976d45';
            $s['captions'] = isset($_POST['captions']);
            $s['replace_legacy'] = isset($_POST['replace_legacy']);
            $s['app_id'] = preg_replace('/[^0-9]/', '', (string) ($_POST['app_id'] ?? ''));
            update_option('hp_ig_settings', $s, false);
            $value = trim(wp_unslash($_POST['app_secret'] ?? ''));
            if ($value !== '') { update_option('hp_ig_app_secret', seal($value), false); }
            set_status('Settings saved.');
        } elseif ($command === 'sync') {
            if (empty(get_option('hp_ig_auth', [])['token'])) { set_status('Connect Instagram before synchronizing.'); }
            else { sync(); }
        } elseif ($command === 'import') {
            import_legacy();
        } elseif ($command === 'toggle') {
            $s = settings();
            $id = sanitize_text_field(wp_unslash($_POST['post_id'] ?? ''));
            $s['hidden'] = in_array($id, $s['hidden'], true) ? array_values(array_diff($s['hidden'], [$id])) : array_merge($s['hidden'], [$id]);
            update_option('hp_ig_settings', $s, false);
        } elseif ($command === 'disconnect') {
            delete_option('hp_ig_auth');
            delete_user_meta(get_current_user_id(), 'hp_ig_oauth');
            set_status('Automatic sync disconnected. Cached photos are still available.');
        } elseif ($command === 'connect') {
            $s = settings();
            if (!$s['app_id'] || !secret()) { throw new \RuntimeException('Save the Instagram App ID and App Secret first.'); }
            $state = bin2hex(random_bytes(32));
            update_user_meta(get_current_user_id(), 'hp_ig_oauth', ['hash' => hash('sha256', $state), 'expires' => time() + 600]);
            $url = add_query_arg(['client_id' => $s['app_id'], 'redirect_uri' => callback_url(), 'response_type' => 'code', 'scope' => 'instagram_business_basic,instagram_business_manage_comments', 'state' => $state, 'force_reauth' => 'true'], 'https://www.instagram.com/oauth/authorize');
            wp_redirect($url); exit;
        }
    } catch (\Throwable $e) { set_status($e->getMessage()); }
    finish();
}

function oauth_callback(): void
{
    guard();
    $pending = (array) get_user_meta(get_current_user_id(), 'hp_ig_oauth', true);
    delete_user_meta(get_current_user_id(), 'hp_ig_oauth');
    $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
    if (!$state || ($pending['expires'] ?? 0) < time() || !hash_equals($pending['hash'] ?? '', hash('sha256', $state))) {
        set_status('The Instagram connection request expired. Please start again.'); finish();
    }
    try {
        $code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
        if (!$code || isset($_GET['error'])) { throw new \RuntimeException('Instagram connection was cancelled.'); }
        $r = response(wp_remote_post('https://api.instagram.com/oauth/access_token', ['timeout' => 20, 'redirection' => 0, 'body' => [
            'client_id' => settings()['app_id'], 'client_secret' => secret(), 'grant_type' => 'authorization_code', 'redirect_uri' => callback_url(), 'code' => $code,
        ]]));
        $short = $r['data'][0]['access_token'] ?? $r['access_token'] ?? '';
        if (!$short) { throw new \RuntimeException('Instagram did not return an access token.'); }
        $long = response(wp_remote_get(add_query_arg(['grant_type' => 'ig_exchange_token', 'client_secret' => secret(), 'access_token' => $short], 'https://graph.instagram.com/access_token'), ['timeout' => 20, 'redirection' => 0]));
        if (empty($long['access_token']) || empty($long['expires_in'])) { throw new \RuntimeException('Instagram did not return a long-lived access token.'); }
        $me = api('me', $long['access_token'], ['fields' => 'user_id,username']);
        if (empty($me['username']) || empty($me['user_id'])) { throw new \RuntimeException('Instagram account details could not be verified.'); }
        $existing = (array) get_option('hp_ig_auth', []);
        if (!empty($existing['user_id']) && $existing['user_id'] !== (string) $me['user_id']) {
            throw new \RuntimeException('A different Instagram account was selected. Reconnect the original account to keep this feed consistent.');
        }
        // Imported content must never be mixed with a different account.
        if (get_option('hp_ig_posts', []) && strcasecmp(settings()['username'], $me['username']) !== 0) {
            throw new \RuntimeException('This feed contains posts for a different username. Connect the matching account.');
        }
        update_option('hp_ig_auth', ['comments_requested' => true, 'token' => seal($long['access_token']), 'user_id' => (string) $me['user_id'], 'refreshed' => time(), 'expires' => time() + (int) $long['expires_in']], false);
        $s = settings(); $s['username'] = sanitize_text_field($me['username']); update_option('hp_ig_settings', $s, false);
        sync();
    } catch (\Throwable $e) { set_status($e->getMessage()); }
    finish();
}

function admin_page(): void
{
    guard(); assets();
    wp_enqueue_style('hp-instagram-admin', plugins_url('assets/admin.css', FILE), [], VERSION);
    $s = settings(); $auth = (array) get_option('hp_ig_auth', []); $status = (array) get_option('hp_ig_status', []);
    $posts = (array) get_option('hp_ig_posts', []);
    ?>
    <div class="wrap hp-ig-admin">
        <h1>Honey Pearls Instagram</h1>
        <p>Your Instagram feed, served from your website. No external widget subscription.</p>
        <?php if (!empty($status['message'])): ?><div class="notice notice-info"><p><?php echo esc_html($status['message']); ?></p></div><?php endif; ?>
        <div class="hp-ig-admin-panel">
            <h2>Connection</h2>
            <p><strong><?php echo empty($auth['token']) ? 'Not connected — automatic updates are not active.' : (($auth['expires'] ?? 0) <= time() ? 'Authorization expired — reconnect Instagram.' : 'Connected to @' . esc_html($s['username'])); ?></strong></p>
            <p><?php echo count($posts); ?> cached posts. Last successful sync: <?php echo esc_html(get_option('hp_ig_last_sync') ? wp_date('j M Y, H:i', get_option('hp_ig_last_sync')) : 'not yet'); ?>.</p>
            <?php if (!empty($auth['expires'])): ?><p>Token expires: <?php echo esc_html(wp_date('j M Y', $auth['expires'])); ?>. Renewed automatically during sync after seven days.</p><?php endif; ?>
            <?php action_form('connect', empty($auth['token']) ? 'Connect Instagram' : 'Reconnect Instagram'); action_form('sync', 'Sync now'); action_form('import', 'Import saved Trustindex posts'); if (!empty($auth['token'])) { action_form('disconnect', 'Disconnect automatic updates'); } ?>
            <p>Import copies available photos and captions only. It does not reuse Trustindex credentials or enable automatic updates.</p>
        </div>
        <form method="post" action="<?php echo esc_url(\admin_url('admin-post.php')); ?>" class="hp-ig-admin-panel">
            <?php wp_nonce_field('hp_instagram'); ?><input type="hidden" name="action" value="hp_instagram"><input type="hidden" name="command" value="save">
            <h2>Appearance</h2>
            <div class="hp-ig-admin-fields">
                <label>Instagram username<input name="username" value="<?php echo esc_attr($s['username']); ?>" required pattern="[A-Za-z0-9_.]+"></label>
                <label>Heading<input name="title" value="<?php echo esc_attr($s['title']); ?>"></label>
                <label>Layout<select name="layout"><option value="carousel" <?php selected($s['layout'], 'carousel'); ?>>Carousel</option><option value="grid" <?php selected($s['layout'], 'grid'); ?>>Grid</option></select></label>
                <label>Grid columns (carousel shows one post)<input type="number" name="columns" value="<?php echo (int) $s['columns']; ?>" min="2" max="6"></label>
                <label>Number of posts<input type="number" name="limit" value="<?php echo (int) $s['limit']; ?>" min="1" max="50"></label>
                <label>Accent colour<input type="color" name="accent" value="<?php echo esc_attr($s['accent']); ?>"></label>
            </div>
            <p><label><input type="checkbox" name="captions" <?php checked($s['captions']); ?>> Show captions and dates</label></p>
            <p><label><input type="checkbox" name="replace_legacy" <?php checked($s['replace_legacy']); ?>> Use this feed for the existing <code>[trustindex-feed-instagram]</code> shortcode</label></p>
            <details><summary>Meta application setup</summary>
                <p>Create an app with Instagram API with Instagram Login. Add your Business or Creator account as an app tester/admin. For this site's own account, Standard Access is sufficient.</p>
                <p>Register this exact OAuth redirect URI:</p><code><?php echo esc_html(callback_url()); ?></code>
                <div class="hp-ig-admin-fields"><label>Instagram App ID<input name="app_id" inputmode="numeric" value="<?php echo esc_attr($s['app_id']); ?>"></label>
                <label>Instagram App Secret<input type="password" name="app_secret" value="" autocomplete="new-password" placeholder="<?php echo secret() ? 'Saved — leave blank to keep' : 'Not configured'; ?>"></label></div>
                <p>Save these settings, then choose Connect Instagram. Enable instagram_business_basic and instagram_business_manage_comments in your Meta app. Reconnect existing accounts to enable comment previews. The widget only reads comments; likes and new comments are made on Instagram.</p>
                <p><a href="https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login/" target="_blank" rel="noopener noreferrer">Meta setup documentation ↗</a></p>
            </details>
            <?php submit_button('Save settings'); ?>
        </form>
        <div class="hp-ig-admin-panel"><h2>Preview</h2><p>Add <code>[honey_pearls_instagram]</code> in an Elementor Shortcode widget or the WordPress Shortcode block.</p><?php echo render(); ?></div>
        <?php if ($posts): ?><div class="hp-ig-admin-panel"><h2>Posts</h2><div class="hp-ig-admin-posts"><?php foreach ($posts as $p): ?>
            <div><img src="<?php echo esc_url($p['image']); ?>" alt="" loading="lazy" width="160" height="160"><p><?php echo esc_html(wp_trim_words($p['caption'], 16)); ?></p>
            <form method="post" action="<?php echo esc_url(\admin_url('admin-post.php')); ?>"><?php wp_nonce_field('hp_instagram'); ?><input type="hidden" name="action" value="hp_instagram"><input type="hidden" name="command" value="toggle"><input type="hidden" name="post_id" value="<?php echo esc_attr($p['id']); ?>"><button class="button"><?php echo in_array($p['id'], $s['hidden'], true) ? 'Show post' : 'Hide post'; ?></button></form></div>
        <?php endforeach; ?></div></div><?php endif; ?>
    </div>
    <?php
}
