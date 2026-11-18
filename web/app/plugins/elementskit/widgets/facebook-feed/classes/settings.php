<?php

/**
 * Facebook Feed Settings and API Handler
 *
 * CACHING STRATEGY:
 * All transients use unlimited expiration (0 seconds) to minimize Facebook Graph API
 * calls and avoid rate limiting. Cached data persists indefinitely until manually cleared.
 *
 * To clear caches:
 * - Use the cache clearing API: POST to /wp-json/elementskit/v1/widget/fb-feed/remove-cache
 * - Or manually delete transients using WordPress functions
 *
 * Only successful API responses are cached; errors are never cached.
 */
class Ekit_facebook_settings {

	public $ekit_fb_account_id = '';
	/**
	 * Access token app id
	 */
	public $ekit_fb_app_id = '';

	/**
	 * Access token secret key
	 */
	public $ekit_fb_app_secret = '';

	/**
	 * Access token secret key
	 */

	protected static $fb_graph_url = 'https://graph.facebook.com/';

	/**
	 * How many posts are fetched. The response is shared by every widget on the
	 * site, so it always holds the most this edge returns and each widget shows
	 * as many of them as its own setting allows. Anything else would make the
	 * first widget to render decide the post count for all the others.
	 */
	const MAX_POSTS = 25;

	/**
	 * Remembers that the markup rows this widget no longer writes have been
	 * removed. A plain option, never autoloaded: it is read once, while the
	 * plugin is being updated.
	 */
	const CLEANUP_FLAG = 'ekit_fbf_markup_cache_cleanup';
	protected static $fb_api_version = 'v25.0';

	public $ekit_fb_app_access_token = '';

	private $user_app_id;
	private $user_app_key;
	private $user_id;
	private $access_key;
	private $page_id;


	private $fileds = [
		'id',
		'message',
		'created_time',
	];


	public function setup(array $config) {

		$this->user_id    = empty($config['pg_id']) ? '' : $config['pg_id'];
		$this->page_id    = empty($config['pg_id']) ? '' : $config['pg_id'];
		$this->access_key = empty($config['pg_tok']) ? '' : $config['pg_tok'];
	}


	public function getToken() {
		return $this->access_key;

	}


	public static function get_me_call_transient_key($token = 'nothing') {

		return 'ekit_fb_feeds_me_' . md5($token);
	}


	public static function get_fb_user_feed_call_transient_key($user_id) {

		return 'ekit_fbf_call_' . $user_id;
	}


	public static function get_fb_pg_post_call_transient_key($page_id, $tok) {

		$md = md5($page_id . $tok);

		return 'ekit_fbf_pg_post_call_' . $md;
	}

	/**
	 * The posts response is kept in a plain option rather than a transient. A
	 * transient without an expiry is autoloaded, so the payload would be read on
	 * every request of the site; this way it is read only when the feed renders.
	 */
	public static function get_posts_cache($page_id, $tok) {

		$key   = self::get_fb_pg_post_call_transient_key($page_id, $tok);
		$posts = get_option($key, '');

		if('' === $posts || false === $posts) {

			// Response saved by an older version still lives in a transient.
			$posts = get_transient($key);

			if(false !== $posts && !empty($posts)) {
				self::save_posts_cache($page_id, $tok, $posts);
				delete_transient($key);
			}
		}

		return empty($posts) ? false : $posts;
	}


	public static function save_posts_cache($page_id, $tok, $posts) {

		return update_option(self::get_fb_pg_post_call_transient_key($page_id, $tok), $posts, false);
	}


	public static function delete_posts_cache($page_id, $tok) {

		$key  = self::get_fb_pg_post_call_transient_key($page_id, $tok);
		$gone = delete_option($key);

		return delete_transient($key) || $gone;
	}


