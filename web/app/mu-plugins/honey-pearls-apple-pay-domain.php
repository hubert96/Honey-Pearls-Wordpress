<?php
/**
 * Plugin Name: Honey Pearls Apple Pay Domain Verification
 * Description: Serves Square's public association file when the request reaches WordPress.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'muplugins_loaded', static function () {
	$method = $_SERVER['REQUEST_METHOD'] ?? '';
	$path   = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$name   = 'apple-developer-merchantid-domain-association';

	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || '/.well-known/' . $name !== $path ) {
		return;
	}

	$file = WP_PLUGIN_DIR . '/woocommerce-square/' . $name;

	if ( ! is_readable( $file ) ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Length: ' . filesize( $file ) );

	if ( 'GET' === $method ) {
		readfile( $file );
	}

	exit;
} );
