<?php 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once BACKERI_THEME_DIR . '/inc/functions.php';
require_once BACKERI_THEME_DIR . '/inc/compatibility/elementor/elementor.php';
require_once BACKERI_THEME_DIR . '/inc/compatibility/elementor/modify-default-control.php';
require_once BACKERI_THEME_DIR . '/inc/compatibility/elementskit-lite.php';
if ( class_exists( 'WooCommerce' ) ) {
	require_once BACKERI_THEME_DIR . '/inc/compatibility/woocommerce.php';
} 
require_once BACKERI_THEME_DIR . '/inc/breadcrumbs.php';
require_once BACKERI_THEME_DIR . '/inc/customizer/customizer.php';


if(is_admin()) {
	// Admin related functions
	require_once BACKERI_THEME_DIR . '/inc/admin/admin.php';
	if(backeri_license_valid()){ 
		require_once BACKERI_THEME_DIR . '/inc/ocdi.php';
		require_once BACKERI_THEME_DIR . '/inc/required-plugins.php';
	}
	
	//Load theme updater functions
	function backeri_theme_updater() {
		$theme_updater = apply_filters( 'backeri_theme_updater_enabled', true );
		if($theme_updater) {
			require_once BACKERI_THEME_DIR . '/inc/updater/theme-updater.php';
		}
	}
	add_action( 'after_setup_theme', 'backeri_theme_updater' );
	
}
