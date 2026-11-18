<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function backeri_admin_css() {
	wp_enqueue_style( 'theme-default-font-admin', backeri_slug_fonts_url(), array(), null );
	wp_enqueue_style( 'backeri-admin', BACKERI_THEME_URL . '/assets/css/admin.css', array(), BACKERI_THEME_VERSION );	
	
	}

// Hook the custom_admin_css function to the admin_enqueue_scripts action.
add_action('admin_enqueue_scripts', 'backeri_admin_css', 11);

/**
 * Elementor: per-admin dismissals (promo banner + admin notices).
 * Runs before Elementor builds its modules (init priority 0).
 */
function backeri_dismiss_elementor_promos() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$user_id = get_current_user_id();

	// "Elevate your site with Elementor Pro" banner.
	$intro = get_user_meta( $user_id, 'elementor_introduction', true );
	$intro = is_array( $intro ) ? $intro : [];

	if ( empty( $intro['conversion_banner_go_pro'] ) ) {
		$intro['conversion_banner_go_pro'] = true;
		update_user_meta( $user_id, 'elementor_introduction', $intro );
	}

	// Promo admin notices.
	$notices = get_user_meta( $user_id, 'elementor_admin_notices', true );
	$notices = is_array( $notices ) ? $notices : [];
	$changed = false;

	foreach ( [ 'rate_us_feedback', 'plugin_image_optimization', 'ally_pages_promotion', 'role_manager_promote' ] as $id ) {
		if ( empty( $notices[ $id ]['is_viewed'] ) ) {
			$notices[ $id ] = [ 'is_viewed' => true, 'meta' => [] ];
			$changed        = true;
		}
	}

	if ( $changed ) {
		update_user_meta( $user_id, 'elementor_admin_notices', $notices );
	}
}
add_action( 'init', 'backeri_dismiss_elementor_promos', -1 );

if ( apply_filters( 'at_show_dashboard', true ) ) {
	require_once BACKERI_THEME_DIR . '/inc/admin/class-dashboard.php';
}

