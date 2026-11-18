<?php

namespace ElementsKit\Widgets\Instagram_Feed;

use Elementor\ElementsKit_Widget_Instagram_Feed_Handler;
use ElementsKit_Lite\Core\Handler_Api;

defined('ABSPATH') || exit;

class Instagram_Feed_Api extends Handler_Api {

	public function config() {

		$this->prefix = 'widget/instagram-feed';
	}


	public function post_remove_cache() {

		$data = $this->request->get_params();
		$idd = sanitize_key(empty($data['provider_id']) ? '' : $data['provider_id']);

		if($idd == 'instagram_feed') {

			// The route itself is open, so the capability is checked here. Without it
			// anyone could keep wiping the stored feed and force every visit to call
			// the API and download the media again.
			if(!current_user_can('manage_options')) {

				return [
					'success' => false,
					'msg'     => __('You are not allowed to clear the cache!', 'elementskit'),
				];
			}

			// The stored response, the profile info and whatever an older user id,
			// token or plugin version left behind all go together, so clearing the
			// cache really does empty this widget's storage.
			$removed = ElementsKit_Widget_Instagram_Feed_Handler::delete_cache();

			// The mirrored media goes as well, so the next render downloads it
			// again alongside the fresh feed.
			if(ElementsKit_Widget_Instagram_Feed_Handler::media_cache()->flush()) {
				$removed++;
			}

			if($removed > 0) {

				return [
					'success' => true,
					'msg'     => __('Successfully cleaned all Instagram feed caches', 'elementskit'),
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
