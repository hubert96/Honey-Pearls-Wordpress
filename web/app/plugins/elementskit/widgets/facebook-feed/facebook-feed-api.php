<?php

namespace ElementsKit\Widgets\Facebook_Feed;

use ElementsKit\Libs\Framework\Attr;
use ElementsKit_Lite\Core\Handler_Api;

defined('ABSPATH') || exit;

class Facebook_Feed_Api extends Handler_Api {

	public function config() {

		$this->prefix = 'widget/fb-feed';
	}


	public function post_remove_cache() {

		$data = $this->request->get_params();
		$idd = sanitize_key($data['provider_id']);

		if($idd == 'fb_page_feed') {

			$data = Attr::instance()->utils->get_option('user_data', []);

			if(empty($data['fb_feed']['page_id'])) {

				return [
					'success' => false,
					'msg'     => __('page id not found!', 'elementskit'),
				];
			}

			if(empty($data['fb_feed']['pg_token'])) {

				return [
					'success' => false,
					'msg'     => __('page access token not found!', 'elementskit'),
				];
			}

			$removed_any = false;

			// 1. Clear page posts cache
			if(class_exists('Ekit_facebook_settings') && \Ekit_facebook_settings::delete_posts_cache($data['fb_feed']['page_id'], $data['fb_feed']['pg_token'])) {
				$removed_any = true;
			}

			// 2-5. Clear additional caches
			if(class_exists('Ekit_facebook_settings')) {
				$page_id = $data['fb_feed']['page_id'];
				$pg_token = $data['fb_feed']['pg_token'];

				// 2. Clear page verification cache (using hash of token+page_id)
				$hash = md5($pg_token . $page_id);
				$page_verify_key = \Ekit_facebook_settings::get_fb_pg_call_transient_key($hash);
				if(delete_transient($page_verify_key)) {
					$removed_any = true;
				}

				// 3. Clear page token-based info cache
				$page_info_key = \Ekit_facebook_settings::get_fb_pg_call_transient_key($pg_token);
				if(delete_transient($page_info_key)) {
					$removed_any = true;
				}

				// 4. Clear "me" endpoint cache
				$me_key = \Ekit_facebook_settings::get_me_call_transient_key($pg_token);
				if(delete_transient($me_key)) {
					$removed_any = true;
				}

				// 5. Clear user feed cache (if user_id is same as page_id)
				$user_feed_key = \Ekit_facebook_settings::get_fb_user_feed_call_transient_key($page_id);
				if(delete_transient($user_feed_key)) {
					$removed_any = true;
				}
			}

			// 6. Caches left behind by an older page id or token go too, so clearing
			// the cache really empties this widget's storage.
			if(class_exists('Ekit_facebook_settings')) {

				$stale = \Ekit_facebook_settings::purge_stale_transients($data['fb_feed']['page_id'], $data['fb_feed']['pg_token']);

				if($stale > 0) {
					$removed_any = true;
				}
			}

			// 7. Drop the mirrored images from the uploads folder as well, so the next
			// render downloads them again alongside the fresh feed.
			if(\Elementor\ElementsKit_Widget_Facebook_Feed_Handler::media_cache()->flush()) {
				$removed_any = true;
			}

			if($removed_any) {

				return [
					'success' => true,
					'msg'     => __('Successfully cleaned all Facebook feed caches', 'elementskit'),
				];
			}

			return [
				'success' => false,
				'msg'     => __('No cache found to clear!', 'elementskit'),
			];
		}

		return [
			'success' => false,
			'msg'     => __('Unknown provider key', 'elementskit'),
		];
	}

}
