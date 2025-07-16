<?php
/**
 * Utils Class - Common utility functions
 *
 * @package WPResourceLibrary
 */

namespace WPResourceLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utils class for common utility functions and helpers.
 */
class Utils {

	/**
	 * Check if frontend scripts/styles should be loaded.
	 *
	 * @return bool
	 */
	public static function is_frontend_script_required(): bool {
		return self::is_files_archive() ||
				self::is_single_file() ||
				self::is_file_category();
	}

	/**
	 * Check if we're on files library archive page.
	 *
	 * @return bool
	 */
	public static function is_files_archive(): bool {
		return is_post_type_archive( 'files_library' );
	}

	/**
	 * Check if we're on single file page.
	 *
	 * @return bool
	 */
	public static function is_single_file(): bool {
		return is_singular( 'files_library' );
	}

	/**
	 * Check if we're on file category page.
	 *
	 * @return bool
	 */
	public static function is_file_category(): bool {
		return is_tax( 'file_category' );
	}

	/**
	 * Get user IP address.
	 *
	 * @return string
	 */
	public static function get_user_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$server_value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				foreach ( explode( ',', $server_value ) as $ip ) {
					$ip = trim( $ip );

					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return '';
	}

	/**
	 * Generate secure token.
	 *
	 * @param int $length Token length.
	 *
	 * @return string Generated token.
	 */
	public static function generate_token( int $length = 32 ): string {
		return wp_generate_password( $length, false );
	}

	/**
	 * Check if user can manage plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get plugin URL.
	 *
	 * @param string $path Optional path to append.
	 *
	 * @return string
	 */
	public static function get_plugin_url( string $path = '' ): string {
		return WPRL_PLUGIN_URL . ltrim( $path, '/' );
	}

