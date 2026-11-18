# Honey Pearls Instagram

Independent WordPress plugin for one Instagram Business/Creator account. Implements
Instagram Login, hourly synchronization of the latest 50 media items, weekly renewal
of long-lived tokens, local image caching, a responsive carousel/grid, keyboard-accessible
post dialogs, per-post visibility and an optional legacy shortcode replacement.
Videos and albums show their cover image; the original post opens on Instagram.
No publishing, messaging, likes/comments management or third-party widget service.

## Setup

1. Activate **Honey Pearls Instagram**, then open **Settings → Honey Pearls Instagram**.
2. In your own Meta app, enable **Instagram API with Instagram Login**. Use a professional
   account belonging to an app admin/tester for Standard Access. Other accounts may require
   Meta review/Advanced Access. This plugin only requests `instagram_business_basic`.
3. Register the exact redirect URI shown in the settings screen.
4. Enter the **Instagram App ID** and **Instagram App Secret** directly into the settings
   form, save, then click **Connect Instagram** and authorize the account.
5. Check the preview and sync status. Insert `[honey_pearls_instagram]` in Elementor's
   Shortcode widget. Optional attributes: `layout="grid" limit="8"`.
6. To replace an existing `[trustindex-feed-instagram]`, enable the replacement checkbox
   after verifying the feed, then deactivate the previous Instagram plugin. The generic
   `[trustindex-feed widget-id="..."]` is intentionally not overridden because other
   platforms can use it.

**Import saved Trustindex posts** copies the site's cached post data and available photos,
not its credentials, code, styles or subscription features. Existing hidden post choices
are preserved. Import is bounded to 35 seconds and can be repeated. Expired remote image
URLs cannot be recovered by this importer; a new Instagram connection is required.

## Operations

- Images live under persistent `uploads/honey-pearls-instagram/`; include this directory
  in upload backups. The plugin never deletes these files on deactivation.
- Tokens/App Secret are encrypted with AES-256-GCM and the WordPress auth salt. Preserve
  salts when migrating; a salt change requires re-entering the secret and reconnecting.
- New images are cached in bounded batches. API failures preserve the previous feed;
  pending image downloads retry at the next sync. Sync errors are visible only to admins.
- WP-Cron runs on traffic. For reliable hourly refresh, configure a real cron invoking
  WordPress cron (or `wp cron event run --due-now`) when WP-Cron is disabled or traffic is low.
- Disconnect stops API access locally and retains cached photos. To revoke the app's
  authorization at Meta as well, remove it in the Instagram account's app settings.
- Styling is local; browser requests go to locally cached photos until someone follows
  an Instagram link. No tracking SDK or embedded Instagram iframe is loaded.
- Test with `php tests/instagram.php` from the repository root; this uses mocked HTTP,
  never production credentials. Complete a real OAuth/upload/sync test after connecting Meta.

Official API reference:
https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login/
