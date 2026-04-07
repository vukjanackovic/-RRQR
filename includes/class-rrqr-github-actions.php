<?php
/**
 * Trigger GitHub Actions workflow_dispatch for boxscore bridge ingest.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub API integration (PAT stored in options).
 *
 * WordPress always dispatches rrqr-bridge-boxscore.yml only. Full schedule sync is rrqr-nba-bridge-sync.yml (GitHub UI / cron only).
 */
class RRQR_Github_Actions {

	const OPTION_TOKEN = 'rrqr_github_token';
	const OPTION_REPO  = 'rrqr_github_repo';
	const OPTION_REF   = 'rrqr_github_ref';

	/**
	 * Deprecated option key (removed from UI; still deleted on uninstall).
	 */
	const OPTION_WORKFLOW_LEGACY = 'rrqr_github_workflow';

	/**
	 * Workflow file always dispatched from WordPress (must exist at .github/workflows/ on the chosen ref).
	 */
	const BOXSCORE_WORKFLOW_FILE = 'rrqr-bridge-boxscore.yml';

	/**
	 * Whether repo, branch, and token are set.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$repo = self::get_repo();
		$tok  = self::get_token();
		$ref  = self::get_ref();
		return '' !== $repo && '' !== $tok && '' !== $ref;
	}

	/**
	 * @return string
	 */
	public static function get_token() {
		$v = get_option( self::OPTION_TOKEN, '' );
		return is_string( $v ) ? $v : '';
	}

	/**
	 * owner/repo (accepts pasted https://github.com/owner/repo URLs).
	 *
	 * @return string
	 */
	public static function get_repo() {
		$v = get_option( self::OPTION_REPO, '' );
		$v = is_string( $v ) ? trim( $v ) : '';
		$v = trim( $v, '/' );
		return self::normalize_github_repo_slug( $v );
	}

	/**
	 * Turn a repo field into owner/repo for the REST API.
	 *
	 * @param string $raw Raw value from settings.
	 * @return string Normalized slug or empty string.
	 */
	public static function normalize_github_repo_slug( $raw ) {
		$raw = trim( (string) $raw );
		$raw = trim( $raw, '/' );
		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '#^(?:https?://)?(?:www\.)?github\.com/([^/]+)/([^/?#]+)#i', $raw, $m ) ) {
			$name = $m[2];
			if ( strlen( $name ) > 4 && '.git' === strtolower( substr( $name, -4 ) ) ) {
				$name = substr( $name, 0, -4 );
			}
			return $m[1] . '/' . $name;
		}

		if ( strlen( $raw ) > 4 && '.git' === strtolower( substr( $raw, -4 ) ) ) {
			$raw = substr( $raw, 0, -4 );
		}

		return trim( $raw, '/' );
	}

	/**
	 * Git ref (branch or tag) to run the workflow on.
	 *
	 * @return string
	 */
	public static function get_ref() {
		$v = get_option( self::OPTION_REF, 'main' );
		$v = is_string( $v ) ? trim( $v ) : '';
		return '' !== $v ? $v : 'main';
	}

	/**
	 * POST workflow_dispatch for boxscore-only workflow (never schedule sync).
	 *
	 * @param string $game_id Ten-digit NBA game id.
	 * @return true|WP_Error
	 */
	public static function dispatch_bridge_workflow( $game_id ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'rrqr_github_not_configured',
				__( 'GitHub Actions is not configured. Add token, repository, and branch under Settings.', 'rrqr' )
			);
		}

		if ( ! preg_match( '/^\d{10}$/', $game_id ) ) {
			return new WP_Error( 'rrqr_github_bad_game', __( 'Invalid game ID.', 'rrqr' ) );
		}

		$repo = self::get_repo();
		if ( ! preg_match( '#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo ) ) {
			return new WP_Error( 'rrqr_github_bad_repo', __( 'Repository must look like owner/repo.', 'rrqr' ) );
		}

		$workflow = self::BOXSCORE_WORKFLOW_FILE;
		$ref      = self::get_ref();
		$token    = self::get_token();

		$url = sprintf(
			'https://api.github.com/repos/%s/actions/workflows/%s/dispatches',
			$repo,
			rawurlencode( $workflow )
		);

		$payload = array(
			'ref'    => $ref,
			'inputs' => array(
				'game_id' => $game_id,
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization'        => 'Bearer ' . $token,
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'Content-Type'         => 'application/json',
					'User-Agent'           => 'RRQR-WordPress/' . ( defined( 'RRQR_VERSION' ) ? RRQR_VERSION : '1' ),
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 204 === $code ) {
			return true;
		}

		$decoded = json_decode( $body, true );
		$msg     = is_array( $decoded ) && isset( $decoded['message'] ) ? (string) $decoded['message'] : '';

		if ( '' === $msg ) {
			/* translators: %d: HTTP status code */
			$msg = sprintf( __( 'GitHub API returned HTTP %d.', 'rrqr' ), (int) $code );
		}

		return new WP_Error( 'rrqr_github_api', $msg, array( 'status' => $code ) );
	}
}
