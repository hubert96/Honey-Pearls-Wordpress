<?php

defined( 'ABSPATH' ) || exit;

function backeri_child_theme_enqueue_styles() {
	wp_enqueue_style( 'backeri-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'backeri-style' ), BACKERI_THEME_VERSION ); 
}
add_action( 'wp_enqueue_scripts', 'backeri_child_theme_enqueue_styles', 999 );
