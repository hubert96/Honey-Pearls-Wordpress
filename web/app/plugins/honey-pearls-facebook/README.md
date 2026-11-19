# Honey Pearls Facebook

Use `[honey_pearls_facebook]` in Elementor. Two posts per desktop row, one per mobile scroll-snap viewport, no navigation bar. Cards have no outer padding; photographs fill their width with object-fit cover.

The two configured posts use a saved public-data snapshot (`cards.json`, captured 19 November 2026), including actual Facebook comment excerpts. Counts are snapshots, not live API data. Like/Comment/Share links open Facebook and do not simulate reactions. Update snapshots in cards.json; new post links without a matching card use Meta's official embed fallback. Automatic discovery and refresh require a separate authorized Page API integration; the Instagram connection does not provide it.

Facebook reaction SVGs and comment/share sprites were observed on the live Facebook post UI. Profile image is site-owned. Post photographs reuse the matching local WordPress media already imported for Instagram. No private commenter images or Facebook credentials are stored.

Public source posts: the `url` fields in cards.json. The first post had no visible comments in the live Facebook dialog; the second displayed 21 reactions, 3 comments and 5 shares. Two public comment excerpts are displayed, not fabricated examples.
