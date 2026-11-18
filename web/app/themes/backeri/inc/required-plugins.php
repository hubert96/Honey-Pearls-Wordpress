<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This file represents an example of the code that themes would use to register
 * the required plugins.
 *
 * It is expected that theme authors would copy and paste this code into their
 * functions.php file, and amend to suit.
 *
 * @see http://tgmpluginactivation.com/configuration/ for detailed documentation.
 *
 * @package    TGM-Plugin-Activation
 * @subpackage Example
 * @version    2.6.1 for parent theme Backeri for publication on ThemeForest
 * @author     Thomas Griffin, Gary Jones, Juliette Reinders Folmer
 * @copyright  Copyright (c) 2011, Thomas Griffin
 * @license    http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link       https://github.com/TGMPA/TGM-Plugin-Activation
 */

/**
 * Include the TGM_Plugin_Activation class.
 *
 * Depending on your implementation, you may want to change the include call:
 *
 * Parent Theme:
 * require_once get_template_directory() . '/path/to/class-tgm-plugin-activation.php';
 *
 * Child Theme:
 * require_once get_stylesheet_directory() . '/path/to/class-tgm-plugin-activation.php';
 *
 * Plugin:
 * require_once dirname( __FILE__ ) . '/path/to/class-tgm-plugin-activation.php';
 */
 
define( 'AWAIKEN_PLUGINS_API_URI', 'https://awaikenthemes.com/wp-json/update-assets/v1/plugins/' );
function backeri_get_plugin_versions() {
	
	// "_v2": the cached format changed (shopengine-pro key), so do not reuse older cached values.
	$transient_key = AWAIKEN_THEME_SLUG.'_plugin_versions_api_v2_' . md5( AWAIKEN_PLUGINS_API_URI );
	$cached_response = get_transient( $transient_key );
	
	if ( $cached_response !== false ) {
        return $cached_response;
    }

	// A recent call failed: do not retry on every request (this runs on every read of the update_plugins transient).
	$fail_key = $transient_key . '_fail';
	if ( get_transient( $fail_key ) ) {
		return null;
	}

	// Get the JSON data from the specified URL using WordPress HTTP API
	$license = trim( get_option( AWAIKEN_THEME_SLUG . '_license_key' ) );
	$status  = get_option( AWAIKEN_THEME_SLUG . '_license_key_status', false );
	
	if ( empty($license) ) return null;
	if ( 'valid' !== $status ) return null;
	
	$api_params = array(
				'at_action'   => 'update_plugin',
				'url'         => home_url(),
				'license'     => $license,
				'item_id'     => AWAIKEN_ITEM_ID,
				'api_version' => 2, // Secure plugin downloads (see inc/plugin-updates.php)
			);
	
	$verify_ssl = (bool) apply_filters( 'awaiken_sl_api_request_verify_ssl', true );
	$response   = wp_remote_post(
					AWAIKEN_PLUGINS_API_URI,
					array(
						'timeout'   => 8,
						'sslverify' => $verify_ssl,
						'body'      => $api_params,
					)
				);
				
	// Check for a successful response
    if ( !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $json_contents = wp_remote_retrieve_body( $response );

        // Decode the JSON data
        $data = json_decode($json_contents, true);

        // Check if JSON decoding was successful and data structure is as expected
        if ( $data !== null && isset( $data['data'] ) ) {
			$return = [];

			if ( ! empty( $data['data']['elementskit'] ) ) {
				$return['elementskit'] = $data['data']['elementskit'];
			}

			if ( ! empty( $data['data']['shopengine-pro'] ) ) {
				$return['shopengine-pro'] = $data['data']['shopengine-pro'];
			}

			// An empty list means the server has nothing to offer (e.g. no zip stored yet): treat it as a failure.
			if ( ! empty( $return ) ) {
				set_transient( $transient_key, $return, 12 * HOUR_IN_SECONDS );

				return $return;
			}
        }
    }

	// The API failed or did not return versions. Remember that for a few minutes.
	set_transient( $fail_key, 1, 10 * MINUTE_IN_SECONDS );
	return null;
}
require_once BACKERI_THEME_DIR . '/inc/class-tgm-plugin-activation.php';

add_action( 'tgmpa_register', 'backeri_register_required_plugins' );

