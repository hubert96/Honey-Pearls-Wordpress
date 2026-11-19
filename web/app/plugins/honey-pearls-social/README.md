# Honey Pearls Social

One WordPress plugin and administration menu for Instagram, Facebook and Google/Tripadvisor reviews. Requires PHP 8.1 or newer.

## Existing site migration

Activate `honey-pearls-social/honey-pearls-social.php`, then deactivate the three legacy Honey Pearls loaders. The loaders require the bundle once, so deployment and activation order cannot register duplicate functions or hooks. Their retained static files keep cached asset URLs working. Do not activate the legacy loaders separately or delete their folders through WordPress until they have been retired from the deployment.

The bundle is self-contained under this directory. All existing `hp_ig_*`, `hp_fb_*` options, encrypted credentials, cached posts, uploads and shortcodes are preserved. No database migration or token re-encryption is performed. Deactivation clears scheduled social sync jobs and retains data. Reactivation restores scheduling.

## Elementor

- `[honey_pearls_instagram height="650"]`
- `[honey_pearls_reviews height="650"]`
- `[honey_pearls_facebook]`

Manage all modules from **Honey Pearls Social**. Existing Settings URLs and registered Meta admin-post callbacks remain valid.

Instagram refreshes hourly. Facebook refreshes every 15 minutes after connecting a Facebook account that has access to the Honey Pearls Page, with `pages_show_list`, `pages_read_engagement` and `pages_read_user_content`. The plugin only reads posts and comments. Facebook credentials are encrypted at rest; API failures preserve the last successful feed. Without authorization, previously captured previews remain visible and are not live data.

Reviews continue to use the existing free Google and Tripadvisor source connectors; keep those two third-party plugins active. Their refresh schedules and download limits apply.

## Validation

Run `php tests/instagram.php`, `php tests/reviews.php`, `php tests/facebook.php`, `php tests/facebook-api.php`, `php tests/social.php` and `php tests/social.php legacy-first` from the repository root. No build step is needed for the bundled CSS and JavaScript; production deployment builds the site Docker image.
