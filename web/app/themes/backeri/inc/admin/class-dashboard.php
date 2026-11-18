<?php
/**
 *
 * Dashboard
 * @package Dashboard
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dashboard class.
 */
class Backeri_Dashboard
{

    /**
     * The settings of page.
     *
     * @var array $settings The settings.
     */
    public $settings = array();

    /**
     * Constructor.
     */
    public function __construct()
    {

        if( ! is_admin() ) {
            return;
        }
		
		add_action('init', array($this, 'set_settings'));
		add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_menu_page'));
	}

    /**
     * Settings
     *
     * @param array $settings The settings.
     */
    public function set_settings()
    {
		$setting =	 array();
		$setting['hero_title']       = esc_html__( 'Welcome to Backeri', 'backeri' );
		$setting['hero_desc'] = esc_html__( 'Backeri has been successfully installed and is ready to use. Click the Getting Started tab to quickly get started with a pre-made template, or visit the Dashboard tab for a complete overview.', 'backeri' );
		

		// Promo.
		$setting['promo_link']   = '';
		$setting['doc_link']   = 'https://docs.awaikenthemes.com/';
		$setting['doc_troubleshooting_link']   = 'https://docs.awaikenthemes.com/category/troubleshooting-and-faqs/';
		$setting['doc_system_requirements_link']   = 'https://docs.awaikenthemes.com/system-requirements/';
		$setting['doc_license_key']   = 'https://docs.awaikenthemes.com/how-to-get-license-key/';
		
		$setting['site_settings_url']   = '';

		if ( defined( 'ELEMENTOR_VERSION' ) && is_callable( '\Elementor\Plugin::instance' ) ) {
			$kit_id = get_option( 'elementor_active_kit' );
			if ( $kit_id ) {
				$kit = \Elementor\Plugin::$instance->documents->get( $kit_id );
				
				if ( $kit && method_exists( $kit, 'get_edit_url' ) ) {
					$setting['site_settings_url'] = $kit->get_edit_url();
				}
			}
		}
        $this->settings = apply_filters('backeri_dashboard_settings', $setting);
    }

    /**
     * Add menu page
     */
    public function add_menu_page()
    {
	
		$capability = 'manage_options';
		$parent_menu_item = 'at-dashboard';

		if ( ! current_user_can( $capability ) ) {
			return;
		}

        add_menu_page( // phpcs:ignore WPThemeReview.PluginTerritory.NoAddAdminPages.add_menu_pages_add_menu_page
            __( 'Backeri Dashboard', 'backeri' ),
            __( 'Theme Options', 'backeri' ),
            $capability,
            $parent_menu_item,
            '',
            BACKERI_THEME_URL.'/inc/admin/assets/images/at-icon.svg',
            2
        );

        add_submenu_page( // phpcs:ignore WPThemeReview.PluginTerritory.NoAddAdminPages.add_menu_pages_add_submenu_page
            $parent_menu_item,
            __( 'Backeri Dashboard', 'backeri' ),
            __( 'Dashboard', 'backeri' ),
            $capability,
            $parent_menu_item,
            [ $this, 'dashboard_page_template' ],
            0
        );
		
		add_submenu_page( // phpcs:ignore WPThemeReview.PluginTerritory.NoAddAdminPages.add_menu_pages_add_submenu_page
            $parent_menu_item,
            __( 'Getting Started', 'backeri' ),
            __( 'Getting Started', 'backeri' ),
            $capability,
            'at-getting-started',
            [ $this, 'getting_started_page_template' ],
        );


		
		add_submenu_page( // phpcs:ignore WPThemeReview.PluginTerritory.NoAddAdminPages.add_menu_pages_add_submenu_page
            $parent_menu_item,
            __( 'Troubleshooting', 'backeri' ),
            __( 'Troubleshooting', 'backeri' ),
            $capability,
            'at-troubleshooting',
            [ $this, 'troubleshooting_page_template' ],
        );

    }

    /**
     * This function will register scripts and styles for admin dashboard.
     *
     * @param string $page Current page.
     */
    public function admin_enqueue_scripts($hook)
    {
		wp_enqueue_style( 'bootstrap-5.3.2', BACKERI_THEME_URL . '/inc/admin/assets/css/bootstrap-grid.css', array(), BACKERI_THEME_VERSION );
        wp_enqueue_style( 'at-dashboard', BACKERI_THEME_URL . '/inc/admin/assets/css/dashboard.css', array(), BACKERI_THEME_VERSION);
    }
	
