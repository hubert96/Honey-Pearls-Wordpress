<?php

defined( 'ABSPATH' ) || exit;

function backeri_child_theme_enqueue_styles() {
	wp_enqueue_style( 'backeri-child-style', get_stylesheet_directory_uri() . '/style.css', array( 'backeri-style' ), filemtime( get_stylesheet_directory() . '/style.css' ) );
}
add_action( 'wp_enqueue_scripts', 'backeri_child_theme_enqueue_styles', 999 );

add_action('elementor/frontend/container/before_render', function ($container) {
    // The header template reserves this column for the site identity.
    if ($container->get_id() === 'aa746ef') {
        printf('<a class="hp-mobile-brand" href="%s">%s</a>', esc_url(home_url('/')), esc_html(get_bloginfo('name')));
    }
});

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_cart() ) ) {
		return;
	}

	wp_enqueue_style( 'hp-checkout', get_stylesheet_directory_uri() . '/checkout.css', array( 'backeri-child-style' ), '1.0.4' );
}, 1000 );

/** Homepage layout belongs to the child theme, not the social plugins. */
add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) return;
    wp_enqueue_style('hp-home-layout', get_stylesheet_directory_uri() . '/home-layout-2.css', ['backeri-child-style'], '1.0.3');
    // Keep native scrolling and avoid character splitting/reveal masks on this page.
    wp_dequeue_script('lenis-theme');
    wp_dequeue_script('magiccursor-theme');
    wp_add_inline_script('theme-js', "document.querySelectorAll('.elementor-6541 [class]').forEach(function(el){ Array.from(el.classList).forEach(function(c){ if (/^at-animation-(heading|image)-style-/.test(c)) el.classList.remove(c); }); });", 'before');
}, 1001);
