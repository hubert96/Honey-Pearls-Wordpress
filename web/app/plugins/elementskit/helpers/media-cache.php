<?php

namespace ElementsKit\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Local mirror for the signed CDN media the social widgets render.
 *
 * Facebook and Instagram hand back media URLs whose signature lives in the
 * oh/oe query parameters and stops resolving after a while, so a widget
 * rendered from a stored API response ends up pointing at dead files. Every
 * file is copied into wp-content/uploads/ the first time it is rendered and
 * served from there afterwards.
 *
 * The class knows nothing about any particular widget: each one asks for its
 * own instance and passes the hosts it accepts and the limits it wants.
 *
 *     $cache = \ElementsKit\Helpers\Media_Cache::instance('instagram-feed', ['hosts' => [...]]);
 *     $local = $cache->url($remote_url);
 *
 * Files are removed by each widget's cache clearing API, together with the
 * stored response they belong to.
 */
class Media_Cache {

	const MAX_FILE_SIZE = 5242880; // 5 MB

	// A post can be a video, and those are heavier than a photo.
	const MAX_VIDEO_SIZE = 20971520; // 20 MB

	// Downloads happen while rendering, so keep a single request bounded.
	const MAX_PER_REQUEST = 30;
	const TIME_BUDGET     = 10;
	const TIMEOUT         = 15;

	/**
	 * One instance per name, so the per request budget below is counted per
	 * widget rather than shared between them.
	 */
	protected static $instances = [];

	protected $sub_dir;
	protected $hosts;
	protected $video;
	protected $per_request;
	protected $time_budget;
	protected $timeout;

	protected $downloads = 0;
	protected $started   = 0;

	/**
	 * Configuration keys, all optional but `hosts`:
	 *
	 *   hosts        array  host names media is accepted from, with their subdomains
	 *   video        bool   store mp4 as well as images (default false)
	 *   sub_dir      string folder under uploads (default elementskit/<name>)
	 *   per_request  int    how many files one page load may download
	 *   time_budget  int    how many seconds one page load may spend downloading
	 *   timeout      int    per download timeout
	 */
	public static function instance( $name, array $config = [] ) {

		$name = (string) $name;

		if ( ! isset( self::$instances[ $name ] ) ) {
			self::$instances[ $name ] = new static( $name, $config );
		}

		return self::$instances[ $name ];
	}


	protected function __construct( $name, array $config ) {

		$this->sub_dir     = empty( $config['sub_dir'] ) ? 'elementskit/' . $name : $config['sub_dir'];
		$this->hosts       = empty( $config['hosts'] ) ? [] : (array) $config['hosts'];
		$this->video       = ! empty( $config['video'] );
		$this->per_request = empty( $config['per_request'] ) ? self::MAX_PER_REQUEST : (int) $config['per_request'];
		$this->time_budget = empty( $config['time_budget'] ) ? self::TIME_BUDGET : (int) $config['time_budget'];
		$this->timeout     = empty( $config['timeout'] ) ? self::TIMEOUT : (int) $config['timeout'];
	}


	/**
	 * Local URL for a media file, downloading it on first use.
	 * Returns the original URL when the file cannot be stored.
	 */
	public function url( $remote_url ) {

		$remote_url = trim( (string) $remote_url );

		if ( empty( $remote_url ) || ! $this->is_cacheable( $remote_url ) ) {
			return $remote_url;
		}

		$key      = $this->cache_key( $remote_url );
		$existing = $this->find_cached( $key );

		if ( ! empty( $existing ) ) {
			return $existing;
		}

		$stored = $this->download( $remote_url, $key );

		// Nothing could be stored, so the page keeps the remote URL for now.
		return empty( $stored ) ? $remote_url : $stored;
	}


	public function flush() {

		$dir = $this->base_dir();

		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			return false;
		}

		$files = glob( trailingslashit( $dir ) . '*' );

		if ( empty( $files ) ) {
			return false;
		}

		$removed = false;

		foreach ( $files as $file ) {
			if ( is_file( $file ) && wp_delete_file( $file ) !== false ) {
				$removed = true;
			}
		}

