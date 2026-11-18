<?php

namespace ElementsKit\Modules\Onepage_Scroll;

defined( 'ABSPATH' ) || exit;

/**
 * Extend the Lite One Page Scroll module with Pro navigation controls.
 */
class Init {
	/**
	 * Initialize the shared Lite module and its Pro extension.
	 */
	public function __construct() {
		new \ElementsKit_Lite\Modules\Onepage_Scroll\Init();

		require_once __DIR__ . '/extend-controls.php';
		new \Elementor\ElementsKit_Onepage_Scroll_Navigation_Controls();
	}
}
