<?php
/**
 * Offers updates for the premium plugins bundled with the theme (ElementsKit Pro, ShopEngine Pro)
 * on the Plugins page, Dashboard > Updates and in bulk updates.
 *
 * Those plugins have their own license, so their own updater refuses to give WordPress a download
 * link. Here the theme reports the update itself and downloads the zip through our license-checked
 * API (signed, single-use link; the zips are not publicly accessible). Only loaded when the theme
 * license is valid (see inc/init.php).
 *
 * @package Backeri
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugins updated through the theme.
 *
 * Key is the plugin file (relative to the plugins folder).
 * - version_key: key in the response of backeri_get_plugin_versions(); no version, no update.
 * - slug: plugin slug; the download goes through our license-checked API (see backeri_secure_plugin_download).
 *
 * @return array
 */
function backeri_get_plugin_update_list() {
	return apply_filters(
		'backeri_plugin_update_list',
		array(
			'elementskit/elementskit.php'       => array(
				'slug'        => 'elementskit',
				'version_key' => 'elementskit',
				'url'         => 'https://wpmet.com/plugin/elementskit',
			),
		)
	);
}

/**
 * Placeholder package URL for a plugin. It is never downloaded directly: the upgrader_pre_download
 * hook below swaps it for a signed, single-use link from our server.
 *
 * @param string $slug Plugin slug.
 * @return string
 */
function backeri_plugin_package_url( $slug ) {
	return AWAIKEN_PLUGINS_API_URI . 'download/' . $slug;
}

/**
 * Downloads a plugin zip through our license-checked API instead of a public URL.
 * Used for updates (Plugins page, Dashboard > Updates, bulk) and for Appearance > Install Plugins.
 *
 * @param bool|string|WP_Error $reply   Filter value; false to let WordPress download normally.
 * @param string               $package Package URL.
 * @return bool|string|WP_Error Path of the downloaded temp file, or an error.
 */
function backeri_secure_plugin_download( $reply, $package ) {

	$prefix = backeri_plugin_package_url( '' );
	if ( ! is_string( $package ) || 0 !== strpos( $package, $prefix ) ) {
		return $reply;
	}

	$slug    = sanitize_key( substr( $package, strlen( $prefix ) ) );
	$license = trim( get_option( AWAIKEN_THEME_SLUG . '_license_key' ) );

	if ( ! $slug || ! $license ) {
		return new WP_Error( 'backeri_download', __( 'A valid theme license is required to download this plugin.', 'backeri' ) );
	}

	$response = wp_remote_post(
		AWAIKEN_PLUGINS_API_URI,
		array(
			'timeout'   => 15,
			'sslverify' => (bool) apply_filters( 'awaiken_sl_api_request_verify_ssl', true ),
			'body'      => array(
				'at_action' => 'get_plugin_download',
				'license'   => $license,
				'url'       => home_url(),
				'item_id'   => AWAIKEN_ITEM_ID,
				'plugin'    => $slug,
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'backeri_download', __( 'Could not reach the license server to download the plugin.', 'backeri' ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['success'] ) || empty( $data['download_url'] ) ) {
		return new WP_Error( 'backeri_download', __( 'Your theme license is not active for this site, so the plugin could not be updated.', 'backeri' ) );
	}

	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	return download_url( $data['download_url'] );
}
add_filter( 'upgrader_pre_download', 'backeri_secure_plugin_download', 10, 2 );

/**
 * Adds our update entries to the plugin updates WordPress reads, replacing the ones from the
 * plugins' own (license based) updaters.
 *
 * @param object|false $transient The update_plugins site transient.
 * @return object|false
 */
function backeri_inject_plugin_updates( $transient ) {

	if ( ! is_object( $transient ) || ! function_exists( 'backeri_get_plugin_versions' ) ) {
		return $transient;
	}

	// Only for sites with an active theme license (the same check the theme uses elsewhere).
	if ( ! trim( (string) get_option( AWAIKEN_THEME_SLUG . '_license_key' ) ) || 'valid' !== get_option( AWAIKEN_THEME_SLUG . '_license_key_status', false ) ) {
		return $transient;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$versions = backeri_get_plugin_versions();
	if ( empty( $versions ) || ! is_array( $versions ) ) {
		return $transient;
	}

	$installed = get_plugins();

	if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
		$transient->response = array();
	}
	if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
		$transient->no_update = array();
	}

	foreach ( backeri_get_plugin_update_list() as $plugin_file => $plugin ) {

		// Not installed, or no version published for it: nothing to do.
		if ( empty( $installed[ $plugin_file ] ) || empty( $versions[ $plugin['version_key'] ] ) ) {
			continue;
		}

		$new_version = sanitize_text_field( $versions[ $plugin['version_key'] ] );

		if ( ! version_compare( $installed[ $plugin_file ]['Version'], $new_version, '<' ) ) {
			continue;
		}

		$update              = new stdClass();
		$update->id          = $plugin['slug'];
		$update->slug        = $plugin['slug'];
		$update->plugin      = $plugin_file;
		$update->new_version = $new_version;
		$update->url         = $plugin['url'];
		// Placeholder only; the real, short-lived link is requested in backeri_secure_plugin_download().
		$update->package     = backeri_plugin_package_url( $plugin['slug'] );

		$transient->response[ $plugin_file ] = $update;
		unset( $transient->no_update[ $plugin_file ] );
	}

	return $transient;
}
// Late priority so ours replaces whatever the plugins' own updaters added.
add_filter( 'site_transient_update_plugins', 'backeri_inject_plugin_updates', 99 );

/**
 * No silent background updates for these plugins; customers update them from the Plugins page.
 *
 * @param bool|null $update Whether to update.
 * @param object    $item   Update offer.
 * @return bool|null
 */
function backeri_disable_plugin_auto_update( $update, $item ) {
	if ( isset( $item->plugin ) && isset( backeri_get_plugin_update_list()[ $item->plugin ] ) ) {
		return false;
	}
	return $update;
}
add_filter( 'auto_update_plugin', 'backeri_disable_plugin_auto_update', 99, 2 );
