<?php

namespace Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Pro-only controls and rendering for the Lite Nav Menu widget.
 */
class ElementsKit_Pro_Nav_Menu_Extension {
	public function __construct() {
		add_action( 'elementskit/nav_menu/after_dropdown_behavior_control', array( $this, 'register_controls' ) );
		add_filter( 'elementskit/nav_menu/show_lite_indicator_control', '__return_false' );
		add_filter( 'elementskit/nav_menu/submenu_indicator_html', array( $this, 'render_indicator' ), 10, 3 );
	}

	/**
	 * Register the Pro dropdown indicator control.
	 *
	 * @param Controls_Stack $element Elementor control stack.
	 */
	public function register_controls( Controls_Stack $element ) {
		$element->add_control(
			'elementskit_submenu_indicator_icon',
			array(
				'label'                  => esc_html__( 'Dropdown Indicator Icon', 'elementskit' ),
				'type'                   => Controls_Manager::ICONS,
				// 'skin'                   => 'inline',
				'exclude_inline_options' => array( 'svg' ),
				'skin_settings'          => array(
					'inline' => array(
						'none' => array(
							'label' => esc_html__( 'Default', 'elementskit' ),
							'icon'  => 'icon icon-down-arrow1',
						),
						'icon' => array(
							'label' => esc_html__( 'Icon Library', 'elementskit' ),
							'icon'  => 'fas fa-external-link-alt',
						),
					),
				),
				'recommended'            => array(
					'ekiticons' => array( 'down-arrow1', 'arrow-point-to-down', 'plus', 'link' ),
					'fa-solid' => array( 'plus', 'external-link-alt', 'link', 'angle-down' ),
				),
				// 'label_block'            => false,
			)
		);
	}

	/**
	 * Render the saved Pro icon while preserving existing document data.
	 *
	 * @param string $html            Existing indicator markup.
	 * @param array  $settings        Widget settings.
	 * @param string $indicator_class Indicator CSS class.
	 * @return string
	 */
	public function render_indicator( $html, array $settings, $indicator_class ) {
		if ( empty( $settings['elementskit_submenu_indicator_icon']['value'] ) ) {
			return $html;
		}

		return Icons_Manager::try_get_icon_html(
			$settings['elementskit_submenu_indicator_icon'],
			array(
				'class'       => $indicator_class,
				'aria-hidden' => 'true',
			)
		);
	}
}
