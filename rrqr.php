<?php
/**
 * Plugin Name: RR Quick Reaction
 * Plugin URI:  https://github.com/nationnetwork/rrqr
 * Description: RRQR plugin for The Nation Network
 * Version:     1.4.2
 * Author:      Ivan Filippov
 * Author URI:  https://github.com/ivanfilippov
 * Text Domain: rrqr
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RRQR_VERSION', '1.4.2' );
define( 'RRQR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RRQR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RRQR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

register_activation_hook(
	__FILE__,
	function () {
		require_once RRQR_PLUGIN_DIR . 'includes/class-rrqr-bridge.php';
		RRQR_Bridge::ensure_cache_directory();
	}
);

require_once RRQR_PLUGIN_DIR . 'includes/class-rrqr.php';

/**
 * Returns the main plugin instance.
 *
 * @return RRQR
 */
function rrqr() {
	return RRQR::get_instance();
}

rrqr();
