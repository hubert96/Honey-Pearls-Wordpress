# Honey Pearls Facebook

Use `[honey_pearls_facebook]` in Elementor. Two posts per desktop row, one per mobile scroll-snap viewport, no navigation bar. Cards have no outer padding; photographs fill their width with object-fit cover.

The two configured posts use a saved public-data snapshot (`cards.json`, captured 19 November 2026), including Facebook comment excerpts. Snapshot counts reflect the capture date. Like, Comment and Share open Facebook. Update snapshots in `cards.json`. Posts without a matching card link directly to Facebook. Automatic discovery and refresh require a separately authorised Facebook Page API integration.

Facebook reaction SVGs and comment/share sprites were observed on the live Facebook post UI. Profile image is site-owned. Post photographs reuse the matching local WordPress media already imported for Instagram. No private commenter images or Facebook credentials are stored.

Public source posts: the `url` fields in cards.json. The first post had no visible comments in the live Facebook dialog; the second displayed 21 reactions, 3 comments and 5 shares. Two public comment excerpts are displayed, not fabricated examples.

## Facebook Page API

Settings → Honey Pearls Facebook provides OAuth connection, manual sync, disconnect and login configuration ID. Reuses the Meta app ID and encrypted app secret already configured for Instagram, but stores a separate encrypted Page token. Requests pages_show_list, pages_read_engagement and pages_read_user_content. Register the displayed redirect URI in Meta. Configure a User access token flow when using Facebook Login for Business.

On connection, resolves exactly one selected Honey Pearls Page. Fetches its 12 newest published posts, reaction totals, comment totals, shares and up to two visible comment previews. WP cron refreshes every 15 minutes (depends on cron execution). Errors keep the last successful feed and appear in admin. Tokens are encrypted with AES-256-GCM, excluded from frontend/status, and deleted on disconnect. OAuth state is user-bound, single-use and expires after 10 minutes.
