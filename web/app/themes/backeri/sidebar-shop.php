<?php
/**
 * The template for displaying sidebar for shop page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( is_singular( 'product' ) ) {
?>
<div class="col-lg-3 col-md-12">
	<div class="sidebar-widget">
		<?php if ( is_active_sidebar( 'wc-single-sidebar' )  ) : ?>
			<?php dynamic_sidebar( 'wc-single-sidebar' ); ?>
		<?php endif; ?>
	</div>
</div>
<?php 
}
else{
?>
<div class="col-lg-3 col-md-12">
	<div class="sidebar-widget">
		<?php if ( is_active_sidebar( 'wc-sidebar' )  ) : ?>
			<?php dynamic_sidebar( 'wc-sidebar' ); ?>
		<?php endif; ?>
	</div>
</div>
<?php 
}
?>
