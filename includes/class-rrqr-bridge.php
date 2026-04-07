<?php
/**
 * NBA CDN bridge: local cache populated by an external fetcher, REST ingest.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridge cache and REST API for off-server NBA fetches.
 */
class RRQR_Bridge {

	const OPTION_ENABLED    = 'rrqr_bridge_enabled';
	const OPTION_ADMIN_ONLY = 'rrqr_bridge_admin_only';
	const OPTION_SECRET     = 'rrqr_bridge_secret';

	/**
	 * Register REST routes.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'rrqr/v1',
			'/bridge',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_ingest' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
			)
		);
	}

	/**
	 * REST permission: Bearer token matches secret.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function rest_permission( $request ) {
		$secret = self::get_secret();
		if ( '' === $secret ) {
			return new WP_Error(
				'rrqr_bridge_no_secret',
				__( 'Bridge ingest is disabled until a secret is configured.', 'rrqr' ),
				array( 'status' => 403 )
			);
		}

		$auth = $request->get_header( 'authorization' );
		if ( ! is_string( $auth ) || ! preg_match( '/^\s*Bearer\s+(.+)$/i', $auth, $m ) ) {
			return new WP_Error(
				'rrqr_bridge_auth',
				__( 'Missing or invalid Authorization header.', 'rrqr' ),
				array( 'status' => 401 )
			);
		}

		$token = trim( $m[1] );
		if ( ! hash_equals( $secret, $token ) ) {
			return new WP_Error(
				'rrqr_bridge_auth',
				__( 'Invalid token.', 'rrqr' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Ingest one file: JSON body with path + body (raw string or JSON value).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_ingest( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error(
				'rrqr_bridge_bad_json',
				__( 'Expected JSON object with path and body.', 'rrqr' ),
				array( 'status' => 400 )
			);
		}

		$rel = isset( $params['path'] ) ? sanitize_text_field( wp_unslash( $params['path'] ) ) : '';
		$rel = str_replace( '\\', '/', $rel );
		$rel = ltrim( $rel, '/' );

		if ( ! self::is_allowed_cache_path( $rel ) ) {
			return new WP_Error(
				'rrqr_bridge_bad_path',
				__( 'Path is not allowed.', 'rrqr' ),
				array( 'status' => 400 )
			);
		}

		if ( ! array_key_exists( 'body', $params ) ) {
			return new WP_Error(
				'rrqr_bridge_no_body',
				__( 'Missing body.', 'rrqr' ),
				array( 'status' => 400 )
			);
		}

		$raw = self::normalize_body( $params['body'] );
		if ( null === $raw || '' === $raw ) {
			return new WP_Error(
				'rrqr_bridge_empty',
				__( 'Body could not be encoded.', 'rrqr' ),
				array( 'status' => 400 )
			);
		}

		$written = self::save_ingest_file( $rel, $raw );
		if ( is_wp_error( $written ) ) {
			return $written;
		}

		return new WP_REST_Response(
			array(
				'ok'   => true,
				'path' => $rel,
				'size' => strlen( $raw ),
			),
			200
		);
	}

	/**
	 * @param mixed $body Raw string or array/object to json-encode.
	 * @return string|null
	 */
	private static function normalize_body( $body ) {
		if ( is_string( $body ) ) {
			return $body;
		}
		if ( is_array( $body ) || is_object( $body ) ) {
			return wp_json_encode( $body );
		}
		return null;
	}

	/**
	 * Validate path and write cache file (REST ingest, server sync, etc.).
	 *
	 * @param string $path Relative cache path.
	 * @param string $contents File contents.
	 * @return true|WP_Error
	 */
	public static function save_ingest_file( $path, $contents ) {
		$path = str_replace( '\\', '/', $path );
		$path = ltrim( $path, '/' );
		if ( ! self::is_allowed_cache_path( $path ) ) {
			return new WP_Error(
				'rrqr_bridge_bad_path',
				__( 'Path is not allowed.', 'rrqr' ),
				array( 'status' => 400 )
			);
		}
		if ( ! self::body_looks_like_json_object( $contents ) ) {
			return new WP_Error(
				'rrqr_bridge_not_json',
				__( 'Refusing to save: response is not a JSON object (often HTML from NBA web apps).', 'rrqr' ),
				array( 'status' => 400 )
			);
		}
		return self::write_cache_file( $path, $contents );
	}

