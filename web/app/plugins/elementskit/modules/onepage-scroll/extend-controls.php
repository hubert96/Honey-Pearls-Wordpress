<?php

namespace Elementor;

class ElementsKit_Onepage_Scroll_Navigation_Controls {
	public function __construct() {
		/**
		 * Navigation page controls.
		 */
		add_action( 'elementor/element/wp-page/ekit_page_settings/before_section_end', array( $this, 'register_page_controls' ) );

		/**
		 * Navigation section controls.
		 */
		add_action( 'elementor/element/section/ekit_onepagescroll_section/before_section_end', array( $this, 'register_section_controls' ) );

		/**
		 * Flexbox Container Support
		 */
		add_action( 'elementor/element/container/ekit_onepagescroll_section/before_section_end', array( $this, 'register_section_controls' ) );

		add_action( 'wp_footer', array( $this, 'render_navigation' ) );
		add_action( 'wp_ajax_generate_navigation_markup', array( $this, 'render_navigation' ) );
	}


	/**
	 * Navigation page controls.
	 */
	public function register_page_controls( Controls_Stack $element ) {
		$element->add_control(
			'ekit_onepagescroll_heading',
			array(
				'label'     => __( 'Onepage Scroll Settings', 'elementskit' ),
				'type'      => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'ekit_onepagescroll' => 'block',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav',
			array(
				'label'              => esc_html__( 'Navigation Style', 'elementskit' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => '0',
				'groups'             => array(
					'0'    => esc_html__( 'None', 'elementskit' ),
					array(
						'label'   => esc_html__( 'Circle', 'elementskit' ),
						'options' => array(
							'circle-scale-up'      => esc_html__( 'Scale Up', 'elementskit' ),
							'circle-fill-in'       => esc_html__( 'Fill In', 'elementskit' ),
							'circle-fill-out'      => esc_html__( 'Fill Out', 'elementskit' ),
							'circle-stroke'        => esc_html__( 'Stroke', 'elementskit' ),
							'circle-stroke-dot'    => esc_html__( 'Stroke Dot', 'elementskit' ),
							'circle-stroke-simple' => esc_html__( 'Stroke Simple', 'elementskit' ),
							'circle-dot-move'      => esc_html__( 'Dot Move', 'elementskit' ),
							'circle-timeline'      => esc_html__( 'Timeline', 'elementskit' ),
						),
					),
					array(
						'label'   => esc_html__( 'Square', 'elementskit' ),
						'options' => array(
							'square-scale-up' => esc_html__( 'Scale Up', 'elementskit' ),
						),
					),
					array(
						'label'   => esc_html__( 'Line', 'elementskit' ),
						'options' => array(
							'line-grow'   => esc_html__( 'Line Grow', 'elementskit' ),
							'line-shrink' => esc_html__( 'Line Shrink', 'elementskit' ),
							'line-fill'   => esc_html__( 'Line Fill', 'elementskit' ),
							'line-move'   => esc_html__( 'Line Move', 'elementskit' ),
						),
					),
					'icon' => esc_html__( 'Custom Icon', 'elementskit' ),
				),
				'frontend_available' => true,
				'condition'          => array(
					'ekit_onepagescroll' => 'block',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav_icon',
			array(
				'label'              => esc_html__( 'Navigation Icon', 'elementskit' ),
				'type'               => Controls_Manager::ICONS,
				'default'            => array(
					'value'   => 'fas fa-circle',
					'library' => 'solid',
				),
				'frontend_available' => true,
				'condition'          => array(
					'ekit_onepagescroll'     => 'block',
					'ekit_onepagescroll_nav' => 'icon',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav_pos',
			array(
				'label'              => esc_html__( 'Navigation Position', 'elementskit' ),
				'type'               => Controls_Manager::CHOOSE,
				'default'            => 'right',
				'options'            => array(
					'top' => array(
						'title' => esc_html__( 'Top', 'elementskit' ),
						'icon'  => 'fa fa-angle-up',
					),
					'bottom' => array(
						'title' => esc_html__( 'Bottom', 'elementskit' ),
						'icon'  => 'fa fa-angle-down',
					),
					'left' => array(
						'title' => esc_html__( 'Left', 'elementskit' ),
						'icon'  => 'fa fa-angle-left',
					),
					'right' => array(
						'title' => esc_html__( 'Right', 'elementskit' ),
						'icon'  => 'fa fa-angle-right',
					),
				),
				'toggle'             => false,
				'frontend_available' => true,
				'condition'          => array(
					'ekit_onepagescroll'      => 'block',
					'ekit_onepagescroll_nav!' => '0',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav_pos_offset',
			array(
				'label'      => esc_html__( 'Navigation Position Offset', 'elementskit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1000,
						'step' => 10,
					),
					'%' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => '50',
				),
				'condition'  => array(
					'ekit_onepagescroll'      => 'block',
					'ekit_onepagescroll_nav!' => '0',
				),
				'selectors'  => array(
					'.onepage_scroll_nav.met-top, .onepage_scroll_nav.met-bottom'   => 'left: {{SIZE}}{{UNIT}};',
					'.onepage_scroll_nav.met-left, .onepage_scroll_nav.met-right'   => 'top: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav_spacing',
			array(
				'label'      => esc_html__( 'Navigation Spacing', 'elementskit' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'unit' => 'px',
					'size' => '20',
				),
				'condition'  => array(
					'ekit_onepagescroll'      => 'block',
					'ekit_onepagescroll_nav!' => '0',
				),
				'selectors'  => array(
					'.onepage_scroll_nav:not(.met-top):not(.met-bottom) li:not(:last-child)'  => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'.onepage_scroll_nav:not(.met-left):not(.met-right) li:not(:last-child)'  => 'margin-right: {{SIZE}}{{UNIT}};',

					'.onepage_scroll_nav.nav-style-circle-timeline:not(.met-top):not(.met-bottom) li:before'  => 'height: calc({{SIZE}}{{UNIT}} - 1px)',
					'.onepage_scroll_nav.nav-style-circle-timeline:not(.met-left):not(.met-right) li:before'  => 'width: calc({{SIZE}}{{UNIT}} - 1px)',
				),
			)
		);

		$element->add_control(
			'ekit_onepagescroll_nav_color',
			array(
				'label'     => esc_html__( 'Navigation Color', 'elementskit' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111',
				'condition' => array(
					'ekit_onepagescroll'      => 'block',
					'ekit_onepagescroll_nav!' => '0',
				),
				'selectors' => array(
					/**
					 * .editor:met_color
					 */
					'.onepage_scroll_nav .editor\:met_color' => 'color: {{VALUE}};',

					/**
					 * .editor:met_bgc
					 * .editor:before:met_bgc
					 */
					'.onepage_scroll_nav .editor\:met_bgc, .onepage_scroll_nav .editor\:before\:met_bgc:before' => 'background-color: {{VALUE}};',

					/**
					 * .editor:met_bdc
					 * .editor:active:met_bdc
					 */
					'.onepage_scroll_nav .editor\:met_bdc, .onepage_scroll_nav .active > .editor\:active\:met_bdc' => 'border-color: {{VALUE}};',
				),
			)
		);

		$element->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'      => 'ekit_onepagescroll_nav_tooltip_font',
				'label'     => esc_html__( 'Tooltip Typography', 'elementskit' ),
				'selector'  => '.onepage_scroll_nav .nav_tooltip',
				'condition' => array(
					'ekit_onepagescroll'      => 'block',
					'ekit_onepagescroll_nav!' => '0',
				),
			)
		);
	}


	/**
	 * Navigation section controls.
	 */
	public function register_section_controls( $element ) {
		$element->add_control(
			'ekit_has_onepagescroll_dot',
			array(
				'label'              => esc_html__( 'Enable Dot', 'elementskit' ),
				'type'               => Controls_Manager::SWITCHER,
				'frontend_available' => true,
				'default'            => 'yes',
				'render_type'        => 'none',
			)
		);
		$element->add_control(
			'ekit_onepagescroll_tooltip_text',
			array(
				'label'              => esc_html__( 'Tooltip Text', 'elementskit' ),
				'type'               => Controls_Manager::TEXT,
				'frontend_available' => true,
				'render_type'        => 'none',
			)
		);
	}

	/**
	 * Render the premium navigation UI for One Page Scroll.
	 */
	public function render_navigation() {
		$init      = '\\ElementsKit_Lite\\Modules\\Onepage_Scroll\\Init';
		if ( ! class_exists( $init ) ) {
			return;
		}

		$is_active = $init::get_page_setting( 'ekit_onepagescroll' );
		$is_nav    = $nav_style = $init::get_page_setting( 'ekit_onepagescroll_nav' );
		$is_editor = \Elementor\Plugin::$instance->preview->is_preview_mode();
		$nav_pos   = $init::get_page_setting( 'ekit_onepagescroll_nav_pos' );
		$nav_icon  = $init::get_page_setting( 'ekit_onepagescroll_nav_icon' );

		if ( ! ( $is_active && $is_nav ) ) {
			return;
		}

		if ( $is_editor ) {
			echo '<div id="onepage_scroll_nav_wrap">';
		}

		$classlist = array(
			'wrapper' => 'nav-style-' . $nav_style . ' met_d--none met_pos--fixed ',
			'ul'      => 'met_list--none met_m--0 met_p--0 met_lh--0 ',
			'li'      => 'met_not_last_mb--20 ',
			'link'    => '',
			'tooltip' => '',
			'arrow'   => '',
			'span'    => '',
		);

		switch ( $nav_pos ) {
			case 'top':
				$classlist['wrapper'] .= 'met-top met_top--0 met_left--50p met_translateLeft--m50p met_my--20 ';
				$classlist['ul']      .= 'met_d--flex ';
				$classlist['li']       = 'met_not_last_mr--20 ';
				$classlist['tooltip'] .= 'met_top--100p ';
				$classlist['arrow']   .= 'met_bdb_color--current met_top--100p ';
				break;

			case 'bottom':
				$classlist['wrapper'] .= 'met-bottom met_bottom--0 met_left--50p met_translateLeft--m50p met_my--20 ';
				$classlist['ul']      .= 'met_d--flex ';
				$classlist['li']       = 'met_not_last_mr--20 ';
				$classlist['tooltip'] .= 'met_bottom--100p ';
				$classlist['arrow']   .= 'met_bdt_color--current met_bottom--100p ';
				break;

			case 'left':
				$classlist['wrapper'] .= 'met-left met_top--50p met_left--0 met_translateTop--m50p met_mx--20 ';
				$classlist['tooltip'] .= 'met_left--100p ';
				$classlist['arrow']   .= 'met_bdr_color--current met_left--100p ';
				break;

			case 'right':
			default:
				$classlist['wrapper'] .= 'met-right met_top--50p met_right--0 met_translateTop--m50p met_mx--20 ';
				$classlist['tooltip'] .= 'met_right--100p ';
				$classlist['arrow']   .= 'met_bdl_color--current met_right--100p ';
				break;
		}

		$nav_styles = array(
			'circle-scale-up',
			'circle-fill-in',
			'circle-fill-out',
			'circle-stroke',
			'circle-stroke-dot',
			'circle-stroke-simple',
			'circle-dot-move',
			'circle-timeline',
			'square-scale-up',
			'line-grow',
			'line-shrink',
			'line-fill',
			'line-move',
			'icon',
		);

		if ( in_array( $nav_style, $nav_styles, true ) ) {
			$template = \ElementsKit::plugin_dir() . 'modules/onepage-scroll/nav-styles/' . $nav_style . '.php';
			if ( is_readable( $template ) ) {
				include $template;
			}
		}

		if ( $is_editor ) {
			echo '</div>';
		}
	}
}
