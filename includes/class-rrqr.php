<?php
/**
 * Main plugin class.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRQR {

	/**
	 * Singleton instance.
	 *
	 * @var RRQR|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return RRQR
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		require_once RRQR_PLUGIN_DIR . 'includes/class-rrqr-bridge.php';
		add_action( 'rest_api_init', array( 'RRQR_Bridge', 'register_rest_routes' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		if ( is_admin() ) {
			require_once RRQR_PLUGIN_DIR . 'includes/class-rrqr-admin.php';
			new RRQR_Admin();
		}
	}

	/**
	 * Init hook callback.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'rrqr-reaction', RRQR_PLUGIN_URL . 'assets/css/reaction.css', array(), RRQR_VERSION );
	}

	public function init() {
		load_plugin_textdomain( 'rrqr', false, dirname( RRQR_PLUGIN_BASENAME ) . '/languages' );
	}
}
