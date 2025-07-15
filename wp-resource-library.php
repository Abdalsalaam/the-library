<?php
/**
 * Plugin Name: WP Resource Library
 * Plugin URI: https://github.com/Abdalsalaam
 * Description: A comprehensive files/books/videos library plugin with user data collection for downloads.
 * Version: 1.0
 * Author: Abdalsalaam Halawa
 * Author URI: https://github.com/Abdalsalaam
 * Text Domain: wp-resource-library
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPRL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPRL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPRL_VERSION', '1.0' );

/**
 * Main plugin class
 */
class WPResourceLibrary {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Load text domain.
		load_plugin_textdomain( 'wp-resource-library', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Include required files.
		$this->includes();

		// Initialize classes.
		$this->init_classes();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
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
		new WPRL_Custom_Post_Type();
		new WPRL_Admin();
		new WPRL_Frontend();
		new WPRL_Download_Handler();
		new WPRL_CSV_Export();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database table.
		$this->create_database_table();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create database table for download requests
	 */
	private function create_database_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wprl_download_requests';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            user_email varchar(255) NOT NULL,
            user_mobile varchar(20) NOT NULL,
            download_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_email (user_email),
            KEY download_date (download_date)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

// Initialize the plugin.
new WPResourceLibrary();
