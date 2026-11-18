<?php

namespace Elementor;

use ElementsKit_Lite\Libs\Framework\Attr;

defined('ABSPATH') || exit;

class ElementsKit_Widget_Facebook_Review_Handler extends \ElementsKit_Lite\Core\Handler_Widget {

	private static $fb_base_url = 'https://www.facebook.com/';
	private static $fb_graph_url = 'https://graph.facebook.com/';
	private static $fb_api_version = 'v25.0';
	public static $ok_fbf_info_cache = 'ekit_fb_page_info_cache_';

	/**
	 * Successful responses are kept until the cache is cleared by hand, so the
	 * Graph API is hit as rarely as possible. Failures are kept for a short
	 * while only, so a broken token does not mean a call on every page view.
	 */
	const FAILURE_CACHE = 300; // 5 minutes


	public function wp_init() {

		(new \ElementsKit\Widgets\Facebook_Review\Facebook_Review_Api());
	}

	/**
	 * A review card carries a single image, so this widget downloads fewer files
	 * per page load than the feed does.
	 */
	public static function media_cache() {

		return \ElementsKit\Helpers\Media_Cache::instance('facebook-review', [
			'hosts'       => ['fbcdn.net', 'facebook.com', 'akamaihd.net'],
			'per_request' => 10,
			'timeout'     => 10,
		]);
	}

	static function get_name() {
		return 'elementskit-facebook-review';
	}


	static function get_title() {
		return esc_html__('Facebook review', 'elementskit');
	}


	static function get_icon() {
		return ' ekit-widget-icon eicon-button';
	}


	static function get_categories() {
		return ['elementskit'];
	}

	static function get_keywords() {
		return ['ekit', 'review', 'fb', 'facebook', 'social review'];
	}

	static function get_dir() {
		return \ElementsKit::widget_dir() . 'facebook-review/';
	}


	static function get_url() {
		return \ElementsKit::widget_url() . 'facebook-review/';
	}


	public static function get_fbp_review_url($page_id, $pg_token) {

		$url  = self::$fb_graph_url . self::$fb_api_version . '/' . $page_id . '/ratings';
		$args = '?access_token=' . $pg_token;

		// Facebook no longer returns the reviewer identity on this edge, so it is
		// not requested any more. What follows is everything that comes back.
		$args .= '&fields=recommendation_type,created_time,review_text,has_rating,has_review';

		return $url . $args;
	}


	public static function get_fbp_page_info_url($page_id, $page_access_tok) {

		$url_acc = self::$fb_graph_url . self::$fb_api_version . '/' . $page_id;
		$args    = '?access_token=' . $page_access_tok;

		$fld[] = 'followers_count';
		$fld[] = 'fan_count';
		$fld[] = 'rating_count';
		$fld[] = 'overall_star_rating';

		$args .= '&fields=id,name,page_token,engagement,picture,' . implode(',', $fld);

		return $url_acc . $args;
	}


	public static function get_fbp_review_trans_key($page_id, $token) {

		$md = md5($page_id . $token);

		return '_trans_ekit_fbp_review_review_' . $md;
	}


	public static function get_fbf_overall_trans_key($page_id, $token) {

		$md = md5($page_id . $token);

		return '_trans_ekit_fbp_review_pg_info_' . $md;
	}


	public static function get_fbp_fail_trans_key($page_id, $token, $what) {

		$md = md5($page_id . $token);

		return '_trans_ekit_fbp_review_fail_' . $what . '_' . $md;
	}

