# Honey Pearls Instagram

Independent WordPress plugin for one Instagram Business/Creator account. Implements
Instagram Login, hourly synchronization of the latest 50 media items, weekly renewal
of long-lived tokens, local image caching, a responsive carousel/grid, keyboard-accessible
post dialogs, per-post visibility and an optional legacy shortcode replacement.
Videos and albums show their cover image; the original post opens on Instagram.
No publishing, messaging, likes/comments management or third-party widget service.

The carousel displays one Instagram-style post at a time, fits its parent container
(up to 540px), and slides with a 620ms eased animation. It advances every six seconds,
pausing on hover, keyboard focus, an open dialog, or a hidden/offscreen page. Users can
pause it, select a dot, swipe, or use arrow keys on the post track. Reduced-motion users
start with autoplay disabled and get immediate transitions. Desktop columns apply only
to the optional grid. Heart/comment/share/save icons link to the original Instagram post;
the widget never fabricates counts or pretends to perform social actions locally.

### Visual reference (2026-11-18)

Measured against the rendered mobile web post https://www.instagram.com/p/Dd8rdepIS3X/
in Chrome on Windows at a 524.8px content width. Matching values: the system font stack
(`-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif`),
14px/18px body text, 600-weight username, 12px/16px location/date, #0c1014 primary text,
#6a717a secondary text, 32px avatar, 70px profile row, 16px content inset, and 24px icons
inside 40px targets with 8px padding. Action SVG geometry follows the rendered reference.
Photos keep their original aspect ratio; carousel height follows the active post.

`assets/instagram-logo.svg` is the unmodified black glyph from Meta's official
`IG_brand_asset_pack_2023.zip`, downloaded from
https://www.meta.com/brand/resources/instagram/instagram-brand/ after the site owner's
explicit acceptance of its usage terms. The Instagram mark belongs to Meta; its brand
terms apply separately from this plugin's code license. The adjacent Instagram text is
ordinary interface text, not an imitation of the Instagram wordmark. `assets/profile.jpg`
is the Honey Pearls account's profile image captured for this site.

The branded widget header and carousel controls are additions outside the measured post.
Owner-only promotion tools, social counts and Instagram's global app navigation are not
replicated. Language, OS font rendering and Instagram UI experiments can alter appearance;
this is a comparison with the observed web version, not a claim of universal pixel identity.

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


### Likes and comment previews (1.2)
Media synchronization reads `like_count` and `comments_count`; absent counts remain unknown, never fabricated as zero. Reconnect with `instagram_business_manage_comments` to read comments. This permission must be enabled in the Meta application. The plugin performs no comment/like writes.

Each sync reads up to 5 comments per post, at most 12 posts, with a 35-second scheduling budget (an in-flight request may take another 20 seconds). Least recently checked posts go first. Two comments appear below the caption; up to three more expand in place. Hidden comments are discarded, previews older than 24 hours are not displayed, and successful empty responses remove obsolete comments. Comment API failures leave media synchronization working and show an administrator notice. Links open Instagram for the full discussion, liking and adding comments. API counts/order can differ for promoted posts; no promise of the exact native ranked comment order.

References: https://developers.facebook.com/documentation/instagram-platform/comment-moderation and https://developers.facebook.com/documentation/instagram-platform/instagram-graph-api/reference/ig-comment . Live API verification requires the account owner to connect Meta; automated tests use mocked API responses.


### Adaptive height (1.3)
Carousel modes switch automatically: full at 800 px and above, compact at 520–799 px, low with one single-line comment below the image at 280–519 px. Images use contain (no cropping). Full/compact discussions scroll inside a keyboard-focusable region; low mode shows one comment and links to the full Instagram discussion; controls stay visible and slide changes do not change the frame height. Grid layout remains content-sized.

Default height is viewport height minus 32 px, capped at 1000 px. For a page section use `[honey_pearls_instagram height="650"]` (pixels, clamped to 280–1600). Alternatively set `--hp-ig-height:650` on the widget/ancestor, or wrap it in an independently sized element with `data-hp-ig-container`; its content height is observed, including resize changes. Do not mark an auto-height ancestor as the sizing container (that would create circular sizing). Priority: CSS numeric variable, shortcode height, marked container, viewport. Space below 280 px requires a larger host.
