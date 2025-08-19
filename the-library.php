<?php
/**
 * Plugin Name: The Library
 * Plugin URI: https://github.com/Abdalsalaam/wp-resource-library
 * Description: A comprehensive files/books/videos library plugin with user data collection for downloads.
 * Version: 1.0.2
 * Author: Abdalsalaam Halawa
 * Author URI: https://github.com/Abdalsalaam
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: the-library
 *
 * @package WPResourceLibrary
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'WPRL_PLUGIN_URL' ) ) {
	define( 'WPRL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPRL_PLUGIN_PATH' ) ) {
	define( 'WPRL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPRL_PLUGIN_FILE' ) ) {
	define( 'WPRL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPRL_VERSION' ) ) {
	define( 'WPRL_VERSION', '1.0.2' );
}

// Initialize the plugin.
require_once WPRL_PLUGIN_PATH . 'includes/class-main.php';
new \WPResourceLibrary\Main();
