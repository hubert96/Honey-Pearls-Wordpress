<?php

namespace Elementor;

use ElementsKit\Libs\Framework\Attr;

/**
 * Instagram Feed Handler
 *
 * CACHING STRATEGY:
 * The API response is kept in a plain option that never expires, so the markup is
 * always built from stored data and the Graph API is only called when nothing is
 * stored yet. It is a plain option rather than a transient because a transient
 * without an expiry is autoloaded, so the payload would be read on every request
 * of the whole site; this way it is read only when the feed renders.
 *
 * Only successful responses are stored. Caching an error would keep the widget
 * empty long after the token was fixed.
 *
 * To clear it: the "Clear Cache" button in ElementsKit user settings, which posts
 * to /wp-json/elementskit/v1/widget/instagram-feed/remove_cache
 */
class ElementsKit_Widget_Instagram_Feed_Handler extends \ElementsKit_Lite\Core\Handler_Widget {

	protected static $graph_url = 'https://graph.instagram.com/';

	protected static $api_version = 'v21.0';

	/**
	 * How many posts are fetched. The stored response is shared by every widget on
	 * the site, so it always holds the most this edge returns and each widget shows
	 * as many of them as its own "Show Post" setting allows. Anything else would
	 * make the first widget to render decide the post count for all the others.
	 */
	const MAX_POSTS = 25;

	public function wp_init() {

		(new \ElementsKit\Widgets\Instagram_Feed\Instagram_Feed_Api());
	}

	/**
	 * The signed CDN URLs in the stored response expire, so the media is mirrored
	 * into the uploads folder. Videos are stored as well: this widget plays the
	 * mp4 itself.
	 */
	public static function media_cache() {

		return \ElementsKit\Helpers\Media_Cache::instance('instagram-feed', [
			'hosts' => ['cdninstagram.com', 'instagram.com', 'fbcdn.net'],
			'video' => true,
		]);
	}

	static function get_name() {
		return 'elementskit-instagram-feed';
	}

	static function get_title() {
		return esc_html__('Instagram Feed', 'elementskit');
	}

	static function get_icon() {
		return 'ekit ekit-instagram ekit-widget-icon ';
	}

	static function get_categories() {
		return ['elementskit'];
	}

	static function get_keywords() {
		return ['ekit', 'instagram', 'feed', 'social feed', 'instagram integration'];
	}

	static function get_dir() {
		return \ElementsKit::widget_dir() . 'instagram-feed/';
	}

	static function get_url() {
		return \ElementsKit::widget_url() . 'instagram-feed/';
	}


	public static function get_data() {

		$data = Attr::instance()->utils->get_option('user_data', []);

		$user_id = (isset($data['instragram']) && !empty($data['instragram']['user_id'])) ? $data['instragram']['user_id'] : '';

		$username = (isset($data['instragram']) && !empty($data['instragram']['username'])) ? $data['instragram']['username'] : '';

		$token = empty($data['instragram']['token']) ? '' : $data['instragram']['token'];

		return [
			'user_id'  => $user_id,
			'token'    => $token,
			'username' => $username,
		];
	}


	/**
	 * The key holds the token as well, so replacing the token starts from a fresh
	 * response instead of the one the dead token had produced.
	 */
	public static function get_posts_cache_key($user_id, $token) {

		return 'ekit_insta_posts_' . md5($user_id . $token);
	}


	public static function get_user_cache_key($user_id, $token) {

		return 'ekit_insta_user_info_' . md5($user_id . $token);
	}


	/**
	 * Keys used by earlier versions, which cached by user id alone. Only the
	 * migration below and the cleanup read them now.
	 */
	public static function get_transient_key($uid) {

		return 'ekit_instagram_cached_data_' . md5($uid);
	}


	public static function get_transient_key_for_user($uid) {

		return 'ekit_insta_cache_user_info__' . md5($uid);
	}


	protected static function get_cache($key, $legacy_key = '') {

		$stored = get_option($key, '');

		if('' === $stored || false === $stored) {

			// A response saved by an older version still lives in a transient.
			$stored = ('' === $legacy_key) ? false : get_transient($legacy_key);

			if(false !== $stored && !empty($stored)) {

				self::save_cache($key, $stored);
				delete_transient($legacy_key);
			}
		}

		return empty($stored) ? false : $stored;
	}


	// autoload false: the payload has no business being read on every request.
	protected static function save_cache($key, $value) {

		return update_option($key, $value, false);
	}


	/**
	 * Empties this widget's storage. Rows left behind by an older user id or token
	 * go too, so clearing the cache really does clear everything.
	 *
	 * @return int number of rows removed
	 */
	public static function delete_cache() {

		return self::purge_stale_cache('', '');
	}


