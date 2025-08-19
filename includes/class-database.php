<?php
/**
 * Database Class - Centralized database operations.
 *
 * @package TheLibrary
 */

namespace TheLibrary;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class for handling all database operations.
 * Implements Singleton pattern for global access.
 */
class Database {

	/**
	 * Singleton instance.
	 *
	 * @var Database|null
	 */
	private static ?Database $instance = null;

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Download requests table name.
	 *
	 * @var string
	 */
	private string $download_requests_table;

	/**
	 * Error logs table name.
	 *
	 * @var string
	 */
	private string $error_logs_table;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		global $wpdb;
		$this->wpdb                    = $wpdb;
		$this->download_requests_table = $wpdb->prefix . 'wprl_download_requests';
		$this->error_logs_table        = $wpdb->prefix . 'wprl_error_logs';
	}

	/**
	 * Prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Prevent un-serialization of the instance.
	 *
	 * @throws \Exception Un-serialization error.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot un-serialize singleton' );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Database The singleton instance.
	 */
	public static function get_instance(): Database {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Static helper method for quick access to database operations.
	 * Alias for get_instance().
	 *
	 * @return Database The singleton instance.
	 */
	public static function db(): Database {
		return self::get_instance();
	}

	/**
	 * Create plugin database tables.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function create_tables(): bool {
		$download_requests_created = $this->create_download_requests_table();
		$error_logs_created        = $this->create_error_logs_table();

		return $download_requests_created && $error_logs_created;
	}

	/**
	 * Create download requests table.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_download_requests_table(): bool {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->download_requests_table} (
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
			KEY download_date (download_date),
			KEY ip_address (ip_address)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Create error logs table.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function create_error_logs_table(): bool {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->error_logs_table} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext,
			user_id bigint(20),
			ip_address varchar(45),
			url varchar(500),
			PRIMARY KEY (id),
			KEY timestamp (timestamp),
			KEY level (level),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$result = dbDelta( $sql );

		return ! empty( $result );
	}

	/**
	 * Check if a table exists.
	 *
	 * @param string $table_name Table name to check.
	 *
	 * @return bool True if table exists, false otherwise.
	 */
	public function table_exists( string $table_name ): bool {
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $result === $table_name;
	}

	/**
	 * Insert download request.
	 *
	 * @param array $data Download request data.
	 *
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert_download_request( array $data ) {
		global $wpdb;
		$defaults = array(
			'post_id'       => 0,
			'user_name'     => '',
			'user_email'    => '',
			'user_mobile'   => '',
			'download_date' => current_time( 'mysql' ),
			'ip_address'    => '',
			'user_agent'    => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$this->download_requests_table,
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			Utils::log_error(
				'Failed to insert download request',
				array(
					'data'     => $data,
					'db_error' => $wpdb->last_error,
				)
			);
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get download requests with optional filtering and pagination.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array Query results with data and pagination info.
	 */
	public function get_download_requests( array $args = array() ): array {
		global $wpdb;
		$defaults = array(
			'search'        => '',
			'per_page'      => 20,
			'page'          => 1,
			'orderby'       => 'download_date',
			'order'         => 'DESC',
			'include_posts' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause.
		$where_conditions = array();
		$query_params     = array();

		if ( ! empty( $args['search'] ) ) {
			$search_term        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_conditions[] = '(dr.user_name LIKE %s OR dr.user_email LIKE %s OR dr.user_mobile LIKE %s)';
			$query_params[]     = $search_term;
			$query_params[]     = $search_term;
			$query_params[]     = $search_term;
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

		// Build ORDER BY clause.
		$allowed_orderby = array( 'download_date', 'user_name', 'user_email', 'post_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'download_date';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$order_clause    = "ORDER BY dr.{$orderby} {$order}";

		// Get total count.
		$count_sql = "SELECT COUNT(*) FROM {$this->download_requests_table} dr {$where_clause}";
		if ( ! empty( $query_params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$count_sql = $wpdb->prepare( $count_sql, ...$query_params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_items = (int) $wpdb->get_var( $count_sql );

		// Calculate pagination.
		$per_page    = max( 1, (int) $args['per_page'] );
		$page        = max( 1, (int) $args['page'] );
		$offset      = ( $page - 1 ) * $per_page;
		$total_pages = ceil( $total_items / $per_page );

		// Build main query.
		if ( $args['include_posts'] ) {
			$select_clause = 'SELECT dr.*, p.post_title';
			$from_clause   = "FROM {$this->download_requests_table} dr LEFT JOIN {$wpdb->posts} p ON dr.post_id = p.ID";
		} else {
			$select_clause = 'SELECT dr.*';
			$from_clause   = "FROM {$this->download_requests_table} dr";
		}

		$limit_clause   = 'LIMIT %d OFFSET %d';
		$query_params[] = $per_page;
		$query_params[] = $offset;

		$main_sql = "{$select_clause} {$from_clause} {$where_clause} {$order_clause} {$limit_clause}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $main_sql, ...$query_params ) );

		return array(
			'data'         => $results ? $results : array(),
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Delete download request by ID.
	 *
	 * @param int $id Request ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete_download_request( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete(
			$this->download_requests_table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Delete multiple download requests by IDs.
	 *
	 * @param array $ids Array of request IDs.
	 *
	 * @return int Number of deleted records.
	 */
	public function delete_download_requests( array $ids ): int {
		global $wpdb;

		if ( empty( $ids ) ) {
			return 0;
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$query        = "DELETE FROM {$this->download_requests_table} WHERE id IN ($placeholders)";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->query( $wpdb->prepare( $query, ...$ids ) );

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Clean up old download requests.
	 *
	 * @param int $days Number of days to keep.
	 *
	 * @return int Number of deleted records.
	 */
	public function cleanup_download_requests( int $days = 365 ): int {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->download_requests_table} WHERE download_date < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days
			)
		);

		if ( false === $result ) {
			Utils::log_error(
				'Failed to cleanup download requests',
				array(
					'days'     => $days,
					'db_error' => $wpdb->last_error,
				)
			);
			return 0;
		}

		return (int) $result;
	}

	/**
	 * Get all download requests for export.
	 *
	 * @return array All download requests with post titles.
	 */
	public function get_all_download_requests_for_export(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT dr.*, p.post_title
			FROM {$this->download_requests_table} dr
			LEFT JOIN {$wpdb->posts} p ON dr.post_id = p.ID
			ORDER BY dr.download_date DESC"
		);

		return $results ? $results : array();
	}

	/**
	 * Insert error log entry.
	 *
	 * @param array $data Error log data.
	 *
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function insert_error_log( array $data ) {
		global $wpdb;

		$defaults = array(
			'timestamp'  => current_time( 'mysql' ),
			'level'      => 'error',
			'message'    => '',
			'context'    => '',
			'user_id'    => 0,
			'ip_address' => '',
			'url'        => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Ensure context is JSON encoded if it's an array.
		if ( is_array( $data['context'] ) ) {
			$data['context'] = wp_json_encode( $data['context'] );
		}

		return $wpdb->insert(
			$this->error_logs_table,
			$data,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get error logs with limit.
	 *
	 * @param int $limit Number of logs to retrieve.
	 *
	 * @return array Error logs.
	 */
	public function get_error_logs( int $limit = 50 ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->error_logs_table} ORDER BY timestamp DESC LIMIT %d",
				$limit
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Clean up old error logs.
	 *
	 * @param int $days Number of days to keep logs.
	 *
	 * @return int Number of deleted records.
	 */
	public function cleanup_error_logs( int $days = 30 ): int {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->error_logs_table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return false !== $result ? (int) $result : 0;
	}

	/**
	 * Run full cleanup with specified options.
	 *
	 * @param array $options Cleanup options.
	 *
	 * @return array Cleanup results.
	 */
	public function run_full_cleanup( array $options = array() ): array {
		$defaults = array(
			'download_requests_days' => 365,
			'error_logs_days'        => 30,
		);

		$options = array_merge( $defaults, $options );
		$results = array();

		// Cleanup download requests.
		$results['download_requests'] = $this->cleanup_download_requests( $options['download_requests_days'] );

		// Cleanup error logs.
		$results['error_logs'] = $this->cleanup_error_logs( $options['error_logs_days'] );

		return $results;
	}

	/**
	 * Get database statistics.
	 *
	 * @return array Database statistics.
	 */
	public function get_statistics(): array {
		global $wpdb;
		$stats = array();

		// Total download requests.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['total_downloads'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->download_requests_table}"
		);

		// Downloads today.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['downloads_today'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->download_requests_table}
			 WHERE DATE(download_date) = CURDATE()"
		);

		// Downloads this week.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['downloads_this_week'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->download_requests_table}
			 WHERE YEARWEEK(download_date, 1) = YEARWEEK(CURDATE(), 1)"
		);

		// Downloads this month.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['downloads_this_month'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->download_requests_table}
			 WHERE YEAR(download_date) = YEAR(CURDATE())
			 AND MONTH(download_date) = MONTH(CURDATE())"
		);

		// Error logs count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats['error_logs_count'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->error_logs_table}"
		);

		return $stats;
	}

	/**
	 * Get WordPress database instance.
	 *
	 * @return wpdb WordPress database instance.
	 */
	public function get_wpdb(): wpdb {
		return $this->wpdb;
	}

	/**
	 * Get error logs table name.
	 *
	 * @return string Table name.
	 */
	public function get_error_logs_table(): string {
		return $this->error_logs_table;
	}
}