	/**
	 * The rendered feed used to be cached as well. Nothing reads those rows any
	 * more, and the ones written as a transient are autoloaded, so they would be
	 * read on every request of the site for no reason at all.
	 *
	 * Only sites that rendered the widget at least once have any.
	 */
	public static function delete_legacy_markup_cache() {

		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->esc_like('_transient_ekit_fbf_pg_markup_') . '%',
				$wpdb->esc_like('ekit_fbf_pg_markup_') . '%'
			)
		);

		$removed = 0;

		foreach((array) $rows as $option_name) {

			// The oldest version stored it as a transient, the next one as an option.
			$is_transient = (strpos($option_name, '_transient_') === 0);
			$name         = $is_transient ? substr($option_name, strlen('_transient_')) : $option_name;

			$gone = $is_transient ? delete_transient($name) : delete_option($name);

			if($gone) {
				$removed++;
			}
		}

		return $removed;
	}


	/**
	 * Drop the feed caches that belong to an older page id or token.
	 *
	 * A transient stored without an expiry is an autoloaded option, so every
	 * cache left behind by a key change would be read on every request of the
	 * whole site, for good. Saving a fresh feed is the moment to clear them.
	 * Only this widget's own keys are touched.
	 */
	public static function purge_stale_transients($page_id, $token) {

		global $wpdb;

		$keep = [
			self::CLEANUP_FLAG,
			self::get_fb_pg_post_call_transient_key($page_id, $token),
			self::get_fb_pg_call_transient_key(md5($token . $page_id)),
			self::get_fb_pg_call_transient_key($token),
			self::get_me_call_transient_key($token),
			self::get_fb_user_feed_call_transient_key($page_id),
		];

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->esc_like('_transient_ekit_fbf_') . '%',
				$wpdb->esc_like('_transient_ekit_fb_feeds_me_') . '%',
				$wpdb->esc_like('ekit_fbf_') . '%'
			)
		);

		$removed = 0;

		foreach((array) $rows as $option_name) {

			// The posts payload is a plain option, the rest are transients. Markup
			// rows written by an earlier version get swept up here as well.
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


	protected static function is_fb_token_error($result) {
		if(!is_object($result) || empty($result->error) || !is_object($result->error)) {
			return false;
		}

		$code = isset($result->error->code) ? intval($result->error->code) : 0;
		$type = isset($result->error->type) ? (string) $result->error->type : '';
		$subcode = isset($result->error->error_subcode) ? intval($result->error->error_subcode) : 0;

		// Common token/auth failures are OAuthException/code 190, often with subcodes 463/467.
		if($code === 190) {
			return true;
		}
		if(stripos($type, 'OAuth') !== false) {
			return true;
		}
		if(in_array($subcode, [463, 467], true)) {
			return true;
		}

		return false;
	}


	public static function get_fb_pg_call_transient_key($token = 'nothing') {

		return 'ekit_fbf_page_info_' . md5($token);
	}


	public static function get_fb_feed_url($user_id, $ac_token, $limit = 5, $fld = '') {

		$url  = self::$fb_graph_url . self::$fb_api_version . '/' . $user_id . '/feed';
		$args = '?access_token=' . $ac_token . '&limit=' . $limit . '&fields=' . $fld;

		return $url . $args;
	}


	public static function get_fb_page_posts_url($page_id, $pg_token, $fld = '', $limit = 10, $edge = 'published_posts') {

		$edge = empty($edge) ? 'published_posts' : $edge;
		$limit = empty($limit) ? self::MAX_POSTS : intval($limit);
		if($limit > self::MAX_POSTS) {
			$limit = self::MAX_POSTS;
		}

		$url  = self::$fb_graph_url . self::$fb_api_version . '/' . $page_id . '/' . $edge;
		$args = '?access_token=' . $pg_token . '&limit=' . $limit . '&fields=' . $fld;

		return $url . $args;
	}

	/**
	 * @param $page_id
	 * @param string $type - small, normal, album, large, square
	 * @return string
	 */
	public static function get_fb_page_profile_pic_url($page_id, $type = 'normal') {

		$url  = self::$fb_graph_url . self::$fb_api_version . '/' . $page_id . '/picture';
		$args = '?type='.$type;

		return $url . $args;
	}


	public function get_fb_user_feed($user_id, $limit = 5, $fields = []) {

		$token           = $this->access_key;
		$transient_name  = self::get_fb_user_feed_call_transient_key($user_id);
		$transient_value = get_transient($transient_name);

		if(false !== $transient_value) {

			return [
				'success' => true,
				'msg'     => __('Fetched from cache', 'elementskit'),
				'param'   => $transient_value,
			];
		}

		try {

			if(empty($fields)) {
				$fld = implode(',', $this->fileds);
			} else {
				$fld = implode(',', $fields);
			}

			$url     = self::get_fb_feed_url($user_id, $token, $limit, $fld);
			$request = wp_remote_get($url);

			if(is_wp_error($request)) {

				return [
					'success' => false,
					'msg'     => $request->get_error_message(),
					'param'   => [],
				];
			}

			$body   = wp_remote_retrieve_body($request);
			$result = json_decode($body);

			$expiration_time = 0; // Unlimited cache to minimize Facebook API calls
			set_transient($transient_name, $result, $expiration_time);


		} catch(\Exception $ex) {

			return [
				'success' => false,
				'msg'     => $ex->getMessage(),
				'param'   => [],
			];
		}

		return [
			'success' => true,
			'msg'     => __('Fetched and cached the api call', 'elementskit'),
			'param'   => $result,
		];
	}


	/**
	 * Get user details
	 * mainly id and name
	 *
	 * @return array
	 */
	public function get_fb_me_id() {

		$msg   = __('Successfully retrieved', 'elementskit');
		$token = $this->access_key;

		$transient_name  = self::get_me_call_transient_key($token);
		$transient_value = get_transient($transient_name);

		if(false !== $transient_value) {

			return [
				'success' => true,
				'msg'     => $msg,
				'param'   => $transient_value,
			];
		}


		$url_acc   = self::$fb_graph_url . self::$fb_api_version . '/me';
		$acc_args  = '?access_token=' . $token;
		$extracted = [];

		try {

			$request = wp_remote_get($url_acc . $acc_args);
			$body    = wp_remote_retrieve_body($request);
			$dt      = json_decode($body);

			if(!empty($dt->name)) {
				$extracted['usr_tok']  = $token;
				$extracted['usr_name'] = $dt->name;
				$extracted['usr_id']   = $dt->id;

				$expiration_time = 0; // Unlimited cache to minimize Facebook API calls

				set_transient($transient_name, $extracted, $expiration_time);
			}

		} catch(\Exception $ex) {

			$msg = $ex->getMessage();
			$dt  = 'Nothing....';
		}


		return [
			'success' => true,
			'msg'     => $msg,
			'param'   => $extracted,
		];
	}


	public function get_fb_page_id_by_token($page_token) {

		$msg   = __('Successfully retrieved', 'elementskit');
		$token = $page_token;

		$trans_name  = self::get_fb_pg_call_transient_key($token);
		$trans_value = get_transient($trans_name);

		if(false !== $trans_value) {

			return [
				'success' => true,
				'msg'     => $msg,
				'param'   => $trans_value,
			];
		}


		$url_acc   = self::$fb_graph_url . self::$fb_api_version . '/me';
		$acc_args  = '?access_token=' . $token;
		$extracted = [];

		try {

			$request = wp_remote_get($url_acc . $acc_args);
			$body    = wp_remote_retrieve_body($request);
			$dt      = json_decode($body);

			if(!empty($dt->name)) {
				$extracted['pg_tok']  = $token;
				$extracted['pg_name'] = $dt->name;
				$extracted['pg_id']   = $dt->id;

				$expiration_time = 0; // Unlimited cache to minimize Facebook API calls

				set_transient($trans_name, $extracted, $expiration_time);
			}

		} catch(\Exception $ex) {

			$msg = $ex->getMessage();
			$dt  = 'Nothing....';
		}


		return [
			'success' => true,
			'msg'     => $msg,
			'param'   => $extracted,
		];
	}


	public function verify_fb_page_and_token() {

		$msg     = __('Successfully retrieved', 'elementskit');
		$token   = $this->access_key;
		$page_id = $this->page_id;

		$hash        = md5($token . $this->page_id);
		$trans_name  = self::get_fb_pg_call_transient_key($hash);
		$trans_value = get_transient($trans_name);

		if(false !== $trans_value) {

			return [
				'success' => true,
				'msg'     => $msg,
				'param'   => $trans_value,
			];
		}


		$url_acc   = self::$fb_graph_url . self::$fb_api_version . '/' . $page_id;
		$acc_args  = '?access_token=' . $token;
		$extracted = [];

		try {

			$request = wp_remote_get($url_acc . $acc_args);
			$body    = wp_remote_retrieve_body($request);
			$dt      = json_decode($body);

			if(!empty($dt->name)) {
				$extracted['pg_tok']  = $token;
				$extracted['pg_name'] = $dt->name;
				$extracted['pg_id']   = $dt->id;

				$expiration_time = 0; // Unlimited cache to minimize Facebook API calls

				set_transient($trans_name, $extracted, $expiration_time);
			}

		} catch(\Exception $ex) {

			$msg = $ex->getMessage();
			$dt  = 'Nothing....';
		}


		return [
			'success' => true,
			'msg'     => $msg,
			'param'   => $extracted,
		];
	}


	public function get_fb_page_posts() {

		$token       = $this->access_key;
		$page_id     = $this->page_id;
		$trans_name  = self::get_fb_pg_post_call_transient_key($page_id, $token);
		$trans_value = self::get_posts_cache($page_id, $token);

		if(false !== $trans_value) {

			return [
				'success' => true,
				'msg'     => __('Fetched from cache', 'elementskit'),
				'param'   => $trans_value,
			];
		}

		try {

			$num = self::MAX_POSTS;

			// Fields used in widget rendering, richest set first. When the node or the
			// token rejects an optional field (error #100), the reduced set is tried.
			$field_sets = [
				'id,message,created_time,permalink_url,full_picture,attachments{media,url,subattachments,type},shares,reactions.summary(total_count),comments.summary(total_count),from{id,name,picture{url}}',
				'id,message,created_time,permalink_url,full_picture,attachments{media,url,subattachments,type}',
			];

			// Prefer published posts edge for page-owned content; the remaining edges are
			// tried when the token or node does not expose it.
			$edges    = ['published_posts', 'posts', 'feed'];
			$result   = null;
			$last_msg = '';
			$last_err = null;

			foreach($edges as $edge) {
				foreach($field_sets as $fld) {

					$url     = self::get_fb_page_posts_url($page_id, $token, $fld, $num, $edge);
					$request = wp_remote_get($url, [
						'timeout' => 15,
					]);

					if(is_wp_error($request)) {
						$last_msg = $request->get_error_message();
						continue;
					}

					$body     = wp_remote_retrieve_body($request);
					$response = json_decode($body);

					if(is_object($response) && !empty($response->error)) {

						// A bad token fails the same way on every edge, so stop right here.
						if(self::is_fb_token_error($response)) {
							return [
								'success'     => false,
								'msg'         => empty($response->error->message) ? __('Facebook API error', 'elementskit') : $response->error->message,
								'param'       => $response,
								'token_error' => true,
							];
						}

						$last_err = $response;
						$last_msg = empty($response->error->message) ? __('Facebook API error', 'elementskit') : $response->error->message;
						continue;
					}

					if(is_object($response) && isset($response->data) && is_array($response->data)) {

						$result = $response;

						if(count($response->data) > 0) {
							break 2;
						}
					}
				}
			}

			if(empty($result)) {
				return [
					'success'     => false,
					'msg'         => empty($last_msg) ? __('Facebook API request failed', 'elementskit') : $last_msg,
					'param'       => empty($last_err) ? [] : $last_err,
					'token_error' => false,
				];
			}

			// An empty feed on every edge usually means the page id does not belong to the
			// token. Report it instead of caching an empty response forever.
			if(empty($result->data)) {
				return [
					'success'     => false,
					'msg'         => __('No posts were returned. Check that the Page ID belongs to the same page as the page access token.', 'elementskit'),
					'param'       => [],
					'token_error' => false,
				];
			}

			// Cache only non-error responses. Kept until the cache is cleared, and out
			// of autoload so the payload is not read on every request of the site.
			self::save_posts_cache($page_id, $token, $result);

			// A new page id or token means a new set of keys, so the caches from the
			// previous one are dead weight in the options table now.
			self::purge_stale_transients($page_id, $token);

		} catch(\Exception $ex) {

			return [
				'success' => false,
				'msg'     => $ex->getMessage(),
				'param'   => [],
				'token_error' => false,
			];
		}

		return [
			'success' => true,
			'msg'     => __('Fetched and cached the api call', 'elementskit'),
			'param'   => $result,
			'token_error' => false,
		];
	}

	/**
	 * @param mixed $access_key
	 */
	public function setAccessKey($access_key) {
		$this->access_key = $access_key;
	}
}