	/**
	 * Drops every stored response except the pair belonging to the given user id
	 * and token. Only this widget's own keys are touched.
	 */
	public static function purge_stale_cache($user_id, $token) {

		global $wpdb;

		$keep = [];

		if(!empty($user_id) && !empty($token)) {

			$keep = [
				self::get_posts_cache_key($user_id, $token),
				self::get_user_cache_key($user_id, $token),
			];
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->esc_like('ekit_insta_posts_') . '%',
				$wpdb->esc_like('ekit_insta_user_info_') . '%',
				$wpdb->esc_like('_transient_ekit_instagram_cached_data_') . '%',
				$wpdb->esc_like('_transient_ekit_insta_cache_user_info__') . '%'
			)
		);

		$removed = 0;

		foreach((array) $rows as $option_name) {

			// The response is a plain option now; older versions wrote transients.
			$is_transient = (strpos($option_name, '_transient_') === 0);
			$name         = $is_transient ? substr($option_name, strlen('_transient_')) : $option_name;

			if(in_array($name, $keep, true)) {
				continue;
			}

			$gone = $is_transient ? delete_transient($name) : delete_option($name);

			if($gone) {
				$removed++;
			}
		}

		return $removed;
	}


	public static function get_instagram_feed_from_API() {

		return self::get_cached_data();
	}


	static function get_cached_data() {

		$data = Attr::instance()->utils->get_option('user_data', []);

		$user_id = empty($data['instragram']['user_id']) ? '' : $data['instragram']['user_id'];
		$token   = empty($data['instragram']['token']) ? '' : $data['instragram']['token'];

		if(empty($user_id)) {
			return self::error_response(__('User id is not set yet! Please go to settings and set the user id.', 'elementskit'));
		}

		if(empty($token)) {
			return self::error_response(__('Access token is not set yet! Please go to settings and set the access token.', 'elementskit'));
		}

		$cached = self::get_cache(self::get_posts_cache_key($user_id, $token), self::get_transient_key($user_id));

		if(false !== $cached) {
			return $cached;
		}

		$feed = self::call_api($token);

		if(is_array($feed) && !empty($feed)) {

			self::save_cache(self::get_posts_cache_key($user_id, $token), $feed);

			// A new user id or token means new keys, so what the previous pair left
			// behind is dead weight in the options table now.
			self::purge_stale_cache($user_id, $token);
		}

		return $feed;
	}


	public static function get_user_info() {

		$data = Attr::instance()->utils->get_option('user_data', []);

		$user_id = empty($data['instragram']['user_id']) ? '' : $data['instragram']['user_id'];
		$token   = empty($data['instragram']['token']) ? '' : $data['instragram']['token'];

		if(empty($token) || empty($user_id)) {
			return null;
		}

		$cached = self::get_cache(self::get_user_cache_key($user_id, $token), self::get_transient_key_for_user($user_id));

		if(false !== $cached) {
			return $cached;
		}

		$info = self::call_api_for_user_details($user_id, $token);

		// Same rule as the feed: an error response is never stored.
		if(is_object($info) && !empty($info->username)) {
			self::save_cache(self::get_user_cache_key($user_id, $token), $info);
		}

		return $info;
	}


	public static function call_api($access_token) {

		$url = self::$graph_url . self::$api_version . '/me/media?fields=id,media_type,media_url,thumbnail_url,permalink,username,caption,timestamp,like_count,comments_count,children{media_type,media_url,thumbnail_url}&limit=' . self::MAX_POSTS . '&access_token=' . $access_token;

		$response = wp_remote_get($url, ['timeout' => 15]);

		if(is_wp_error($response)) {
			return self::error_response($response->get_error_message());
		}

		$body = json_decode(wp_remote_retrieve_body($response));

		// A dead or unauthorised token is reported in the body, and that message is
		// far more useful to whoever set the token up than the status code alone.
		if(is_object($body) && !empty($body->error)) {
			return self::error_response(empty($body->error->message) ? __('Instagram API error', 'elementskit') : $body->error->message);
		}

		if(200 !== wp_remote_retrieve_response_code($response)) {
			return self::error_response(__('Instagram API request failed!', 'elementskit'));
		}

		if(!is_object($body) || !isset($body->data) || !is_array($body->data)) {
			return self::error_response(__('Instagram API returned an unexpected response!', 'elementskit'));
		}

		if(empty($body->data)) {
			return self::error_response(__('0 posts returned!', 'elementskit'));
		}

		return $body->data;
	}


	public static function call_api_for_user_details($uid, $token) {

		$url = self::$graph_url . self::$api_version . '/' . $uid . '?fields=username,profile_picture_url&access_token=' . $token;

		$response = wp_remote_get($url, ['timeout' => 15]);

		if(is_wp_error($response)) {
			return null;
		}

		$body = json_decode(wp_remote_retrieve_body($response));

		return is_object($body) ? $body : null;
	}


	protected static function error_response($message) {

		$ret = new \stdClass();
		$msg = new \stdClass();

		$msg->message = $message;
		$ret->error   = $msg;

		return $ret;
	}
}