	/**
	 * Whether the body looks like a JSON object (not HTML/XML).
	 *
	 * @param string $body Raw body.
	 * @return bool
	 */
	public static function body_looks_like_json_object( $body ) {
		$t = ltrim( (string) $body, " \t\n\r\0\x0B" );
		if ( strlen( $t ) >= 3 && "\xEF\xBB\xBF" === substr( $t, 0, 3 ) ) {
			$t = ltrim( substr( $t, 3 ), " \t\n\r\0\x0B" );
		}
		return '' !== $t && '{' === $t[0];
	}

	/**
	 * @param string $path Relative cache path.
	 * @param string $contents File contents.
	 * @return true|WP_Error
	 */
	private static function write_cache_file( $path, $contents ) {
		$dir = self::get_cache_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$full = $dir . '/' . $path;
		$parent = dirname( $full );
		if ( ! wp_mkdir_p( $parent ) ) {
			return new WP_Error(
				'rrqr_bridge_mkdir',
				__( 'Could not create cache directory.', 'rrqr' ),
				array( 'status' => 500 )
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok = file_put_contents( $full, $contents, LOCK_EX );
		if ( false === $ok ) {
			return new WP_Error(
				'rrqr_bridge_write',
				__( 'Could not write cache file.', 'rrqr' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * @param string $path Relative path.
	 * @return bool
	 */
	public static function is_allowed_cache_path( $path ) {
		if ( '' === $path || false !== strpos( $path, '..' ) ) {
			return false;
		}
		if ( preg_match( '#^cdn/static/json/staticData/scheduleLeagueV2\.json$#', $path ) ) {
			return true;
		}
		if ( preg_match( '#^cdn/static/json/liveData/boxscore/boxscore_\d{10}\.json$#', $path ) ) {
			return true;
		}
		if ( 'global/stats2/team/standing.json' === $path ) {
			return true;
		}
		return false;
	}

	/**
	 * Map a full NBA URL to a relative cache path, or null if unsupported.
	 *
	 * @param string $url URL.
	 * @return string|null
	 */
	public static function url_to_cache_path( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return null;
		}
		$host = strtolower( $parts['host'] );
		$path = $parts['path'];

		if ( 'cdn.nba.com' === $host ) {
			return 'cdn' . $path;
		}

		if ( ( false !== strpos( $host, 'global.nba.com' ) || false !== strpos( $host, 'nba.com' ) )
			&& false !== strpos( $path, 'standing.json' ) ) {
			return 'global/stats2/team/standing.json';
		}

		return null;
	}

	/**
	 * Whether bridge reads are enabled for the whole site (theme + RRQR admin).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( defined( 'RRQR_BRIDGE_ENABLED' ) ) {
			return (bool) RRQR_BRIDGE_ENABLED;
		}
		return (bool) get_option( self::OPTION_ENABLED, false );
	}

	/**
	 * Prefer local bridge files inside Quick Reactions admin only (theme unchanged).
	 *
	 * @return bool
	 */
	public static function is_admin_only_enabled() {
		return (bool) get_option( self::OPTION_ADMIN_ONLY, false );
	}

	/**
	 * Whether RRQR admin should read from bridge files first.
	 *
	 * @return bool
	 */
	public static function prefers_local_for_rrqr() {
		return self::is_enabled() || self::is_admin_only_enabled();
	}

	/**
	 * Paths shown on the Bridge tools screen (schedule + theme standings mirror).
	 *
	 * @return array<int, array{label:string, path:string, url:string}>
	 */
	public static function get_monitored_definitions() {
		return array(
			array(
				'label' => __( 'League schedule', 'rrqr' ),
				'path'  => 'cdn/static/json/staticData/scheduleLeagueV2.json',
				'url'   => 'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
			),
			array(
				'label' => __( 'Team standings (theme)', 'rrqr' ),
				'path'  => 'global/stats2/team/standing.json',
				'url'   => 'https://ca.global.nba.com/stats2/team/standing.json?locale=en&teamCode=raptors',
			),
		);
	}

	/**
	 * File status for a relative bridge path.
	 *
	 * @param string $rel Relative path.
	 * @return array{ok:bool, exists?:bool, size?:int, mtime?:int, error?:string}
	 */
	public static function get_path_status( $rel ) {
		$dir = self::get_cache_dir();
		if ( is_wp_error( $dir ) ) {
			return array(
				'ok'    => false,
				'error' => $dir->get_error_message(),
			);
		}
		$full = $dir . '/' . $rel;
		if ( ! is_readable( $full ) ) {
			return array(
				'ok'    => true,
				'exists' => false,
			);
		}
		return array(
			'ok'     => true,
			'exists' => true,
			'size'   => (int) filesize( $full ),
			'mtime'  => (int) filemtime( $full ),
		);
	}

	/**
	 * Shared secret for REST ingest (Bearer token).
	 *
	 * @return string
	 */
	public static function get_secret() {
		if ( defined( 'RRQR_BRIDGE_SECRET' ) && is_string( RRQR_BRIDGE_SECRET ) && '' !== RRQR_BRIDGE_SECRET ) {
			return RRQR_BRIDGE_SECRET;
		}
		$opt = get_option( self::OPTION_SECRET, '' );
		return is_string( $opt ) ? $opt : '';
	}

	/**
	 * Absolute cache directory (uploads/rrqr-bridge).
	 *
	 * @return string|WP_Error
	 */
	public static function get_cache_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'rrqr_upload_dir', $upload['error'] );
		}
		return trailingslashit( $upload['basedir'] ) . 'rrqr-bridge';
	}

	/**
	 * Whether the mirrored boxscore JSON file exists for a game.
	 *
	 * @param string $game_id Ten-digit NBA game id.
	 * @return bool
	 */
	public static function has_boxscore_mirror_file( $game_id ) {
		if ( ! preg_match( '/^\d{10}$/', (string) $game_id ) ) {
			return false;
		}
		$dir = self::get_cache_dir();
		if ( is_wp_error( $dir ) ) {
			return false;
		}
		$rel  = 'cdn/static/json/liveData/boxscore/boxscore_' . $game_id . '.json';
		$full = $dir . '/' . $rel;
		if ( ! is_readable( $full ) ) {
			return false;
		}
		$size = (int) filesize( $full );
		return $size > 50;
	}

	/**
	 * Public ingest URL for admins / fetcher script.
	 *
	 * @return string
	 */
	public static function get_rest_ingest_url() {
		return rest_url( 'rrqr/v1/bridge' );
	}

	/**
	 * Read a mirrored file for a known NBA URL (no option checks).
	 *
	 * @param string $url Original NBA URL.
	 * @return string|null
	 */
	private static function read_local_file_for_url( $url ) {
		$rel = self::url_to_cache_path( $url );
		if ( null === $rel ) {
			return null;
		}
		$dir = self::get_cache_dir();
		if ( is_wp_error( $dir ) ) {
			return null;
		}
		$full = $dir . '/' . $rel;
		if ( ! is_readable( $full ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = file_get_contents( $full );
		return false !== $data ? $data : null;
	}

	/**
	 * Return cached file body when whole-site bridge is enabled (theme + admin).
	 *
	 * @param string $url Original NBA URL.
	 * @return string|null Null if not using bridge or no file.
	 */
	public static function get_cached_body( $url ) {
		if ( ! self::is_enabled() ) {
			return null;
		}
		return self::read_local_file_for_url( $url );
	}

	/**
	 * Return cached file body for RRQR admin when whole-site or admin-only mode is on.
	 *
	 * @param string $url Original NBA URL.
	 * @return string|null
	 */
	public static function get_cached_body_for_rrqr( $url ) {
		if ( ! self::prefers_local_for_rrqr() ) {
			return null;
		}
		return self::read_local_file_for_url( $url );
	}

	/**
	 * Ensure cache root exists and is protected.
	 *
	 * @return void
	 */
	public static function ensure_cache_directory() {
		$dir = self::get_cache_dir();
		if ( is_wp_error( $dir ) ) {
			return;
		}
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! is_file( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX );
		}
	}

	/**
	 * Generate a new random secret (URL-safe).
	 *
	 * @return string
	 */
	public static function generate_secret() {
		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 48, false, false );
		}
		return bin2hex( random_bytes( 24 ) );
	}
}

/**
 * Theme / external use: read bridge file for an NBA URL.
 *
 * @param string $url URL.
 * @return string|null
 */
function rrqr_bridge_get_cached_body( $url ) {
	if ( ! class_exists( 'RRQR_Bridge' ) ) {
		return null;
	}
	return RRQR_Bridge::get_cached_body( $url );
}
