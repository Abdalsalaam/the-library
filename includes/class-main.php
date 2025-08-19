<?php
/**
 * Main Class.
 *
 * @package TheLibrary
 */

namespace TheLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class Main {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( WPRL_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( WPRL_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Include required files.
		$this->includes();

		// Initialize database singleton.
		Database::get_instance();

		// Initialize classes.
		$this->init_classes();

		// Schedule cleanup tasks.
		$this->schedule_cleanup_tasks();

		// Hook cleanup function to scheduled event.
		add_action( 'wprl_daily_cleanup', array( $this, 'run_daily_cleanup' ) );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once WPRL_PLUGIN_PATH . 'includes/class-database.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-utils.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-custom-post-type.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-admin.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-frontend.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-download-handler.php';
		require_once WPRL_PLUGIN_PATH . 'includes/class-csv-export.php';
	}

	/**
	 * Initialize classes.
	 */
	private function init_classes() {
		new Custom_Post_Type();
		new Admin();
		new Frontend();
		new Download_Handler();
		new CSV_Export();
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		$this->includes();
		// Create database tables.
		Database::get_instance()->create_tables();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Schedule cleanup tasks.
	 */
	public function schedule_cleanup_tasks() {
		if ( ! wp_next_scheduled( 'wprl_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wprl_daily_cleanup' );
		}
	}

	/**
	 * Run daily cleanup tasks.
	 */
	public function run_daily_cleanup() {
		// Run full cleanup with default settings.
		Database::get_instance()->run_full_cleanup(
			array(
				'download_requests_days' => 365, // Keep 1 year of download data.
				'error_logs_days'        => 30,  // Keep 30 days of error logs.
			)
		);
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clear scheduled events.
		wp_clear_scheduled_hook( 'wprl_daily_cleanup' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
