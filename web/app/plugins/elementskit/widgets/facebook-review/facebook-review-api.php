<?php

namespace ElementsKit\Widgets\Facebook_Review;

use ElementsKit\Libs\Framework\Attr;
use ElementsKit_Lite\Core\Handler_Api;

defined('ABSPATH') || exit;

class Facebook_Review_Api extends Handler_Api {

	public function config() {

		$this->prefix = 'widget/fb-pg-review';
	}


	public function post_remove_cache() {

		$data = $this->request->get_params();
		$idd = sanitize_key($data['provider_id']);

		if($idd == 'fb_page_reviews') {

			$data = Attr::instance()->utils->get_option('user_data', []);

			if(empty($data['fbp_review']['pg_id'])) {

				return [
					'success' => false,
					'msg'     => __('page id not found!', 'elementskit'),
				];
			}

			if(empty($data['fbp_review']['pg_token'])) {

				return [
					'success' => false,
					'msg'     => __('page access token not found!', 'elementskit'),
				];
			}

			$page_id  = $data['fbp_review']['pg_id'];
			$pg_token = $data['fbp_review']['pg_token'];

			// Every cache is cleared on its own. Nesting these used to leave the
			// reviews behind whenever the page info cache was already gone.
			$keys = [
				self::get_fbp_fail_trans_key($page_id, $pg_token, 'pg_info'),
				self::get_fbp_fail_trans_key($page_id, $pg_token, 'reviews'),
			];

			$removed_any = false;

			foreach($keys as $key) {
				if(delete_transient($key)) {
					$removed_any = true;
				}
			}

			// The cached API responses are plain options, and whatever an older page
			// id or token left behind goes with them.
			if(class_exists('Elementor\\ElementsKit_Widget_Facebook_Review_Handler')) {

				$handler = 'Elementor\\ElementsKit_Widget_Facebook_Review_Handler';

				if($handler::delete_reviews_cache($page_id, $pg_token)) {
					$removed_any = true;
				}

				if($handler::delete_page_info_cache($page_id, $pg_token)) {
					$removed_any = true;
				}

				if($handler::purge_stale_caches($page_id, $pg_token) > 0) {
					$removed_any = true;
				}

				// Drop the mirrored images so they are downloaded again with the data.
				// The handler pulls the image cache in itself when it is not loaded yet.
				if($handler::flush_image_cache()) {
					$removed_any = true;
				}
			}

			if($removed_any) {

				return [
					'success' => true,
					'msg'     => __('Successfully cleaned', 'elementskit'),
				];
			}

			return [
				'success' => false,
				'msg'     => __('Cache not found!', 'elementskit'),
			];
		}

		return [
			'success' => false,
			'msg'     => __('Unknown provider key', 'elementskit'),
		];
	}

	public static function get_fbp_fail_trans_key($page_id, $token, $what) {

		$md = md5($page_id . $token);

		return '_trans_ekit_fbp_review_fail_' . $what . '_' . $md;
	}
}
