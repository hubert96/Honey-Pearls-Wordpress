# Honey Pearls Reviews

`[honey_pearls_reviews height="650"]` is a vertical carousel in a rounded site-style container, with scoped CSS and 40px desktop / 20px mobile padding. Version 1.1 replaces the unused Trustpilot adapter with Tripadvisor.

Keep Widgets for Google Reviews and WP Tripadvisor Review Widgets active. Connect each business in its free widget configurator. This plugin reads their review tables without modifying native widgets or bypassing subscription/download restrictions. Their manual refresh steps and free-plan limits still apply. No paid account widget or direct Tripadvisor Content API is used.

The connectors retain their own caches and visibility settings. The carousel reads all visible reviews, including low ratings, alternates sources and preserves full text. Each source retains its overall score/count, separate from the stored subset. No blended score, mock reviews or invented verification badges. Source links lead to business profiles because stored rows lack reliable individual review URLs.

Google uses yellow stars; Tripadvisor uses its green bubbles and official logo. These are custom cards, not pixel-identical platform interfaces. Logo: official brand guidelines, files.readme.io/9f59534-Vector_1.png. Bubble SVGs: Trustindex connector platform assets, cdn.trustindex.io/assets/platform/Tripadvisor/star/.

Eight-second vertical rotation; previous/next/pause. Hover, focus, touch, background tabs and offscreen placement pause rotation. Reduced-motion disables autoplay and animation. Long reviews scroll inside cards; without JavaScript all reviews remain readable.

Validation: php tests/reviews.php, PHP lint and node --check web/app/plugins/honey-pearls-reviews/reviews.js.
