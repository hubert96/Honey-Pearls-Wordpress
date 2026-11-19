# Honey Pearls Facebook

Shortcode: `[honey_pearls_facebook]`. Settings → Honey Pearls Facebook accepts up to 12 public post permalinks and a viewport height. A `url` shortcode attribute embeds one post. Paste into an Elementor Shortcode widget.

The post itself is Meta's official Embedded Posts iframe. No recreated icons, fabricated counts, access tokens, scraping, or paid feed service. Appearance can differ from Facebook's app and varies with Meta and viewer locale/login. Only the surrounding pale-blue rounded container belongs to this plugin.

Posts are selected manually, not automatically discovered. Facebook's Page timeline embed was tested but failed to load its timeline; it is not used as a silent fallback. A future automatic feed requires a separate authorized Page API integration.

The carousel is manual, keyboard accessible, supports reduced motion, and respects narrow containers. Below Meta's 350px post width it scales the iframe without clipping horizontally. Tall posts may scroll inside the configured viewport. A permanent original-post link remains available if Meta is blocked or requires login/cookies. Facebook receives ordinary embed requests; the site does not store Facebook login credentials.
