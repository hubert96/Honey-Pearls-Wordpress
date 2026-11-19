# Honey Pearls Reviews

Use `[honey_pearls_reviews height="650"]` in an Elementor Shortcode widget. The component stays within its parent, with 40px desktop / 20px mobile padding and the site's rounded pale-blue shell. CSS is scoped to `.hpr` and does not change Elementor or existing widgets.

## Sources

- Google: reads the existing **Widgets for Google Reviews** connector's database, including visibility choices and original review text. Keep that plugin active. Its download schedule / manual refresh rules still apply; this plugin does not bypass them. The connector currently stores only a subset of reviews, while the summary is the business's overall score and count. Links lead to the Google Maps business profile because the stored data does not provide reliable individual review URLs.
- Trustpilot: optional Business Units API integration. Configure business unit ID and API key in Settings → Honey Pearls Reviews. API entitlement may require a Trustpilot plan. API keys are encrypted with the WordPress auth salt (AES-256-GCM), not exposed in HTML or JS. WP-Cron refreshes hourly when traffic runs cron. The first API page (up to 100 entries) is displayed, newest first; it is not a complete archive. Failed refreshes preserve the last successful result for up to seven days. Disconnect removes cached Trustpilot content. Changing WP salts requires entering the key again.

Each platform retains its own score/count. Trustpilot's score is a TrustScore, not an arithmetic average. No blended rating or fabricated reviews are generated. Available sources alternate; there is no rating threshold. Empty/unconfigured sources are omitted. No mock reviews appear on the public site.

The platform treatments use Google-style yellow stars and Trustpilot-style green star tiles, author, date, and source link. They are custom presentations, not official embedded widgets or pixel-identical platform UIs. Typography falls back to locally available sans-serif fonts.

## Interaction

Vertical slides every eight seconds; previous/next and pause buttons. Hover, keyboard focus, touch, hidden tabs and offscreen placement pause rotation. Reduced-motion preference disables autoplay and animated transitions. Long reviews scroll inside the card without clipping their text. Without JavaScript, all reviews are readable in a normal list. Multiple instances are isolated.

## Checks

`php tests/reviews.php` from the repository root. Run PHP lint and `node --check web/app/plugins/honey-pearls-reviews/reviews.js`.

Official references: https://developers.trustpilot.com/business-units-api/ and https://developers.google.com/maps/documentation/places/web-service/policies
