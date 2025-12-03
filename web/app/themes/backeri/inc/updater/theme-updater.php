<?php
/**
 * Easy Digital Downloads Theme Updater
 *
 * @package EDD Sample Theme
 */

// Includes the files needed for the theme updater
if ( ! class_exists( 'AWAIKEN_Theme_Updater_Admin' ) ) {
	include dirname( __FILE__ ) . '/theme-updater-admin.php';
}

// Loads the updater classes
$updater = new AWAIKEN_Theme_Updater_Admin(
	// Config settings
	array(
		'remote_api_url' => 'https://awaikenthemes.com/', // Site where EDD is hosted
		'tf_pc_val_api_url' => 'https://awaikenthemes.com/wp-json/themeforest/v1/validate-purchase', // Site where EDD is hosted
		'item_name'      => AWAIKEN_ITEM_NAME, // Name of theme
		'theme_slug'     => AWAIKEN_THEME_SLUG, // Theme slug
		'version'        => wp_get_theme( get_template() )->get( 'Version' ), // The current version of this theme
		'author'         => 'Awaiken Themes', // The author of this theme
		'download_id'    => '', // Optional, used for generating a license renewal link
		'renew_url'      => '', // Optional, allows for a custom license renewal link
		'beta'           => false, // Optional, set to true to opt into beta versions
		'item_id'        => AWAIKEN_ITEM_ID,
	),
	// Strings
	array(
		'activate-license-info'     => __( 'Please activate the theme license key to proceed.', 'backeri' ),
		'maybe-later-btn'    	 => __( 'Maybe Later', 'backeri' ),
		'dismiss-notice-btn'    	 => __( 'Dismiss this notice', 'backeri' ),
		'theme-license'             => __( 'Theme License', 'backeri' ),
		'enter-key-tf'              => __( 'Enter your Item Purchase Code. <a href="%s" rel="noopener noreferrer" target="_blank">Refer to the article for instructions on where to find it</a>.', 'backeri' ),
		'enter-key'                 => __( 'Enter your theme license key.', 'backeri' ),
		'license-key'               => __( 'License Key', 'backeri' ),
		'license-buyer-email'       => __( 'Email', 'backeri' ),
		'license-action'            => __( 'License Action', 'backeri' ),
		'deactivate-license'        => __( 'Deactivate License', 'backeri' ),
		'activate-license'          => __( 'Activate License', 'backeri' ),
		'status-unknown'            => __( 'License status is unknown.', 'backeri' ),
		'renew'                     => __( 'Renew?', 'backeri' ),
		'unlimited'                 => __( 'unlimited', 'backeri' ),
		'license-key-is-active'     => __( 'License key is active.', 'backeri' ),
		/* translators: the license expiration date */
		'expires%s'                 => __( 'Expires %s.', 'backeri' ),
		'expires-never'             => __( 'Lifetime License.', 'backeri' ),
		/* translators: 1. the number of sites activated 2. the total number of activations allowed. */
		'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'backeri' ),
		'activation-limit'          => __( 'Your license key has reached its activation limit.', 'backeri' ),
		/* translators: the license expiration date */
		'license-key-expired-%s'    => __( 'License key expired %s.', 'backeri' ),
		'license-key-expired'       => __( 'License key has expired.', 'backeri' ),
		/* translators: the license expiration date */
		'license-expired-on'        => __( 'Your license key expired on %s.', 'backeri' ),
		'license-keys-do-not-match' => __( 'License keys do not match.', 'backeri' ),
		'license-is-inactive'       => __( 'License is inactive.', 'backeri' ),
		'license-key-is-disabled'   => __( 'License key is disabled.', 'backeri' ),
		'license-key-invalid'       => __( 'Invalid license.', 'backeri' ),
		'site-is-inactive'          => __( 'Your license is not active for this URL.', 'backeri' ),
		/* translators: the theme name */
		'item-mismatch'             => __( 'This appears to be an invalid license key for %s.', 'backeri' ),
		'license-status-unknown'    => __( 'License status is unknown.', 'backeri' ),
		'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'backeri' ),
		'error-generic'             => __( 'An error occurred, please try again.', 'backeri' ),
		'pending-active'            => __( 'Click the Activate License button to activate the license.', 'backeri' ),
	)
);