	/**
	 * Get plugin path.
	 *
	 * @param string $path Optional path to append.
	 *
	 * @return string
	 */
	public static function get_plugin_path( string $path = '' ): string {
		return WPRL_PLUGIN_PATH . ltrim( $path, '/' );
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public static function get_version(): string {
		return WPRL_VERSION;
	}

	/**
	 * Validate mobile number.
	 *
	 * @param string $mobile Mobile number to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_mobile( string $mobile ): bool {
		// Remove all non-digit characters for validation.
		$digits_only = preg_replace( '/[^\d]/', '', $mobile );

		// Check if it contains only allowed characters.
		if ( ! preg_match( '/^[\d\s\-\+\(\)]+$/', $mobile ) ) {
			return false;
		}

		// Check minimum length (7 digits).
		if ( strlen( $digits_only ) < 7 ) {
			return false;
		}

		// Check maximum length (20 characters including formatting).
		if ( strlen( $mobile ) > 20 ) {
			return false;
		}

		return true;
	}

	/**
	 * Get file extension from URL or filename.
	 *
	 * @param string $file File path or URL.
	 *
	 * @return string File extension.
	 */
	public static function get_file_extension( string $file ): string {
		return strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	}

	/**
	 * Check if current request is AJAX.
	 *
	 * @return bool
	 */
	public static function is_ajax_request(): bool {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}

	/**
	 * Check if current request is from admin.
	 *
	 * @return bool
	 */
	public static function is_admin_request(): bool {
		return is_admin() && ! self::is_ajax_request();
	}

	/**
	 * Get consolidated file data for a post.
	 *
	 * @param int $post_id Post ID. If null, uses get_the_ID().
	 *
	 * @return array File data array with url, size, type, download_count, file_id.
	 */
	public static function get_file_data( int $post_id = 0 ): array {
		if ( 0 === $post_id ) {
			$post_id = get_the_ID();
		}

		$file_data = get_post_meta( $post_id, '_wprl_file_data', true );

		// Return default structure if empty.
		if ( empty( $file_data ) || ! is_array( $file_data ) ) {
			return array(
				'url'            => '',
				'size'           => 0,
				'type'           => '',
				'download_count' => 0,
				'file_id'        => 0,
			);
		}

		return $file_data;
	}

	/**
	 * Update file data for a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Data to update.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function update_file_data( int $post_id, array $data ): bool {
		$current_data = self::get_file_data( $post_id );
		$updated_data = array_merge( $current_data, $data );

		return update_post_meta( $post_id, '_wprl_file_data', $updated_data );
	}

	/**
	 * Increment download count for a file.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function increment_download_count( int $post_id ): bool {
		$file_data                   = self::get_file_data( $post_id );
		$file_data['download_count'] = intval( $file_data['download_count'] ) + 1;

		return self::update_file_data( $post_id, $file_data );
	}

	/**
	 * Get simplified file type from mime type or extension.
	 *
	 * @param string $file_type_or_url File mime type or file URL.
	 *
	 * @return string Simplified type like 'PDF', 'Video', 'Image', etc.
	 */
	public static function get_simplified_file_type( string $file_type_or_url ): string {
		// If it looks like a URL, extract extension.
		if ( filter_var( $file_type_or_url, FILTER_VALIDATE_URL ) ) {
			$extension = self::get_file_extension( $file_type_or_url );
		} else {
			// It's a mime type, extract extension from it.
			$extension = '';
			if ( strpos( $file_type_or_url, '/' ) !== false ) {
				$parts = explode( '/', $file_type_or_url );
				if ( count( $parts ) === 2 ) {
					$extension = $parts[1];
				}
			} else {
				$extension = $file_type_or_url;
			}
		}

		$extension = strtolower( $extension );

		// Map extensions to simplified types.
		$type_map = array(
			// Documents.
			'pdf'  => 'PDF',
			'doc'  => 'Document',
			'docx' => 'Document',
			'txt'  => 'Document',
			'rtf'  => 'Document',
			'odt'  => 'Document',

			// Spreadsheets.
			'xls'  => 'Spreadsheet',
			'xlsx' => 'Spreadsheet',
			'csv'  => 'Spreadsheet',
			'ods'  => 'Spreadsheet',

			// Presentations.
			'ppt'  => 'Presentation',
			'pptx' => 'Presentation',
			'odp'  => 'Presentation',

			// Images.
			'jpg'  => 'Image',
			'jpeg' => 'Image',
			'png'  => 'Image',
			'gif'  => 'Image',
			'bmp'  => 'Image',
			'svg'  => 'Image',
			'webp' => 'Image',

			// Videos.
			'mp4'  => 'Video',
			'avi'  => 'Video',
			'mov'  => 'Video',
			'wmv'  => 'Video',
			'flv'  => 'Video',
			'webm' => 'Video',
			'mkv'  => 'Video',

			// Audio.
			'mp3'  => 'Audio',
			'wav'  => 'Audio',
			'flac' => 'Audio',
			'aac'  => 'Audio',
			'ogg'  => 'Audio',

			// Archives.
			'zip'  => 'Archive',
			'rar'  => 'Archive',
			'7z'   => 'Archive',
			'tar'  => 'Archive',
			'gz'   => 'Archive',
		);

		return $type_map[ $extension ] ?? 'File';
	}

	/**
	 * Log error message with context.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @param string $level   Error level (error, warning, info, debug).
	 */
	public static function log_error( string $message, array $context = array(), string $level = 'error' ) {
		// Only log if WP_DEBUG_LOG is enabled.
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}

		// Also log to database for admin viewing.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		Database::db()->insert_error_log(
			array(
				'level'      => sanitize_text_field( $level ),
				'message'    => sanitize_text_field( $message ),
				'context'    => $context,
				'user_id'    => get_current_user_id(),
				'ip_address' => self::get_user_ip(),
				'url'        => esc_url_raw( $request_uri ),
			)
		);
	}

	/**
	 * Get error logs from database.
	 *
	 * @param int $limit Number of logs to retrieve.
	 *
	 * @return array Error logs.
	 */
	public static function get_error_logs( int $limit = 50 ): array {
		return Database::db()->get_error_logs( $limit );
	}











	/**
	 * Get cached file types with counts.
	 *
	 * @return array File types with counts.
	 */
	public static function get_cached_file_types(): array {
		$cache_key  = 'wprl_file_types_' . gmdate( 'Y-m-d-H' ); // Cache for 1 hour.
		$file_types = get_transient( $cache_key );

		if ( false === $file_types ) {
			$posts = get_posts(
				array(
					'post_type'      => 'files_library',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'meta_query'     => array(
						array(
							'key'     => '_wprl_file_data',
							'compare' => 'EXISTS',
						),
					),
				)
			);

			$file_types = array();
			foreach ( $posts as $post ) {
				$file_data = self::get_file_data( $post->ID );
				if ( ! empty( $file_data['type'] ) ) {
					if ( ! isset( $file_types[ $file_data['type'] ] ) ) {
						$file_types[ $file_data['type'] ] = 0;
					}
					++$file_types[ $file_data['type'] ];
				}
			}

			// Sort by count (descending).
			arsort( $file_types );

			// Cache for 1 hour.
			set_transient( $cache_key, $file_types, HOUR_IN_SECONDS );

			self::log_error(
				'File types cache refreshed',
				array(
					'types_count' => count( $file_types ),
					'total_files' => array_sum( $file_types ),
				),
				'info'
			);
		}

		return $file_types;
	}

	/**
	 * Clear file types cache.
	 */
	public static function clear_file_types_cache() {
		$cache_key       = 'wprl_file_types_' . gmdate( 'Y-m-d-H' );
		$current_deleted = delete_transient( $cache_key );

		// Also clear previous hour cache.
		$prev_cache_key = 'wprl_file_types_' . gmdate( 'Y-m-d-H', strtotime( '-1 hour' ) );
		$prev_deleted   = delete_transient( $prev_cache_key );

		// Also clear next hour cache (in case of timezone issues).
		$next_cache_key = 'wprl_file_types_' . gmdate( 'Y-m-d-H', strtotime( '+1 hour' ) );
		$next_deleted   = delete_transient( $next_cache_key );

		self::log_error(
			'File types cache cleared',
			array(
				'current_cache_cleared' => $current_deleted,
				'prev_cache_cleared'    => $prev_deleted,
				'next_cache_cleared'    => $next_deleted,
				'cache_keys'            => array( $cache_key, $prev_cache_key, $next_cache_key ),
			),
			'info'
		);
	}

	/**
	 * Clear all plugin caches.
	 */
	public static function clear_all_caches() {
		// Clear file types cache.
		self::clear_file_types_cache();

		// Clear any other plugin caches here in the future.
		self::log_error( 'All plugin caches cleared', array(), 'info' );
	}
}
