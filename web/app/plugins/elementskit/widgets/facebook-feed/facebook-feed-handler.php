<?php

namespace Elementor;

use ElementsKit_Lite\Libs\Framework\Attr;

class ElementsKit_Widget_Facebook_Feed_Handler extends \ElementsKit_Lite\Core\Handler_Widget {

	public function wp_init() {
		include(self::get_dir() . 'classes/settings.php');

		add_action('upgrader_process_complete', [$this, 'cleanup_legacy_markup_cache'], 10, 2);

		(new \ElementsKit\Widgets\Facebook_Feed\Facebook_Feed_Api());
	}

	/**
	 * Facebook image URLs are signed and expire, so every image is mirrored into
	 * the uploads folder and served from there once it has been stored.
	 */
	public static function media_cache() {

		return \ElementsKit\Helpers\Media_Cache::instance('facebook-feed', [
			'hosts'   => ['fbcdn.net', 'facebook.com', 'akamaihd.net'],
			'timeout' => 10,
		]);
	}

	/**
	 * Earlier versions cached the rendered feed next to the API response. That
	 * markup is not read any more, and a transient without an expiry is an
	 * autoloaded option, so it would be read on every request for good.
	 *
	 * Updating the plugin is the moment to drop it. Sites that never rendered
	 * the widget have nothing to remove, and the query simply finds no rows.
	 */
	public function cleanup_legacy_markup_cache($upgrader_object, $options) {

		if(empty($options['action']) || 'update' !== $options['action'] || empty($options['type']) || 'plugin' !== $options['type']) {
			return;
		}

		$updated = [];

		if(!empty($options['plugins']) && is_array($options['plugins'])) {
			$updated = $options['plugins'];
		}

		if(!empty($options['plugin']) && !is_array($options['plugin'])) {
			$updated[] = $options['plugin'];
		}

		if(!in_array(plugin_basename(\ElementsKit::plugin_file()), $updated, true)) {
			return;
		}

		// wp_init loads this, but an update can run before the widget was booted.
		if(!class_exists('Ekit_facebook_settings')) {

			$file = self::get_dir() . 'classes/settings.php';

			if(file_exists($file)) {
				include_once($file);
			}
		}

		if(!class_exists('Ekit_facebook_settings')) {
			return;
		}

		if(get_option(\Ekit_facebook_settings::CLEANUP_FLAG)) {
			return;
		}

		\Ekit_facebook_settings::delete_legacy_markup_cache();

		// autoload false: this is only ever read while updating the plugin, so it
		// has no business being loaded on every request of the site.
		update_option(\Ekit_facebook_settings::CLEANUP_FLAG, \ElementsKit::version(), false);
	}


	static function get_name() {
		return 'elementskit-facebook-feed';
	}


	static function get_title() {
		return esc_html__('Facebook Feed', 'elementskit');
	}


	static function get_icon() {
		return 'eicon-fb-feed ekit-widget-icon ';
	}


	static function get_categories() {
		return ['elementskit'];
	}

	static function get_keywords() {
		return ['ekit', 'facebook', 'feed', 'social feed', 'fb', 'facebook integration'];
	}

	static function get_dir() {
		return \ElementsKit::widget_dir() . 'facebook-feed/';
	}


	static function get_url() {
		return \ElementsKit::widget_url() . 'facebook-feed/';
	}


	public static function get_data() {

		$data = Attr::instance()->utils->get_option('user_data', []);

		$access = empty($data['fb_feed']['pg_token']) ? '' : $data['fb_feed']['pg_token'];
		$page_id = empty($data['fb_feed']['page_id']) ? '' : $data['fb_feed']['page_id'];


		return [
			'pg_id'  => $page_id,
			'pg_tok' => $access,
		];
	}
}