/**
 * Register the required plugins for this theme.
 *
 * In this example, we register five plugins:
 * - one included with the TGMPA library
 * - two from an external source, one from an arbitrary source, one from a GitHub repository
 * - two from the .org repo, where one demonstrates the use of the `is_callable` argument
 *
 * The variables passed to the `tgmpa()` function should be:
 * - an array of plugin arrays;
 * - optionally a configuration array.
 * If you are not changing anything in the configuration array, you can remove the array and remove the
 * variable from the function call: `tgmpa( $plugins );`.
 * In that case, the TGMPA default settings will be used.
 *
 * This function is hooked into `tgmpa_register`, which is fired on the WP `init` action on priority 10.
 */
function backeri_register_required_plugins() {
	/*
	 * Array of plugin arrays. Required keys are name and slug.
	 * If the source is NOT from the .org repo, then source is also required.
	 */
	 
	$plugin_versions = backeri_get_plugin_versions();
	
	// Bundled zips, used when the license server has nothing to offer (no valid license, API down).
	$elementskit_version 		= '4.10.7';
	$elementskit_source_url 	= get_template_directory() . '/assets/plugins/elementskit-' . $elementskit_version . '.zip';

	// Licensed: install through the license-checked download (see inc/plugin-updates.php).
	if ( ! empty( $plugin_versions['elementskit'] ) && function_exists( 'backeri_plugin_package_url' ) ) {
		$elementskit_version 		= $plugin_versions['elementskit'];
		$elementskit_source_url 	= backeri_plugin_package_url( 'elementskit' );
	}

	$plugins = array(
		array(
			'name' 				=> esc_html__('Elementor', 'backeri'),
			'slug' 				=> 'elementor',
			'required' 			=> true,
			'external_url' 		=> 'https://wordpress.org/plugins/elementor/',
		),
		array(
			'name' 				=> esc_html__('ElementsKit Lite', 'backeri'),
			'slug' 				=> 'elementskit-lite',
			'required' 			=> true,
			'external_url' 		=> 'https://wordpress.org/plugins/elementskit-lite/',
		),
		array(
			'name' 				=> esc_html__('ElementsKit Pro', 'backeri'),
			'slug' 				=> 'elementskit',
			'source' 			=> $elementskit_source_url,
			'required' 			=> true,
			'version' 			=> $elementskit_version,
			'external_url' 		=> 'https://wpmet.com/plugin/elementskit/',
			),
		array(
			'name'              => esc_html__('Backeri Theme Addons', 'backeri'), 
			'slug'              => 'backeri-theme-addons',
			'source'            => get_template_directory() . '/assets/plugins/backeri-theme-addons.zip', 
			'version'			=> '1.0.1',
			'required'          => true, 
		),
		array(
			'name'      		=>  esc_html__('WooCommerce', 'backeri'),
			'slug'      		=> 'woocommerce',
			'required'  		=> true,
		),
		array(
			'name'      		=> esc_html__('Contact Form 7', 'backeri'),
			'slug'      		=> 'contact-form-7',
			'required'  		=> true,
		)
	);
	
	if( get_option( 'backeri_demo_imported' ) !== '1' ) { 
		$plugins[] = array(
			'name'     			=> esc_html__('One Click Demo Import', 'backeri') ,
			'slug'      		=> 'one-click-demo-import',
			'required'  		=> false,
		);
	}

	/*
	 * Array of configuration settings. Amend each line as needed.
	 *
	 * TGMPA will start providing localized text strings soon. If you already have translations of our standard
	 * strings available, please help us make TGMPA even better by giving us access to these translations or by
	 * sending in a pull-request with .po file(s) with the translations.
	 *
	 * Only uncomment the strings in the config array if you want to customize the strings.
	 */
	$config = array(
		'id'           => 'backeri',                 // Unique ID for hashing notices for multiple instances of TGMPA.
		'default_path' => '',                      // Default absolute path to bundled plugins.
		'menu'         => 'tgmpa-install-plugins', // Menu slug.
		'has_notices'  => true,                    // Show admin notices or not.
		'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
		'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
		'is_automatic' => false,                   // Automatically activate plugins after installation or not.
		'message'      => '',                      // Message to output right before the plugins table.
	);

	tgmpa( $plugins, $config );
}