		return $removed;
	}


	protected function is_cacheable( $url ) {

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], [ 'http', 'https' ], true ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		foreach ( $this->hosts as $allowed ) {
			if ( $host === $allowed || substr( $host, - ( strlen( $allowed ) + 1 ) ) === '.' . $allowed ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Key built from the parts of the URL that identify the file. The signature
	 * and cache busting parameters change on every API call, so keeping them
	 * would store the same picture again after each refresh.
	 */
	protected function cache_key( $url ) {

		$parts = wp_parse_url( $url );
		$path  = empty( $parts['path'] ) ? $url : $parts['path'];
		$keep  = [];

		if ( ! empty( $parts['query'] ) ) {

			$query = [];
			wp_parse_str( $parts['query'], $query );

			foreach ( $query as $name => $value ) {
				// _nc_*, oh, oe and friends are the expiring signature bits.
				if ( strpos( $name, '_nc' ) === 0 || in_array( $name, [ 'oh', 'oe', 'ccb', 'stp', 'efg', 'edm', 'cb', 'ig_cache_key' ], true ) ) {
					continue;
				}

				$keep[ $name ] = $value;
			}

			ksort( $keep );
		}

		return md5( $path . ( empty( $keep ) ? '' : '?' . http_build_query( $keep ) ) );
	}


	protected function extensions() {

		$extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];

		if ( $this->video ) {
			$extensions[] = 'mp4';
		}

		return $extensions;
	}


	protected function base_dir() {

		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['basedir'] ) . $this->sub_dir;
	}


	protected function base_url() {

		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}

		return trailingslashit( $uploads['baseurl'] ) . $this->sub_dir;
	}


	protected function find_cached( $key ) {

		$dir = $this->base_dir();

		if ( empty( $dir ) ) {
			return '';
		}

		foreach ( $this->extensions() as $ext ) {
			if ( file_exists( trailingslashit( $dir ) . $key . '.' . $ext ) ) {
				return trailingslashit( $this->base_url() ) . $key . '.' . $ext;
			}
		}

		return '';
	}


	protected function download( $url, $key ) {

		if ( $this->started === 0 ) {
			$this->started = time();
		}

		if ( $this->downloads >= $this->per_request || ( time() - $this->started ) >= $this->time_budget ) {
			return '';
		}

		$dir = $this->base_dir();

		if ( empty( $dir ) || ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		$this->downloads++;

		$request = wp_remote_get(
			$url,
			[
				'timeout'  => $this->timeout,
				'blocking' => true,
			]
		);

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $request );

		if ( empty( $body ) ) {
			return '';
		}

		$ext = $this->resolve_extension( $body, wp_remote_retrieve_header( $request, 'content-type' ) );

		if ( '' === $ext ) {
			return '';
		}

		$limit = ( 'mp4' === $ext ) ? self::MAX_VIDEO_SIZE : self::MAX_FILE_SIZE;

		if ( strlen( $body ) > $limit ) {
			return '';
		}

		$file = trailingslashit( $dir ) . $key . '.' . $ext;

		if ( file_put_contents( $file, $body ) === false ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return '';
		}

		$this->protect_dir( $dir );

		return trailingslashit( $this->base_url() ) . $key . '.' . $ext;
	}

	/**
	 * An expired signature is answered with an HTML page, so the bytes are
	 * confirmed to be media before anything is written.
	 */
	protected function resolve_extension( $body, $content_type ) {

		$info = @getimagesizefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! empty( $info ) && ! empty( $info[2] ) ) {

			$ext = ltrim( (string) image_type_to_extension( $info[2], true ), '.' );
			$ext = ( 'jpeg' === $ext ) ? 'jpg' : $ext;

			return in_array( $ext, $this->extensions(), true ) ? $ext : '';
		}

		if ( ! $this->video ) {
			return '';
		}

		// A video post stores its mp4 the same way, so the player keeps working
		// once the signed URL behind it has expired.
		$content_type = strtolower( (string) ( is_array( $content_type ) ? reset( $content_type ) : $content_type ) );

		if ( strpos( $content_type, 'video/mp4' ) === 0 && strpos( $body, 'ftyp' ) !== false ) {
			return 'mp4';
		}

		return '';
	}


	protected function protect_dir( $dir ) {

		$index = trailingslashit( $dir ) . 'index.html';

		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
