<?php
/**
 * CSV Export Class
 *
 * @package WPResourceLibrary
 */

namespace WPResourceLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CSV Export class for exporting download requests data.
 */
class CSV_Export {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
	}

	/**
	 * Handle CSV export.
	 */
	public function handle_csv_export() {
		if (
			isset( $_GET['page'] )
			&& 'wprl-download-requests' === sanitize_text_field( wp_unslash( $_GET['page'] ) )
			&& isset( $_GET['action'] )
			&& 'export_csv' === sanitize_text_field( wp_unslash( $_GET['action'] ) )
			&& isset( $_GET['nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'wprl_export_csv' )
		) {

			if ( ! Utils::current_user_can_manage() ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'the-library' ) );
			}

			$this->export_download_requests();
		}
	}

	/**
	 * Export download requests to CSV.
	 */
	private function export_download_requests() {
		// Get all download requests with post titles.
		$requests = Database::get_instance()->get_all_download_requests_for_export();

		if ( empty( $requests ) ) {
			wp_die( esc_html__( 'No download requests found to export.', 'the-library' ) );
		}

		// Set headers for CSV download.
		$filename = 'download-requests-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create file pointer connected to the output stream.
		$output = fopen( 'php://output', 'w' );

		// Add BOM for UTF-8.
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Add CSV headers.
		$headers = array(
			esc_html__( 'ID', 'the-library' ),
			__( 'File Title', 'the-library' ),
			__( 'User Name', 'the-library' ),
			__( 'User Email', 'the-library' ),
			__( 'User Mobile', 'the-library' ),
			__( 'Download Date', 'the-library' ),
			__( 'IP Address', 'the-library' ),
			__( 'User Agent', 'the-library' ),
		);

		// Explicitly pass separator, enclosure, and escape to avoid PHP deprecation warnings.
		fputcsv( $output, $headers, ',', '"', '\\' );

		// Add data rows.
		foreach ( $requests as $request ) {
			$row = array(
				$request->id,
				$request->post_title ? $request->post_title : esc_html__( 'File not found', 'the-library' ),
				$request->user_name,
				$request->user_email,
				$request->user_mobile,
				$request->download_date,
				$request->ip_address,
				$request->user_agent,
			);

			// Explicitly pass separator, enclosure, and escape to avoid PHP deprecation warnings.
			fputcsv( $output, $row, ',', '"', '\\' );
		}

		// Close output stream - safe to ignore WPCS warning for php://output.
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
