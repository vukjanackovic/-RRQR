<?php
/**
 * Admin functionality.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRQR_Admin {

	/**
	 * Team name used to filter NBA API data.
	 */
	const TEAM_NAME = 'Raptors';

	const SCHEDULE_URL = 'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json';
	const BOXSCORE_URL = 'https://cdn.nba.com/static/json/liveData/boxscore/boxscore_%s.json';
	const HEADSHOT_URL = 'https://cdn.nba.com/headshots/nba/latest/260x190/%s.png';
	const TEAM_LOGO_URL = 'https://cdn.nba.com/logos/nba/%s/global/L/logo.svg';

	const GRADES = array( 'Inc', 'F', 'D-', 'D', 'D+', 'C-', 'C', 'C+', 'B-', 'B', 'B+', 'A-', 'A', 'A+' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_rrqr_fetch_games', array( $this, 'ajax_fetch_games' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_new_bridge_secret' ) );
		add_action( 'admin_post_rrqr_bridge_server_sync', array( $this, 'handle_bridge_server_sync' ) );
	}

	/**
	 * Register admin menu page.
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'Quick Reactions', 'rrqr' ),
			__( 'Quick Reactions', 'rrqr' ),
			'edit_posts',
			'rrqr',
			array( $this, 'render_settings_page' ),
			'dashicons-thumbs-up',
			30
		);

		add_submenu_page(
			'rrqr',
			__( 'Settings', 'rrqr' ),
			__( 'Settings', 'rrqr' ),
			'edit_posts',
			'rrqr-settings',
			array( $this, 'render_settings_form_page' )
		);

		add_submenu_page(
			'rrqr',
			__( 'Bridge tools', 'rrqr' ),
			__( 'Bridge tools', 'rrqr' ),
			'edit_posts',
			'rrqr-bridge-tools',
			array( $this, 'render_bridge_tools_page' )
		);

		add_submenu_page(
			null,
			__( 'Generate Reaction', 'rrqr' ),
			'',
			'edit_posts',
			'rrqr-game',
			array( $this, 'render_game_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'toplevel_page_rrqr', 'admin_page_rrqr-game' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'rrqr-admin',
			RRQR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RRQR_VERSION
		);

		wp_register_script(
			'rrqr-utils',
			RRQR_PLUGIN_URL . 'assets/js/utils.js',
			array(),
			RRQR_VERSION,
			true
		);

		if ( 'toplevel_page_rrqr' === $hook_suffix ) {
			wp_enqueue_script(
				'rrqr-admin',
				RRQR_PLUGIN_URL . 'assets/js/admin.js',
				array(),
				RRQR_VERSION,
				true
			);

			wp_localize_script( 'rrqr-admin', 'rrqrAdmin', array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'rrqr_fetch_games' ),
				'gameUrl'      => admin_url( 'admin.php?page=rrqr-game' ),
				'gameUrlNonce' => wp_create_nonce( 'rrqr_view_game' ),
				'i18n'    => array(
					'loading'      => __( 'Loading…', 'rrqr' ),
					'refresh'      => __( 'Refresh', 'rrqr' ),
					'unknownError' => __( 'Unknown error.', 'rrqr' ),
					'networkError' => __( 'Network error. Please try again.', 'rrqr' ),
					'date'         => __( 'Date', 'rrqr' ),
					'away'         => __( 'Away', 'rrqr' ),
					'home'         => __( 'Home', 'rrqr' ),
					'status'       => __( 'Status', 'rrqr' ),
					'generateReaction' => __( 'Generate Reaction', 'rrqr' ),
				),
			) );
		}

		if ( 'admin_page_rrqr-game' === $hook_suffix ) {
			wp_enqueue_style(
				'rrqr-reaction',
				RRQR_PLUGIN_URL . 'assets/css/reaction.css',
				array(),
				RRQR_VERSION
			);

			wp_enqueue_script(
				'rrqr-game',
				RRQR_PLUGIN_URL . 'assets/js/game.js',
				array( 'rrqr-utils' ),
				RRQR_VERSION,
				true
			);

			wp_localize_script( 'rrqr-game', 'rrqrGame', array(
				'teamLogoUrl' => self::TEAM_LOGO_URL,
				'i18n'        => array(
					'copied'        => __( 'Copied!', 'rrqr' ),
					'copyClipboard' => __( 'Copy to Clipboard', 'rrqr' ),
					'thingsWeSaw'   => __( 'Things we saw', 'rrqr' ),
				),
			) );
		}
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require_once RRQR_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Register plugin settings sections and fields for rendering.
	 */
	public function register_settings() {
		add_settings_section(
			'rrqr_coach_section',
			__( 'Coach', 'rrqr' ),
			array( $this, 'render_coach_section' ),
			'rrqr-settings'
		);

		add_settings_field(
			'rrqr_coach_name',
			__( 'Coach Name', 'rrqr' ),
			array( $this, 'render_coach_name_field' ),
			'rrqr-settings',
			'rrqr_coach_section'
		);

		add_settings_field(
			'rrqr_coach_headshot_url',
			__( 'Coach Headshot URL', 'rrqr' ),
			array( $this, 'render_coach_headshot_field' ),
			'rrqr-settings',
			'rrqr_coach_section'
		);

		add_settings_section(
			'rrqr_bridge_section',
			__( 'NBA bridge cache', 'rrqr' ),
			array( $this, 'render_bridge_section' ),
			'rrqr-settings'
		);

		add_settings_field(
			'rrqr_bridge_enabled',
			__( 'Whole site', 'rrqr' ),
			array( $this, 'render_bridge_enabled_field' ),
			'rrqr-settings',
			'rrqr_bridge_section'
		);

		add_settings_field(
			'rrqr_bridge_admin_only',
			__( 'Admin test mode', 'rrqr' ),
			array( $this, 'render_bridge_admin_only_field' ),
			'rrqr-settings',
			'rrqr_bridge_section'
		);

		add_settings_field(
			'rrqr_bridge_secret',
			__( 'Ingest secret', 'rrqr' ),
			array( $this, 'render_bridge_secret_field' ),
			'rrqr-settings',
			'rrqr_bridge_section'
		);

		add_settings_field(
			'rrqr_bridge_endpoint',
			__( 'Ingest endpoint', 'rrqr' ),
			array( $this, 'render_bridge_endpoint_field' ),
			'rrqr-settings',
			'rrqr_bridge_section'
		);
	}

	/**
	 * Handle settings form submission before page output.
	 */
	public function handle_settings_save() {
		if ( ! isset( $_POST['rrqr_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rrqr_settings_nonce'] ) ), 'rrqr_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		update_option( 'rrqr_coach_name', sanitize_text_field( wp_unslash( $_POST['rrqr_coach_name'] ?? '' ) ) );
		update_option( 'rrqr_coach_headshot_url', esc_url_raw( wp_unslash( $_POST['rrqr_coach_headshot_url'] ?? '' ) ) );

		$bridge_enabled = isset( $_POST['rrqr_bridge_enabled'] );
		update_option( RRQR_Bridge::OPTION_ENABLED, $bridge_enabled );
		update_option( RRQR_Bridge::OPTION_ADMIN_ONLY, isset( $_POST['rrqr_bridge_admin_only'] ) );

		if ( ! defined( 'RRQR_BRIDGE_SECRET' ) ) {
			$prev_secret = get_option( RRQR_Bridge::OPTION_SECRET, '' );
			$prev_secret = is_string( $prev_secret ) ? $prev_secret : '';
			$new_secret  = isset( $_POST['rrqr_bridge_secret'] ) ? trim( (string) wp_unslash( $_POST['rrqr_bridge_secret'] ) ) : '';

			if ( '' !== $new_secret ) {
				update_option( RRQR_Bridge::OPTION_SECRET, $new_secret );
			} elseif ( $bridge_enabled && '' === $prev_secret ) {
				$generated = RRQR_Bridge::generate_secret();
				update_option( RRQR_Bridge::OPTION_SECRET, $generated );
				set_transient( 'rrqr_bridge_show_secret_once', $generated, 120 );
			}
		}

		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', admin_url( 'admin.php?page=rrqr-settings' ) ) );
		exit;
	}

	/**
	 * Render the settings form page.
	 */
	public function render_settings_form_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require_once RRQR_PLUGIN_DIR . 'templates/admin-settings-form.php';
	}

	/**
	 * Bridge tools: status + optional server-side sync (same IP as production).
	 */
	public function render_bridge_tools_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require_once RRQR_PLUGIN_DIR . 'templates/admin-bridge-tools.php';
	}

	/**
	 * Try to download schedule + standings from this server into the bridge directory.
	 */
	public function handle_bridge_server_sync() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'rrqr_bridge_server_sync' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'rrqr' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'rrqr' ) );
		}

		RRQR_Bridge::ensure_cache_directory();

		$results = array();
		foreach ( RRQR_Bridge::get_monitored_definitions() as $def ) {
			$response = wp_remote_get(
				$def['url'],
				array(
					'timeout'    => 60,
					'user-agent' => 'RRQR Plugin/' . RRQR_VERSION,
				)
			);

			if ( is_wp_error( $response ) ) {
				$results[] = array(
					'path'    => $def['path'],
					'label'   => $def['label'],
					'ok'      => false,
					'message' => $response->get_error_message(),
				);
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			if ( 200 !== $code ) {
				$results[] = array(
					'path'    => $def['path'],
					'label'   => $def['label'],
					'ok'      => false,
					/* translators: %d: HTTP status code */
					'message' => sprintf( __( 'HTTP %d from NBA CDN.', 'rrqr' ), (int) $code ),
				);
				continue;
			}

			if ( ! RRQR_Bridge::body_looks_like_json_object( $body ) ) {
				$results[] = array(
					'path'    => $def['path'],
					'label'   => $def['label'],
					'ok'      => false,
					'message' => __( 'Not JSON (often HTML from ca.global.nba.com for legacy standing.json). Theme summary may need another data source.', 'rrqr' ),
				);
				continue;
			}

			$saved = RRQR_Bridge::save_ingest_file( $def['path'], $body );
			if ( is_wp_error( $saved ) ) {
				$results[] = array(
					'path'    => $def['path'],
					'label'   => $def['label'],
					'ok'      => false,
					'message' => $saved->get_error_message(),
				);
				continue;
			}

			$results[] = array(
				'path'    => $def['path'],
				'label'   => $def['label'],
				'ok'      => true,
				'message' => __( 'Saved to bridge cache.', 'rrqr' ),
				'bytes'   => strlen( $body ),
			);
		}

		set_transient( 'rrqr_bridge_sync_notice_' . get_current_user_id(), $results, 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=rrqr-bridge-tools&rrqr_synced=1' ) );
		exit;
	}

	/**
	 * Render the Coach section description.
	 */
	public function render_coach_section() {
		echo '<p>' . esc_html__( 'Configure the head coach details for reactions.', 'rrqr' ) . '</p>';
	}

	/**
	 * Render the Coach Name field.
	 */
	public function render_coach_name_field() {
		$value = get_option( 'rrqr_coach_name', '' );
		echo '<input type="text" id="rrqr_coach_name" name="rrqr_coach_name" value="' . esc_attr( $value ) . '" class="regular-text" />';
	}

	/**
	 * Render the Coach Headshot URL field.
	 */
	public function render_coach_headshot_field() {
		$value = get_option( 'rrqr_coach_headshot_url', '' );
		echo '<input type="url" id="rrqr_coach_headshot_url" name="rrqr_coach_headshot_url" value="' . esc_attr( $value ) . '" class="regular-text" />';
		if ( ! empty( $value ) ) {
			echo '<p><img src="' . esc_url( $value ) . '" alt="' . esc_attr__( 'Coach headshot preview', 'rrqr' ) . '" style="max-width:130px;height:auto;margin-top:8px;border-radius:4px;" /></p>';
		}
	}

	/**
	 * Bridge section description.
	 */
	public function render_bridge_section() {
		echo '<p>' . esc_html__( 'Mirrored JSON lives under wp-content/uploads/rrqr-bridge/. Use Bridge tools to inspect files or try a sync from this server. If the host is blocked by the NBA CDN, push data with the CLI fetcher or the REST ingest endpoint.', 'rrqr' ) . '</p>';
	}

	/**
	 * Bridge enabled for whole site (theme + admin).
	 */
	public function render_bridge_enabled_field() {
		$value = get_option( RRQR_Bridge::OPTION_ENABLED, false );
		echo '<label><input type="checkbox" name="rrqr_bridge_enabled" value="1" ' . checked( (bool) $value, true, false ) . ' /> ';
		esc_html_e( 'Use mirrored files on the front end and in admin (theme standings + Quick Reactions).', 'rrqr' );
		echo '</label>';
	}

	/**
	 * Bridge for RRQR admin only (production front end unchanged).
	 */
	public function render_bridge_admin_only_field() {
		$value = get_option( RRQR_Bridge::OPTION_ADMIN_ONLY, false );
		echo '<label><input type="checkbox" name="rrqr_bridge_admin_only" value="1" ' . checked( (bool) $value, true, false ) . ' /> ';
		esc_html_e( 'Use mirrored files only inside Quick Reactions (schedule/boxscore). The theme keeps using the CDN / its own cache as before.', 'rrqr' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Turn this on to test the workflow without changing live site behavior. You still need populated bridge files (Bridge tools → Try sync, external fetcher, or REST).', 'rrqr' ) . '</p>';
	}

	/**
	 * Bridge ingest secret field.
	 */
	public function render_bridge_secret_field() {
		if ( defined( 'RRQR_BRIDGE_SECRET' ) && is_string( RRQR_BRIDGE_SECRET ) && '' !== RRQR_BRIDGE_SECRET ) {
			echo '<p>' . esc_html__( 'The secret is set in wp-config.php via RRQR_BRIDGE_SECRET.', 'rrqr' ) . '</p>';
			return;
		}

		echo '<input type="password" name="rrqr_bridge_secret" class="regular-text" autocomplete="new-password" placeholder="' . esc_attr__( 'Leave blank to keep the current secret', 'rrqr' ) . '" />';
		if ( '' !== RRQR_Bridge::get_secret() ) {
			echo '<p class="description">' . esc_html__( 'A secret is already saved. Enter a new value only to replace it.', 'rrqr' ) . '</p>';
		}
	}

	/**
	 * Show REST ingest URL.
	 */
	public function render_bridge_endpoint_field() {
		echo '<p><code style="word-break:break-all;">' . esc_html( RRQR_Bridge::get_rest_ingest_url() ) . '</code></p>';
		echo '<p class="description">' . esc_html__( 'POST JSON: { "path": "cdn/static/json/…", "body": "<raw json string or object>" } with header Authorization: Bearer <secret>. Large payloads may require raising PHP post_max_size on the server.', 'rrqr' ) . '</p>';
	}

	/**
	 * One-time display of an auto-generated bridge secret after save.
	 */
	public function maybe_show_new_bridge_secret() {
		if ( ! isset( $_GET['page'] ) || 'rrqr-settings' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$secret = get_transient( 'rrqr_bridge_show_secret_once' );
		if ( false === $secret || ! is_string( $secret ) ) {
			return;
		}
		delete_transient( 'rrqr_bridge_show_secret_once' );
		echo '<div class="notice notice-warning is-dismissible"><p><strong>RRQR bridge:</strong> ';
		esc_html_e( 'Copy this ingest secret for your fetcher cron (shown once):', 'rrqr' );
		echo ' <code style="user-select:all">' . esc_html( $secret ) . '</code></p></div>';
	}

	/**
	 * Render game detail page.
	 */
	public function render_game_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'rrqr_view_game' ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Invalid or expired link. Please go back and try again.', 'rrqr' ) . '</p></div></div>';
			return;
		}

		$game_id = isset( $_GET['game_id'] ) ? sanitize_text_field( wp_unslash( $_GET['game_id'] ) ) : '';

		if ( empty( $game_id ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'No game ID provided.', 'rrqr' ) . '</p></div></div>';
			return;
		}

		if ( ! preg_match( '/^\d{10}$/', $game_id ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Invalid game ID format.', 'rrqr' ) . '</p></div></div>';
			return;
		}

		$game = $this->fetch_boxscore( $game_id );

		if ( is_wp_error( $game ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $game->get_error_message() ) . '</p></div></div>';
			return;
		}

		$team_name  = self::TEAM_NAME;
		$players    = array();
		$team_label = '';

		if ( ( $game['homeTeam']['teamName'] ?? '' ) === $team_name ) {
			$players    = $game['homeTeam']['players'] ?? array();
			$team_label = ( $game['homeTeam']['teamCity'] ?? '' ) . ' ' . ( $game['homeTeam']['teamName'] ?? '' );
		} elseif ( ( $game['awayTeam']['teamName'] ?? '' ) === $team_name ) {
			$players    = $game['awayTeam']['players'] ?? array();
			$team_label = ( $game['awayTeam']['teamCity'] ?? '' ) . ' ' . ( $game['awayTeam']['teamName'] ?? '' );
		}

		$grades             = self::GRADES;
		$coach_name         = get_option( 'rrqr_coach_name', '' );
		$coach_headshot_url = get_option( 'rrqr_coach_headshot_url', '' );

		require_once RRQR_PLUGIN_DIR . 'templates/admin-game.php';
	}

	/**
	 * Format a player's statistics into a readable statline string.
	 *
	 * @param array $stats Player statistics array from the NBA API.
	 * @return string Formatted statline.
	 */
	public static function format_statline( $stats ) {
		$min = $stats['minutes'] ?? '';
		if ( preg_match( '/PT(\d+)M/', $min, $m ) ) {
			$min = $m[1];
		} else {
			$min = '0';
		}

		return implode( ', ', array(
			$min . ' MIN',
			( $stats['points'] ?? 0 ) . ' PTS',
			( $stats['reboundsTotal'] ?? 0 ) . ' REB',
			( $stats['assists'] ?? 0 ) . ' AST',
			( $stats['steals'] ?? 0 ) . ' STL',
			( $stats['fieldGoalsMade'] ?? 0 ) . '-' . ( $stats['fieldGoalsAttempted'] ?? 0 ) . ' FG',
			( $stats['threePointersMade'] ?? 0 ) . '-' . ( $stats['threePointersAttempted'] ?? 0 ) . ' 3FG',
			( $stats['freeThrowsMade'] ?? 0 ) . '-' . ( $stats['freeThrowsAttempted'] ?? 0 ) . ' FT',
			( $stats['blocks'] ?? 0 ) . ' BLK',
			( $stats['turnovers'] ?? 0 ) . ' TO',
			( $stats['plusMinusPoints'] ?? 0 ) . ' +/-',
		) );
	}

	/**
	 * Fetch boxscore data from the NBA API with transient caching.
	 *
	 * @param string $game_id The 10-digit NBA game ID.
	 * @return array|WP_Error Game data array on success, WP_Error on failure.
	 */
	private function fetch_boxscore( $game_id ) {
		$cache_key = 'rrqr_boxscore_' . $game_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = sprintf( self::BOXSCORE_URL, urlencode( $game_id ) );
		$body = $this->fetch_nba_json( $url );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$game = $body['game'] ?? null;

		if ( empty( $game ) ) {
			return new WP_Error( 'no_data', __( 'Could not load game data.', 'rrqr' ) );
		}

		set_transient( $cache_key, $game, DAY_IN_SECONDS );

		return $game;
	}

	/**
	 * Fetch JSON from the NBA CDN or from the local bridge cache.
	 *
	 * @param string $url Full NBA URL.
	 * @return array|WP_Error Decoded JSON (root) or error.
	 */
	private function fetch_nba_json( $url ) {
		$local = class_exists( 'RRQR_Bridge' ) ? RRQR_Bridge::get_cached_body_for_rrqr( $url ) : null;
		if ( null !== $local ) {
			$body = json_decode( $local, true );
			if ( null === $body && JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'invalid_json', __( 'Bridge cache file is not valid JSON.', 'rrqr' ) );
			}
			return is_array( $body ) ? $body : array();
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'user-agent' => 'RRQR Plugin/' . RRQR_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'api_error', sprintf( __( 'NBA API returned status %d.', 'rrqr' ), $status_code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( null === $body && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'invalid_json', __( 'Response was not valid JSON.', 'rrqr' ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * AJAX handler: fetch NBA schedule and return team games.
	 */
	public function ajax_fetch_games() {
		check_ajax_referer( 'rrqr_fetch_games', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'rrqr' ), 403 );
			return;
		}

		if ( ! empty( $_POST['refresh'] ) ) {
			delete_transient( 'rrqr_nba_schedule' );
		}

		$cached = get_transient( 'rrqr_nba_schedule' );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
			return;
		}

		$body = $this->fetch_nba_json( self::SCHEDULE_URL );

		if ( is_wp_error( $body ) ) {
			wp_send_json_error( $body->get_error_message() );
			return;
		}

		if ( empty( $body['leagueSchedule']['gameDates'] ) ) {
			wp_send_json_error( __( 'No schedule data found.', 'rrqr' ) );
			return;
		}

		$team_name     = self::TEAM_NAME;
		$team_games    = array();

		foreach ( $body['leagueSchedule']['gameDates'] as $game_date ) {
			foreach ( $game_date['games'] as $game ) {
				$home = $game['homeTeam']['teamName'] ?? '';
				$away = $game['awayTeam']['teamName'] ?? '';

				$status = trim( $game['gameStatusText'] ?? '' );
				if ( ( $team_name === $home || $team_name === $away ) && in_array( $status, array( 'Final', 'Final/OT' ), true ) ) {
					$team_games[] = array(
						'date'     => $game_date['gameDate'] ?? '',
						'homeTeam' => $game['homeTeam']['teamName'] ?? '',
						'homeCity' => $game['homeTeam']['teamCity'] ?? '',
						'awayTeam' => $game['awayTeam']['teamName'] ?? '',
						'awayCity' => $game['awayTeam']['teamCity'] ?? '',
						'status'   => $game['gameStatusText'] ?? '',
						'gameId'   => $game['gameId'] ?? '',
					);
				}
			}
		}

		if ( empty( $team_games ) ) {
			/* translators: %s: team name */
			wp_send_json_error( sprintf( __( 'No %s games found.', 'rrqr' ), $team_name ) );
			return;
		}

		set_transient( 'rrqr_nba_schedule', $team_games, HOUR_IN_SECONDS );

		wp_send_json_success( $team_games );
	}

}
