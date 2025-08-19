<?php
/**
 * Download Handler Class
 *
 * @package TheLibrary
 */

namespace TheLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download Handler class for managing file downloads and user data collection.
 */
class Download_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_ajax_wprl_submit_download_form', array( $this, 'submit_download_form' ) );
		add_action( 'wp_ajax_nopriv_wprl_submit_download_form', array( $this, 'submit_download_form' ) );
		add_action( 'wp_ajax_wprl_process_download', array( $this, 'process_download' ) );
		add_action( 'wp_ajax_nopriv_wprl_process_download', array( $this, 'process_download' ) );
		add_action( 'wp_ajax_wprl_direct_download', array( $this, 'direct_download_for_logged_users' ) );
		add_action( 'init', array( $this, 'handle_download_request' ) );
	}

	/**
	 * Submit download form with proper validation and security.
	 */
	public function submit_download_form() {
		check_ajax_referer( 'wprl_download_nonce', 'nonce' );

		// Validate required POST parameters exist.
		if ( ! isset( $_POST['post_id'] ) || ! isset( $_POST['user_name'] ) || ! isset( $_POST['user_mobile'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Required fields are missing.', 'the-library' ),
				)
			);
		}

		$post_id     = intval( wp_unslash( $_POST['post_id'] ) );
		$user_name   = sanitize_text_field( wp_unslash( $_POST['user_name'] ) );
		$user_email  = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$user_mobile = sanitize_text_field( wp_unslash( $_POST['user_mobile'] ) );

		// Validate required fields.
		if ( empty( $user_name ) || empty( $user_mobile ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please fill in all required fields.', 'the-library' ),
				)
			);
		}

		// Validate mobile number.
		if ( ! Utils::is_valid_mobile( $user_mobile ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please enter a valid mobile number (minimum 7 digits).', 'the-library' ),
				)
			);
		}

		// Validate email.
		if ( ! empty( $user_email ) && ! is_email( $user_email ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please enter a valid email address.', 'the-library' ),
				)
			);
		}

		// Validate post exists and has file.
		$file_data = Utils::get_file_data( $post_id );
		if ( empty( $file_data['url'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'File not found.', 'the-library' ),
				)
			);
		}

		// Save download request.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$result = Database::get_instance()->insert_download_request(
			array(
				'post_id'       => $post_id,
				'user_name'     => $user_name,
				'user_email'    => $user_email,
				'user_mobile'   => $user_mobile,
				'download_date' => current_time( 'mysql' ),
				'ip_address'    => Utils::get_user_ip(),
				'user_agent'    => $user_agent,
			)
		);

		// Log database errors.
		if ( false === $result ) {
			Utils::log_error(
				'Failed to insert download request',
				array(
					'post_id'    => $post_id,
					'user_email' => $user_email,
					'db_error'   => Database::get_instance()->get_wpdb()->last_error,
				)
			);
			wp_send_json_error( array( 'message' => esc_html__( 'Database error occurred. Please try again.', 'the-library' ) ) );
		}

		// Generate download token and URL.
		$download_url = $this->generate_download_token_and_url( $post_id );

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Thank you! Your download will start shortly.', 'the-library' ),
				'download_url' => $download_url,
			)
		);
	}

	/**
	 * Process download with proper validation.
	 */
	public function process_download() {
		check_ajax_referer( 'wprl_process_download_nonce', 'nonce' );

		// Validate token parameter exists.
		if ( ! isset( $_POST['token'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Download token is required.', 'the-library' ),
				)
			);
		}

		$token = sanitize_text_field( wp_unslash( $_POST['token'] ) );

		if ( empty( $token ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid download token.', 'the-library' ),
				)
			);
		}

		$download_data = get_transient( 'wprl_download_' . $token );

		if ( ! $download_data ) {
			Utils::log_error(
				'Invalid or expired download token',
				array(
					'token' => $token,
				),
				'warning'
			);

			wp_send_json_error(
				array(
					'message' => esc_html__( 'Download token has expired. Please request the file again.', 'the-library' ),
				)
			);
		}

		$post_id   = $download_data['post_id'];
		$file_data = Utils::get_file_data( $post_id );

		if ( empty( $file_data['url'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'File not found.', 'the-library' ),
				)
			);
		}

		// Don't delete the token here - it will be deleted in handle_download_request.
		// Just return the download URL.
		wp_send_json_success(
			array(
				'download_url' => add_query_arg(
					array(
						'wprl_download' => '1',
						'token'         => $token,
						'post_id'       => $post_id,
					),
					home_url()
				),
			)
		);
	}

	/**
	 * Handle download request with proper validation.
	 */
	public function handle_download_request() {
		if ( isset( $_GET['wprl_download'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wprl_download'] ) ) ) {
			// Validate required parameters.
			if ( ! isset( $_GET['post_id'] ) || ! isset( $_GET['token'] ) ) {
				wp_die( esc_html__( 'Invalid download request. Missing parameters.', 'the-library' ) );
			}

			$post_id = intval( wp_unslash( $_GET['post_id'] ) );
			$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );

			if ( $post_id <= 0 || empty( $token ) ) {
				wp_die( esc_html__( 'Invalid download request.', 'the-library' ) );
			}

			// Validate the download token.
			$download_data = get_transient( 'wprl_download_' . $token );

			if ( ! $download_data ) {
				wp_die( esc_html__( 'Download token has expired or is invalid. Please request the file again.', 'the-library' ) );
			}

			// Check if token matches the post ID.
			if ( (int) $download_data['post_id'] !== $post_id ) {
				wp_die( esc_html__( 'Invalid download token for this file.', 'the-library' ) );
			}

			$file_data = Utils::get_file_data( $post_id );

			if ( empty( $file_data['url'] ) ) {
				wp_die( esc_html__( 'File not found.', 'the-library' ) );
			}

			// Get file path.
			if ( $file_data['file_id'] ) {
				$file_path = get_attached_file( $file_data['file_id'] );
			} else {
				// Handle external URLs.
				$file_path = $file_data['url'];
			}

			if ( $file_data['file_id'] && ( ! $file_path || ! file_exists( $file_path ) ) ) {
				wp_die( esc_html__( 'File not found on server.', 'the-library' ) );
			}

			// Get file info.
			$filename = $file_data['file_id'] ? basename( $file_path ) : basename( $file_data['url'] );

			// Set headers for download.
			if ( $file_data['file_id'] && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				$mime_type = wp_check_filetype( $file_path );

				header( 'Content-Type: ' . esc_attr( $mime_type['type'] ) );
				header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
				header( 'Content-Length: ' . esc_attr( $file_size ) );
				header( 'Cache-Control: private' );
				header( 'Pragma: private' );
				header( 'Expires: 0' );

				global $wp_filesystem;
				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
					WP_Filesystem();
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $wp_filesystem->get_contents( $file_path );
			} else {
				// Redirect to external URL.
				wp_redirect( $file_data['url'] );
			}

			exit;
		}
	}

	/**
	 * Direct download for logged-in users with proper validation.
	 */
	public function direct_download_for_logged_users() {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You must be logged in to download files directly.', 'the-library' ),
				)
			);
		}

		check_ajax_referer( 'wprl_direct_download_nonce', 'nonce' );

		// Validate post_id parameter.
		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Post ID is required.', 'the-library' ),
				)
			);
		}

		$post_id = intval( wp_unslash( $_POST['post_id'] ) );

		if ( $post_id <= 0 ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid post ID.', 'the-library' ),
				)
			);
		}

		// Validate post exists and has file.
		$file_data = Utils::get_file_data( $post_id );
		if ( empty( $file_data['url'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'File not found.', 'the-library' ),
				)
			);
		}

		// Get current user data.
		$current_user = wp_get_current_user();
		$user_name    = $current_user->display_name;
		$user_email   = $current_user->user_email;
		$user_mobile  = get_user_meta( $current_user->ID, 'billing_phone', true );
		if ( empty( $user_mobile ) ) {
			$user_mobile = get_user_meta( $current_user->ID, 'phone', true );
		}
		if ( empty( $user_mobile ) ) {
			$user_mobile = esc_html__( 'Not provided', 'the-library' );
		}

		// Save download request with user data.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$result = Database::get_instance()->insert_download_request(
			array(
				'post_id'       => $post_id,
				'user_name'     => $user_name,
				'user_email'    => $user_email,
				'user_mobile'   => $user_mobile,
				'download_date' => current_time( 'mysql' ),
				'ip_address'    => Utils::get_user_ip(),
				'user_agent'    => $user_agent,
			)
		);

		if ( false === $result ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Error saving your request. Please try again.', 'the-library' ),
				)
			);
		}

		// Generate download token and URL.
		$download_url = $this->generate_download_token_and_url( $post_id );

		wp_send_json_success(
			array(
				'download_url' => $download_url,
			)
		);
	}

	/**
	 * Generate download token and URL.
	 *
	 * @param int $post_id    Post ID.
	 *
	 * @return string Download URL.
	 */
	private function generate_download_token_and_url( int $post_id ): string {
		// Update download count using utility function.
		Utils::increment_download_count( $post_id );

		// Generate download token.
		$download_token = Utils::generate_token( 32 );
		set_transient(
			'wprl_download_' . $download_token,
			array( 'post_id' => $post_id ),
			24 * 60 * 60 // 24 hours.
		);

		// Generate download URL.
		return add_query_arg(
			array(
				'wprl_download' => '1',
				'token'         => $download_token,
				'post_id'       => $post_id,
			),
			home_url()
		);
	}
}