    public function dashboard_page_template() {
		
        $user_id             = get_current_user_id();
        $current_user        = wp_get_current_user();
        $notifications_count = 1;

        $theme_slug     = get_template();
        $theme_version  = wp_get_theme()->get( 'Version' );
		require_once BACKERI_THEME_DIR . '/inc/admin/views/dashboard.php';

	}
	
	public function getting_started_page_template() {
		require_once BACKERI_THEME_DIR . '/inc/admin/views/getting-started.php';
	}
	
	public function troubleshooting_page_template() {
		require_once BACKERI_THEME_DIR . '/inc/admin/views/troubleshooting.php';
	}

	public function license_page_template() {
		require_once BACKERI_THEME_DIR . '/inc/admin/views/license.php'; // phpcs:ignore WPThemeReview.CoreFunctionality.FileInclude
	}

}

global $at_theme_dashboard;
$at_theme_dashboard = new Backeri_Dashboard();


/**
 * Sets the site logo (logo.svg / logo.png / logo.jpg from the media library),
 * the front page (Home) and the posts page (Blog), same as the demo import does.
 */
function backeri_apply_demo_defaults() {

	// Logo: first existing attachment named logo.svg, logo.png or logo.jpg.
	foreach ( array( 'logo.svg', 'logo.png', 'logo.jpg' ) as $file ) {
		$logo = get_posts(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => '_wp_attached_file',
						'value'   => '/' . $file,
						'compare' => 'LIKE',
					],
				],
			]
		);

		if ( ! empty( $logo ) ) {
			set_theme_mod( 'custom_logo', $logo[0] );
			break;
		}
	}

	// Front page.
	$front_page = get_posts(
		[
			'post_type'              => 'page',
			'title'                  => 'Home',
			'post_status'            => 'all',
			'numberposts'            => 1,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		]
	);

	if ( ! empty( $front_page ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page[0]->ID );
	}

	// Blog page.
	$blog_page = get_posts(
		[
			'post_type'              => 'page',
			'title'                  => 'Blog',
			'post_status'            => 'all',
			'numberposts'            => 1,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		]
	);

	if ( ! empty( $blog_page ) ) {
		update_option( 'page_for_posts', $blog_page[0]->ID );
	}
}

add_action( 'wp_ajax_backeri_reapply_elementor_kit', 'backeri_reapply_elementor_kit_callback' );
function backeri_reapply_elementor_kit_callback() {

    // Security check.
    check_ajax_referer( 'backeri_reapply_kit_nonce', 'nonce' );

    // Check user capability.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'backeri' ) ] );
    }

    // Get Elementor Kit.
    $kit_page = get_posts(
        [
            'post_type'              => 'elementor_library',
            'title'                  => 'Backeri - Default Kit',
            'post_status'            => 'all',
            'numberposts'            => 1,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ]
    );

    if ( ! empty( $kit_page ) ) {
		// If the active kit is not ours, the demo setup did not complete, so also set logo, home and blog pages.
		$apply_defaults = (int) get_option( 'elementor_active_kit' ) !== (int) $kit_page[0]->ID;

        update_option( 'elementor_active_kit', $kit_page[0]->ID );

		if ( $apply_defaults ) {
			backeri_apply_demo_defaults();
		}

		// Replace our demo URLs with this site's URL (defined in ocdi.php).
		if ( function_exists( 'backeri_replace_demo_urls' ) ) {
			backeri_replace_demo_urls( backeri_get_active_demo_url() );
		}

		// WooCommerce demo setup (defined in inc/woocommerce.php, not available in every theme).
		if ( class_exists( 'WooCommerce' ) && function_exists( 'backeri_ocdi_after_import_setup_woo' ) ) {
			backeri_ocdi_after_import_setup_woo( array() );
		}

		if ( did_action( 'elementor/loaded' ) ) {
			// Regenerate CSS files
			\Elementor\Plugin::instance()->files_manager->clear_cache();
		}
		update_option( 'backeri_kit_applied', '1' );
        wp_send_json_success( [ 'message' => __( 'Style has been re-applied successfully! If you have any caching plugins active, please clear their cache to see the changes.', 'backeri' ) ] );
    } else {
        wp_send_json_error( [ 'message' => __( 'Style not found. Please contact our support team.', 'backeri' ) ] );
    }
}