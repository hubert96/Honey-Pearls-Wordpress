<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * WooCommerce Compatibility File.
 *
 */

if ( ! class_exists( 'backeri_theme_woocommerce' ) ) {
	class backeri_theme_woocommerce {
		
		private static $instance;
		private $defaults;

		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}
		
		public function __construct() { 
			$this->defaults = awaiken_generate_defaults();
			add_action( 'init', [ $this, 'alter_wc_hooks' ] );
			add_action( 'widgets_init', [ $this, 'wc_widgets_init' ], 15 );
			add_filter( 'woocommerce_show_page_title', '__return_false' );
			
			// Register customizer controls
			add_action( 'customize_register', [ $this, 'wc_customizer_options' ] );
		}
		
		public static function alter_wc_hooks() {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		}
		
		public function wc_widgets_init() {
			register_sidebar(
				apply_filters(
					'backeri_wc_sidebar',
					array(
						'name' => esc_html__( 'Shop Sidebar', 'backeri' ),
						'id' => 'wc-sidebar',
						'description' => esc_html__( 'This sidebar will be used on Shop and Product Taxonomy pages.', 'backeri' ),
						'before_widget' => '<aside id="%1$s" class="widget %2$s">',
						'after_widget'  => '</aside>',
						'before_title'  => '<h3 class="widget-title">',
						'after_title'   => '</h3>',
					)
				)
			);
			register_sidebar(
				apply_filters(
					'backeri_wc_single_sidebar',
					array(
						'name' => esc_html__( 'Product Sidebar', 'backeri' ),
						'id' => 'wc-single-sidebar',
						'description' => esc_html__( 'This sidebar will be used on Single Product pages.', 'backeri' ),
						'before_widget' => '<aside id="%1$s" class="widget %2$s">',
						'after_widget'  => '</aside>',
						'before_title'  => '<h3 class="widget-title">',
						'after_title'   => '</h3>',
					)
				)
			);
		}
		
		public function wc_customizer_options( $wp_customize ) { 
			$section	=	'wc_backeri_settings';
			
			// Add section to WooCommerce panel
			$wp_customize->add_section( $section , array(
				'title'      => __( 'Backeri - Settings', 'backeri' ),
				'panel'		=>		'woocommerce',
			) );
			
			//Header Background Image
			$wp_customize->add_setting( 'shop_page_header_background_image',
				array(
					'default' => '',
					'transport' => 'refresh',
					'sanitize_callback' => 'absint'
				)
			);
			
			$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'shop_page_header_background_image',
				array(
					'label' => __( 'Header Background Image', 'backeri' ),
					'description' => esc_html__( 'Header background image for shop archive and product single page.', 'backeri' ),
					'section' => $section,
					'mime_type' => 'image',
					'button_labels' => array(
						'select' => __( 'Select File', 'backeri' ),
						'change' => __( 'Change File', 'backeri' ),
						'default' => __( 'Default', 'backeri' ),
						'remove' => __( 'Remove', 'backeri' ),
						'placeholder' => __( 'No file selected', 'backeri' ),
						'frame_title' => __( 'Select File', 'backeri' ),
						'frame_button' => __( 'Choose File', 'backeri' ),
					)
				)
			) );
			
			// Product list layout
			$wp_customize->add_setting( 'shop_page_layout', array(
			  'default' => $this->defaults['shop_page_layout'],
			   'sanitize_callback' => 'sanitize_text_field',
			) );
			
			$wp_customize->add_control( 'shop_page_layout', array(
				  'label'          => __( 'Shop Archive Layout', 'backeri' ),
				  'section' => $section,
				  'settings' => 'shop_page_layout',
				  'type' => 'radio',
				  'choices' => array(
					'full-width'   => __( 'Full Width', 'backeri' ),
					'right-sidebar'  => __( 'Right Sidebar', 'backeri' ),
					'left-sidebar'  => __( 'Left Sidebar', 'backeri' )
				  ),
			) );
			
			// Product list layout
			$wp_customize->add_setting( 'product_single_layout', array(
			  'default' => $this->defaults['product_single_layout'],
			   'sanitize_callback' => 'sanitize_text_field',
			) );
			
			$wp_customize->add_control( 'product_single_layout', array(
				  'label'          => __( 'Product Single Layout', 'backeri' ),
				  'section' => $section,
				  'settings' => 'product_single_layout',
				  'type' => 'radio',
				  'choices' => array(
					'full-width'   => __( 'Full Width', 'backeri' ),
					'right-sidebar'  => __( 'Right Sidebar', 'backeri' ),
					'left-sidebar'  => __( 'Left Sidebar', 'backeri' )
				  ),
			) );
			
		}
		
	}
}
backeri_theme_woocommerce::instance();