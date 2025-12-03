<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function backeri_admin_css() {
	wp_enqueue_style( 'theme-default-font-admin', backeri_slug_fonts_url(), array(), null );
	wp_enqueue_style( 'backeri-admin', BACKERI_THEME_URL . '/assets/css/admin.css', array(), BACKERI_THEME_VERSION );	
	
	$documentation_link = apply_filters('backeri_documentation_link', true);

    if ($documentation_link) {
		wp_enqueue_script( 'backeri-admin-js', BACKERI_THEME_URL . '/assets/js/admin.js', array( 'jquery' ), BACKERI_THEME_VERSION, true );    
    }
	
}

// Hook the custom_admin_css function to the admin_enqueue_scripts action.
add_action('admin_enqueue_scripts', 'backeri_admin_css', 11);

add_action('admin_menu', 'backeri_custom_appearance_submenu');

function backeri_custom_appearance_submenu() {
	
    $documentation_link = apply_filters('backeri_documentation_link', true);

    if (!$documentation_link) {
        return;
    }
	
    add_submenu_page(
        'themes.php', 
        __( 'Documentation', 'backeri' ), 
        __( 'Documentation', 'backeri' ), 
        'manage_options', 
        'custom_documentation_link', 
        '__return_null' 
    );
}