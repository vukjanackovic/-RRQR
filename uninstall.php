<?php
/**
 * Uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package RRQR
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'rrqr_coach_name' );
delete_option( 'rrqr_coach_headshot_url' );
delete_option( 'rrqr_bridge_enabled' );
delete_option( 'rrqr_bridge_admin_only' );
delete_option( 'rrqr_bridge_secret' );

// Remove cached data.
delete_transient( 'rrqr_nba_schedule' );

global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_rrqr_boxscore_%'
	    OR option_name LIKE '_transient_timeout_rrqr_boxscore_%'"
);
