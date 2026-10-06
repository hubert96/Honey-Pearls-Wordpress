<?php
/** Temporary production deployment verification marker. */
add_action('wp_footer', static function (): void {
    echo '<footer id="deploy-check" style="position:fixed;bottom:0;left:0;right:0;z-index:2147483647;text-align:center;background:#fff;color:#333;font:12px monospace;padding:4px">DEPLOY-CHECK-20261006-HONEY</footer>';
});
