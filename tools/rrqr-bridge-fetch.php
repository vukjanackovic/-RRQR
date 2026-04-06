#!/usr/bin/env php
<?php
/**
 * Fetches NBA JSON from a network that can reach the CDN, then POSTs it to the site REST ingest.
 *
 * Run on a cron outside Cloudways (home server, GitHub Actions, cheap VPS, etc.):
 *
 *   RRQR_BRIDGE_SITE="https://www.example.com" RRQR_BRIDGE_SECRET="your-token" php rrqr-bridge-fetch.php
 *
 * Optional boxscore for Quick Reactions (10-digit game id):
 *
 *   php rrqr-bridge-fetch.php --game=0022400123
 *
 * @package RRQR
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

$longopts = array( 'site:', 'secret:', 'game:', 'help' );
$opts     = getopt( '', $longopts );

if ( isset( $opts['help'] ) ) {
	$self = basename( __FILE__ );
	echo "Usage: RRQR_BRIDGE_SITE=https://site RRQR_BRIDGE_SECRET=xxx php {$self} [--game=GAME_ID]\n";
	echo "   or: php {$self} --site=https://site --secret=xxx [--game=GAME_ID]\n";
	exit( 0 );
}

$site   = $opts['site'] ?? getenv( 'RRQR_BRIDGE_SITE' );
$secret = $opts['secret'] ?? getenv( 'RRQR_BRIDGE_SECRET' );
$game   = $opts['game'] ?? null;

if ( empty( $site ) || empty( $secret ) ) {
	fwrite( STDERR, "Missing --site / RRQR_BRIDGE_SITE or --secret / RRQR_BRIDGE_SECRET.\n" );
	exit( 1 );
}

$site = rtrim( $site, '/' );
$endpoint = $site . '/wp-json/rrqr/v1/bridge';

$jobs = array(
	array(
		'url'  => 'https://cdn.nba.com/static/json/staticData/scheduleLeagueV2.json',
		'path' => 'cdn/static/json/staticData/scheduleLeagueV2.json',
	),
	array(
		'url'  => 'https://ca.global.nba.com/stats2/team/standing.json?locale=en&teamCode=raptors',
		'path' => 'global/stats2/team/standing.json',
	),
);

if ( is_string( $game ) && preg_match( '/^\d{10}$/', $game ) ) {
	$jobs[] = array(
		'url'  => 'https://cdn.nba.com/static/json/liveData/boxscore/boxscore_' . $game . '.json',
		'path' => 'cdn/static/json/liveData/boxscore/boxscore_' . $game . '.json',
	);
} elseif ( null !== $game && '' !== $game ) {
	fwrite( STDERR, "Invalid --game: expected 10-digit NBA game id.\n" );
	exit( 1 );
}

$ua = 'RRQR-Bridge-Fetch/1.0';

foreach ( $jobs as $job ) {
	$body = rrqr_bridge_cli_http_get( $job['url'], $ua );
	if ( null === $body ) {
		fwrite( STDERR, "Fetch failed: {$job['url']}\n" );
		exit( 1 );
	}

	$payload = wp_json_encode_bridge(
		array(
			'path' => $job['path'],
			'body' => $body,
		)
	);

	if ( false === $payload ) {
		fwrite( STDERR, "JSON encode failed for {$job['path']}\n" );
		exit( 1 );
	}

	$code = rrqr_bridge_cli_http_post( $endpoint, $payload, $secret, $ua );
	if ( $code < 200 || $code >= 300 ) {
		fwrite( STDERR, "Ingest failed HTTP {$code} for {$job['path']}\n" );
		exit( 1 );
	}

	echo "OK {$job['path']} (" . strlen( $body ) . " bytes)\n";
}

/**
 * @param string $url URL.
 * @param string $ua  User-Agent.
 * @return string|null
 */
function rrqr_bridge_cli_http_get( $url, $ua ) {
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_USERAGENT      => $ua,
			)
		);
		$res  = curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		if ( false === $res || $code < 200 || $code >= 300 ) {
			return null;
		}
		return $res;
	}

	$ctx = stream_context_create(
		array(
			'http' => array(
				'timeout' => 60,
				'header'  => "User-Agent: {$ua}\r\n",
			),
		)
	);
	$res = @file_get_contents( $url, false, $ctx );
	if ( false === $res ) {
		return null;
	}
	return $res;
}

/**
 * @param string $endpoint URL.
 * @param string $json     JSON body.
 * @param string $secret   Bearer token.
 * @param string $ua       User-Agent.
 * @return int HTTP status code or 0.
 */
function rrqr_bridge_cli_http_post( $endpoint, $json, $secret, $ua ) {
	if ( function_exists( 'curl_init' ) ) {
		$ch = curl_init( $endpoint );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $json,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 60,
				CURLOPT_USERAGENT      => $ua,
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					'Authorization: Bearer ' . $secret,
				),
			)
		);
		curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );
		return $code;
	}

	$ctx = stream_context_create(
		array(
			'http' => array(
				'method'  => 'POST',
				'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$secret}\r\nUser-Agent: {$ua}\r\n",
				'content' => $json,
				'timeout' => 60,
			),
		)
	);
	$res = @file_get_contents( $endpoint, false, $ctx );
	$code = 0;
	if ( ! empty( $http_response_header ) && is_array( $http_response_header ) && ! empty( $http_response_header[0] ) && preg_match( '#\s(\d{3})\s#', $http_response_header[0], $m ) ) {
		$code = (int) $m[1];
	}
	return $code;
}

/**
 * JSON encode without WordPress (CLI).
 *
 * @param array $data Data.
 * @return string|false
 */
function wp_json_encode_bridge( array $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
