<?php
/**
 * Download Handler Class
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRL_Download_Handler {

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
	 * Submit download form
	 */
	public function submit_download_form() {
		check_ajax_referer( 'wprl_download_nonce', 'nonce' );

		$post_id     = intval( $_POST['post_id'] );
		$user_name   = sanitize_text_field( $_POST['user_name'] );
		$user_email  = sanitize_email( $_POST['user_email'] );
		$user_mobile = sanitize_text_field( $_POST['user_mobile'] );

		// Validate required fields.
		if ( empty( $user_name ) || empty( $user_mobile ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please fill in all required fields.', 'wp-resource-library' ),
				)
			);
		}

		// Validate mobile number
		if ( ! $this->is_valid_mobile( $user_mobile ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please enter a valid mobile number (minimum 7 digits).', 'wp-resource-library' ),
				)
			);
		}

		// Validate email.
		if ( ! empty( $user_email ) && ! is_email( $user_email ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please enter a valid email address.', 'wp-resource-library' ),
				)
			);
		}

		// Validate post exists and has file.
		$file_url = get_post_meta( $post_id, '_wprl_file_url', true );
		if ( empty( $file_url ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'File not found.', 'wp-resource-library' ),
				)
			);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wprl_download_requests';

		// Save download request.
		$result = $wpdb->insert(
			$table_name,
			array(
				'post_id'       => $post_id,
				'user_name'     => $user_name,
				'user_email'    => $user_email,
				'user_mobile'   => $user_mobile,
				'download_date' => current_time( 'mysql' ),
				'ip_address'    => $this->get_user_ip(),
				'user_agent'    => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result === false ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Error saving your request. Please try again.', 'wp-resource-library' ),
				)
			);
		}

		// Update download count.
		$current_count = get_post_meta( $post_id, '_wprl_download_count', true );
		$new_count     = intval( $current_count ) + 1;
		update_post_meta( $post_id, '_wprl_download_count', $new_count );

		// Generate download token.
		$download_token = wp_generate_password( 32, false );
		set_transient(
			'wprl_download_' . $download_token,
			array(
				'post_id'    => $post_id,
				'user_email' => $user_email,
				'expires'    => time() + ( 24 * 60 * 60 ), // 24 hours
			),
			24 * 60 * 60
		);

		// Generate download URL
		$download_url = add_query_arg(
			array(
				'wprl_download' => '1',
				'token'        => $download_token,
				'post_id'      => $post_id,
			),
			home_url()
		);

		wp_send_json_success(
			array(
				'message'        => esc_html__( 'Thank you! Your download will start shortly.', 'wp-resource-library' ),
				'download_token' => $download_token,
				'download_url'   => $download_url,
			)
		);
	}

	/**
	 * Process download
	 */
	public function process_download() {
		$token = sanitize_text_field( $_POST['token'] );

		if ( empty( $token ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid download token.', 'wp-resource-library' ),
				)
			);
		}

		$download_data = get_transient( 'wprl_download_' . $token );

		if ( ! $download_data || $download_data['expires'] < time() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Download token has expired. Please request the file again.', 'wp-resource-library' ),
				)
			);
		}

		$post_id  = $download_data['post_id'];
		$file_url = get_post_meta( $post_id, '_wprl_file_url', true );

		if ( empty( $file_url ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'File not found.', 'wp-resource-library' ),
				)
			);
		}

		// Don't delete the token here - it will be deleted in handle_download_request
		// Just return the download URL
		wp_send_json_success(
			array(
				'download_url' => add_query_arg(
					array(
						'wprl_download' => '1',
						'token'        => $token,
						'post_id'      => $post_id,
					),
					home_url()
				),
			)
		);
	}

	/**
	 * Handle download request.
	 */
	public function handle_download_request() {
		if ( isset( $_GET['wprl_download'] ) && $_GET['wprl_download'] == '1' ) {
			$post_id = intval( $_GET['post_id'] );
			$token   = sanitize_text_field( $_GET['token'] );

			if ( empty( $post_id ) || empty( $token ) ) {
				wp_die( esc_html__( 'Invalid download request.', 'wp-resource-library' ) );
			}

			// Validate the download token
			$download_data = get_transient( 'wprl_download_' . $token );

			if ( ! $download_data ) {
				wp_die( esc_html__( 'Download token has expired or is invalid. Please request the file again.', 'wp-resource-library' ) );
			}

			// Check if token matches the post ID
			if ( $download_data['post_id'] != $post_id ) {
				wp_die( esc_html__( 'Invalid download token for this file.', 'wp-resource-library' ) );
			}

			// Check if token has expired
			if ( $download_data['expires'] < time() ) {
				// Clean up expired token
				delete_transient( 'wprl_download_' . $token );
				wp_die( esc_html__( 'Download token has expired. Please request the file again.', 'wp-resource-library' ) );
			}

			// Keep the token valid for 24 hours to allow multiple downloads

			$file_id  = get_post_meta( $post_id, '_wprl_file_id', true );
			$file_url = get_post_meta( $post_id, '_wprl_file_url', true );

			if ( empty( $file_url ) ) {
				wp_die( esc_html__( 'File not found.', 'wp-resource-library' ) );
			}

			// Get file path.
			if ( $file_id ) {
				$file_path = get_attached_file( $file_id );
			} else {
				// Handle external URLs.
				$file_path = $file_url;
			}

			if ( $file_id && ( ! $file_path || ! file_exists( $file_path ) ) ) {
				wp_die( esc_html__( 'File not found on server.', 'wp-resource-library' ) );
			}

			// Get file info.
			$filename = $file_id ? basename( $file_path ) : basename( $file_url );

			// Set headers for download.
			if ( $file_id && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				$mime_type = wp_check_filetype( $file_path );

				header( 'Content-Type: ' . $mime_type['type'] );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				header( 'Content-Length: ' . $file_size );
				header( 'Cache-Control: private' );
				header( 'Pragma: private' );
				header( 'Expires: 0' );

				// Output file.
				readfile( $file_path );
			} else {
				// Redirect to external URL.
				wp_redirect( $file_url );
			}

			exit;
		}
	}

	/**
	 * Get user IP address
	 */
	private function get_user_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}

	/**
	 * Validate mobile number
	 */
	private function is_valid_mobile( $mobile ) {
		// Remove all non-digit characters for validation
		$digits_only = preg_replace( '/[^\d]/', '', $mobile );

		// Check if it contains only allowed characters
		if ( ! preg_match( '/^[\d\s\-\+\(\)]+$/', $mobile ) ) {
			return false;
		}

		// Check minimum length (at least 7 digits)
		if ( strlen( $digits_only ) < 7 ) {
			return false;
		}

		// Check maximum length (no more than 15 digits - international standard)
		if ( strlen( $digits_only ) > 15 ) {
			return false;
		}

		return true;
	}

	/**
	 * Direct download for logged-in users.
	 */
	public function direct_download_for_logged_users() {
		// Check if user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must be logged in to download files directly.', 'wp-resource-library' ),
				)
			);
		}

		check_ajax_referer( 'wprl_direct_download_nonce', 'nonce' );

		$post_id = intval( $_POST['post_id'] );

		// Validate post exists and has file.
		$file_url = get_post_meta( $post_id, '_wprl_file_url', true );
		if ( empty( $file_url ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'File not found.', 'wp-resource-library' ),
				)
			);
		}

		// Get current user data.
		$current_user = wp_get_current_user();
		$user_name    = $current_user->display_name;
		$user_email   = $current_user->user_email;
		$user_mobile  = get_user_meta( $current_user->ID, 'billing_phone', true ) ?:
			get_user_meta( $current_user->ID, 'phone', true ) ?:
				esc_html__( 'Not provided', 'wp-resource-library' );

		// Save download request with user data.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wprl_download_requests';

		$result = $wpdb->insert(
			$table_name,
			array(
				'post_id'       => $post_id,
				'user_name'     => $user_name,
				'user_email'    => $user_email,
				'user_mobile'   => $user_mobile,
				'download_date' => current_time( 'mysql' ),
				'ip_address'    => $this->get_user_ip(),
				'user_agent'    => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( $result === false ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error saving your request. Please try again.', 'wp-resource-library' ),
				)
			);
		}

		// Update download count
		$current_count = get_post_meta( $post_id, '_wprl_download_count', true );
		$new_count     = intval( $current_count ) + 1;
		update_post_meta( $post_id, '_wprl_download_count', $new_count );

		// Generate download token.
		$download_token = wp_generate_password( 32, false );
		set_transient(
			'wprl_download_' . $download_token,
			array(
				'post_id'    => $post_id,
				'user_email' => $user_email,
				'expires'    => time() + ( 24 * 60 * 60 ),
			),
			24 * 60 * 60
		);

		// Generate download URL.
		$download_url = add_query_arg(
			array(
				'wprl_download' => '1',
				'token'        => $download_token,
				'post_id'      => $post_id,
			),
			home_url()
		);

		wp_send_json_success(
			array(
				'download_url' => $download_url,
			)
		);
	}
}