	/**
	 * A token failure looks the same on every call, so it is worth telling apart
	 * from an ordinary API error: it is the one worth reporting to the owner.
	 */
	public static function is_fb_token_error($result) {

		if(!is_object($result) || empty($result->error) || !is_object($result->error)) {
			return false;
		}

		$code    = isset($result->error->code) ? intval($result->error->code) : 0;
		$type    = isset($result->error->type) ? (string) $result->error->type : '';
		$subcode = isset($result->error->error_subcode) ? intval($result->error->error_subcode) : 0;

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

	/**
	 * Facebook image URLs expire, so they are mirrored into the uploads folder.
	 * The widget keeps working on its own when the mirror is unavailable.
	 */
	public static function cached_img_url($url) {

		return self::media_cache()->url($url);
	}


	public static function flush_image_cache() {

		return self::media_cache()->flush();
	}

	/**
	 * The API responses are kept in plain options rather than transients. A
	 * transient without an expiry is autoloaded, so WordPress would read the
	 * whole payload on every request of the site; this way it is read only
	 * while the widget renders.
	 */
	public static function get_reviews_cache($page_id, $token) {

		return self::read_cache(self::get_fbp_review_trans_key($page_id, $token));
	}


	public static function save_reviews_cache($page_id, $token, $reviews) {

		return update_option(self::get_fbp_review_trans_key($page_id, $token), $reviews, false);
	}


	public static function delete_reviews_cache($page_id, $token) {

		return self::forget_cache(self::get_fbp_review_trans_key($page_id, $token));
	}


	public static function get_page_info_cache($page_id, $token) {

		return self::read_cache(self::get_fbf_overall_trans_key($page_id, $token));
	}


	public static function save_page_info_cache($page_id, $token, $info) {

		return update_option(self::get_fbf_overall_trans_key($page_id, $token), $info, false);
	}


	public static function delete_page_info_cache($page_id, $token) {

		return self::forget_cache(self::get_fbf_overall_trans_key($page_id, $token));
	}


	/**
	 * Reads the option, falling back to the transient an older version of the
	 * widget wrote. Anything found there is moved over and the transient goes.
	 */
	protected static function read_cache($key) {

		$value = get_option($key, '');

		if('' === $value || false === $value) {

			$value = get_transient($key);

			if(!empty($value)) {
				update_option($key, $value, false);
				delete_transient($key);
			}
		}

		return empty($value) ? false : $value;
	}


	protected static function forget_cache($key) {

		$gone = delete_option($key);

		return delete_transient($key) || $gone;
	}


	/**
	 * Drop the review caches that belong to an older page id or token.
	 *
	 * The keys carry the page id and the token, so saving a new key in the
	 * settings leaves the previous set behind for good. Only this widget's own
	 * keys are touched, never the ones belonging to another widget.
	 */
	public static function purge_stale_caches($page_id, $token) {

		global $wpdb;

		$keep = [
			self::get_fbp_review_trans_key($page_id, $token),
			self::get_fbf_overall_trans_key($page_id, $token),
			self::get_fbp_fail_trans_key($page_id, $token, 'pg_info'),
			self::get_fbp_fail_trans_key($page_id, $token, 'reviews'),
		];

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->esc_like('_transient__trans_ekit_fbp_review_') . '%',
				$wpdb->esc_like('_trans_ekit_fbp_review_') . '%'
			)
		);

		$removed = 0;

