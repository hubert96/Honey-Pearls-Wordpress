<?php
get_header(); ?>
<?php
/**
 * The template for displaying singular post-types: posts and user-defined custom post types.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $BACKERI_STORAGE;
$secondary_image	=	'';

if ( is_singular( 'product' ) ) {
	$page_layout		=	get_theme_mod( 'product_single_layout', $BACKERI_STORAGE['product_single_layout'] );
	$secondary_image	= get_post_meta(get_the_ID(), 'awaiken_secondary_image', true);
	if(empty($secondary_image)) {
	$background_image 	= get_theme_mod( 'shop_page_header_background_image', $BACKERI_STORAGE['shop_page_header_background_image'] );
	if($background_image) {
			$background_image 	= 	wp_get_attachment_image_src( $background_image , 'full' );
			if(isset($background_image[0])) {
				$secondary_image	=	$background_image[0];
			}
		}
	}
}
else{
	$page_layout		=	get_theme_mod( 'shop_page_layout', $BACKERI_STORAGE['shop_page_layout'] );
	$background_image 	= 	get_theme_mod( 'shop_page_header_background_image', $BACKERI_STORAGE['shop_page_header_background_image']);
	if($background_image){
		$background_image 	= 	wp_get_attachment_image_src( $background_image , 'full' );
		if(isset($background_image[0])) {
			$secondary_image	=	$background_image[0];
		}
	}
}

if($page_layout == 'full-width'){
	$column = 'col-md-12';
}
else{
	$column = 'col-lg-9 col-md-12';
}

?>
<main id="content" <?php post_class( 'site-main' ); ?>>
	<div class="page-header bg-section" <?php if($secondary_image) { ?> style="background-image: url('<?php echo esc_url($secondary_image); ?>')" <?php } ?>>
		<div class="container">
			<div class="row align-items-center">
				<div class="col-md-12">
					<div class="page-header-box">
						<?php 
						if ( is_singular( 'product' ) ) {
							the_title( '<h1 class="product_title entry-title">', '</h1>' );
						}
						else {
						?>
						<h1 class="page-title"><?php woocommerce_page_title(); ?></h1>
						<?php
						}
						?>
						<?php do_action('backeri_action_get_breadcrumb'); ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="page-single-post bg-section <?php  echo esc_attr( $page_layout ); ?>">
		<div class="container">
			<div class="row">
				<?php 
					if($page_layout == 'left-sidebar'):
						get_sidebar('shop');
					endif;
				?>
				<div class="<?php echo esc_attr( $column ); ?>">
					<?php
						if ( have_posts() ) :
							woocommerce_content();
						else:
							echo '<div class="product-not-found"><p>No products match your filter criteria. Please try a different filter.</p></div>';
						endif; // End the loop.
					?>
				</div>
				<?php 
					if($page_layout == 'right-sidebar'):
						get_sidebar('shop');
					endif;
				?>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();