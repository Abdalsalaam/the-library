<?php
/**
 * CSV Export Class
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRL_CSV_Export {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action(
			'admin_init',
			array(
				$this,
				'handle_csv_export',
			)
		);
	}

	/**
	 * Handle CSV export
	 */
	public function handle_csv_export() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wprl-download-requests' &&
			isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
			}

			$this->export_download_requests();
		}
	}

	/**
	 * Export download requests to CSV
	 */
	private function export_download_requests() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wprl_download_requests';

		// Get all download requests with post titles.
		$query = "SELECT dr.*, p.post_title
                  FROM $table_name dr
                  LEFT JOIN {$wpdb->posts} p ON dr.post_id = p.ID
                  ORDER BY dr.download_date DESC";

		$requests = $wpdb->get_results( $query );

		if ( empty( $requests ) ) {
			wp_die( esc_html__( 'No download requests found to export.', 'wp-resource-library' ) );
		}

		// Set headers for CSV download
		$filename = 'download-requests-' . date( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create file pointer connected to the output stream.
		$output = fopen( 'php://output', 'w' );

		// Add BOM for UTF-8.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Add CSV headers.
		$headers = array(
			esc_html__( 'ID', 'wp-resource-library' ),
			__( 'File Title', 'wp-resource-library' ),
			__( 'User Name', 'wp-resource-library' ),
			__( 'User Email', 'wp-resource-library' ),
			__( 'User Mobile', 'wp-resource-library' ),
			__( 'Download Date', 'wp-resource-library' ),
			__( 'IP Address', 'wp-resource-library' ),
			__( 'User Agent', 'wp-resource-library' ),
		);

		fputcsv( $output, $headers );

		// Add data rows
		foreach ( $requests as $request ) {
			$row = array(
				$request->id,
				$request->post_title ? $request->post_title : esc_html__( 'File not found', 'wp-resource-library' ),
				$request->user_name,
				$request->user_email,
				$request->user_mobile,
				$request->download_date,
				$request->ip_address,
				$request->user_agent,
			);

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Export filtered download requests
	 */
	public function export_filtered_requests( $filters = array() ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wprl_download_requests';

		$where_conditions = array();
		$where_values     = array();

		// Apply filters
		if ( ! empty( $filters['start_date'] ) ) {
			$where_conditions[] = 'dr.download_date >= %s';
			$where_values[]     = $filters['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where_conditions[] = 'dr.download_date <= %s';
			$where_values[]     = $filters['end_date'] . ' 23:59:59';
		}

		if ( ! empty( $filters['post_id'] ) ) {
			$where_conditions[] = 'dr.post_id = %d';
			$where_values[]     = intval( $filters['post_id'] );
		}

		if ( ! empty( $filters['user_email'] ) ) {
			$where_conditions[] = 'dr.user_email LIKE %s';
			$where_values[]     = '%' . $wpdb->esc_like( $filters['user_email'] ) . '%';
		}

		// Build query.
		$query = "SELECT dr.*, p.post_title 
                  FROM $table_name dr 
                  LEFT JOIN {$wpdb->posts} p ON dr.post_id = p.ID";

		if ( ! empty( $where_conditions ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where_conditions );
		}

		$query .= ' ORDER BY dr.download_date DESC';

		if ( ! empty( $where_values ) ) {
			$requests = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) );
		} else {
			$requests = $wpdb->get_results( $query );
		}

		return $requests;
	}
}