		foreach((array) $rows as $option_name) {

			// The responses are plain options now, only the failures are transients.
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


	protected static function remember_failure($fail_key, $msg, $token_error = false) {

		$failure = [
			'success'     => false,
			'msg'         => $msg,
			'token_error' => $token_error,
		];

		set_transient($fail_key, $failure, self::FAILURE_CACHE);

		return $failure;
	}


	public static function get_fb_page_overall_rating($page_id, $pg_acc_token) {

		$trans_val = self::get_page_info_cache($page_id, $pg_acc_token);

		if(false !== $trans_val) {

			return array(
				'success'     => true,
				'msg'         => 'Fetched from cached api call',
				'dt'          => $trans_val,
				'token_error' => false,
			);
		}

		$fail_key = self::get_fbp_fail_trans_key($page_id, $pg_acc_token, 'pg_info');
		$failed   = get_transient($fail_key);

		if(false !== $failed) {

			return $failed;
		}

		try {

			$url     = self::get_fbp_page_info_url($page_id, $pg_acc_token);
			$request = wp_remote_get($url, ['timeout' => 15]);

			if(is_wp_error($request)) {

				return self::remember_failure(
					$fail_key,
					__('API call failed to retrieve the facebook page info.', 'elementskit') . ' - ' . $request->get_error_message()
				);
			}

			$datum = json_decode(wp_remote_retrieve_body($request));

			if(is_object($datum) && !empty($datum->error)) {

				$msg = empty($datum->error->message)
					? __('API call failed to retrieve the facebook page info.', 'elementskit')
					: $datum->error->message;

				return self::remember_failure($fail_key, $msg, self::is_fb_token_error($datum));
			}

			if(empty($datum->name)) {

				return self::remember_failure(
					$fail_key,
					__('API call failed to retrieve the facebook page info.', 'elementskit')
				);
			}

			// Every field below is optional on the page node, so nothing is assumed.
			$extracted = [
				'rating'   => isset($datum->overall_star_rating) ? $datum->overall_star_rating : 0,
				'count'    => isset($datum->rating_count) ? intval($datum->rating_count) : 0,
				'pg_name'  => $datum->name,
				'pg_id'    => isset($datum->id) ? $datum->id : $page_id,
				'pgt'      => empty($datum->page_token) ? (isset($datum->id) ? $datum->id : $page_id) : $datum->page_token,
				'follower' => isset($datum->followers_count) ? intval($datum->followers_count) : 0,
				'fan'      => isset($datum->fan_count) ? intval($datum->fan_count) : 0,
				'likes'    => empty($datum->engagement->count) ? 0 : intval($datum->engagement->count),
				'picture'  => empty($datum->picture->data->url) ? '' : $datum->picture->data->url,
			];

			// Only the API response is cached. The picture is mirrored while the card
			// renders, so a download that fails once is retried on the next render
			// instead of freezing an expiring Facebook URL into the cache.
			self::save_page_info_cache($page_id, $pg_acc_token, $extracted);
			delete_transient($fail_key);

			// A new page id or token means a new set of keys, so the caches from the
			// previous one are dead weight in the options table now.
			self::purge_stale_caches($page_id, $pg_acc_token);

		} catch(\Exception $ex) {

			return self::remember_failure(
				$fail_key,
				__('API call failed to retrieve the facebook page info.', 'elementskit') . ' - ' . $ex->getMessage()
			);
		}

		return array(
			'success'     => true,
			'msg'         => 'Fetched and cached the calls',
			'dt'          => $extracted,
			'token_error' => false,
		);
	}


	public static function get_fb_reviews($pg_id, $pg_token) {

		$transient_value = self::get_reviews_cache($pg_id, $pg_token);

		if(false !== $transient_value) {

			return $transient_value;
		}

		$fail_key = self::get_fbp_fail_trans_key($pg_id, $pg_token, 'reviews');
		$failed   = get_transient($fail_key);

		if(false !== $failed) {

			return $failed;
		}

		try {

			$url     = self::get_fbp_review_url($pg_id, $pg_token);
			$request = wp_remote_get($url, ['timeout' => 15]);

			if(is_wp_error($request)) {

				return self::remember_review_failure($fail_key, $request->get_error_message());
			}

			$result = json_decode(wp_remote_retrieve_body($request));

			// Only a good response is worth keeping. Caching an error used to keep
			// the widget broken long after the token had been fixed.
			if(is_object($result) && !empty($result->error)) {

				$msg = empty($result->error->message) ? __('Facebook API error', 'elementskit') : $result->error->message;

				return self::remember_review_failure($fail_key, $msg, $result->error);
			}

			if(!is_object($result) || !isset($result->data)) {

				return self::remember_review_failure($fail_key, __('Facebook API returned no review data.', 'elementskit'));
			}

			self::save_reviews_cache($pg_id, $pg_token, $result);
			delete_transient($fail_key);

			return $result;

		} catch(\Exception $ex) {

			return self::remember_review_failure($fail_key, $ex->getMessage());
		}
	}


	/**
	 * Reviews reach the markup as an object, so a failure has to keep the same
	 * shape: an object carrying ->error->message.
	 */
	protected static function remember_review_failure($fail_key, $msg, $error = null) {

		$failure = new \stdClass();

		$failure->error          = new \stdClass();
		$failure->error->message = $msg;
		$failure->error->type    = isset($error->type) ? $error->type : 'ApiError';
		$failure->error->code    = isset($error->code) ? $error->code : 0;

		if(isset($error->error_subcode)) {
			$failure->error->error_subcode = $error->error_subcode;
		}

		set_transient($fail_key, $failure, self::FAILURE_CACHE);

		return $failure;
	}


	public static function get_data() {

		$data = Attr::instance()->utils->get_option('user_data', []);

		$pg_token = empty($data['fbp_review']['pg_token']) ? '' : $data['fbp_review']['pg_token'];
		$page_id  = empty($data['fbp_review']['pg_id']) ? '' : $data['fbp_review']['pg_id'];


		return [
			'pg_id'  => $page_id,
			'pg_tok' => $pg_token,
		];
	}
}
