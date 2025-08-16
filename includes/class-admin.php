<?php
/**
 * Admin Class.
 *
 * @package WPResourceLibrary
 */

namespace WPResourceLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for handling admin interface and functionality.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_wprl_delete_download_request', array( $this, 'delete_download_request' ) );
		add_action( 'wp_ajax_wprl_clear_logs', array( $this, 'clear_logs_ajax' ) );
		add_action( 'wp_ajax_wprl_run_cleanup', array( $this, 'run_cleanup_ajax' ) );
		add_action( 'wp_ajax_wprl_clear_cache', array( $this, 'clear_cache_ajax' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=wprl_files_library',
			esc_html__( 'Download Requests', 'the-library' ),
			esc_html__( 'Download Requests', 'the-library' ),
			'manage_options',
			'wprl-download-requests',
			array( $this, 'download_requests_page' )
		);

		add_submenu_page(
			'edit.php?post_type=wprl_files_library',
			esc_html__( 'System Logs', 'the-library' ),
			esc_html__( 'System Logs', 'the-library' ),
			'manage_options',
			'wprl-system-logs',
			array( $this, 'system_logs_page' )
		);

		add_submenu_page(
			'edit.php?post_type=wprl_files_library',
			esc_html__( 'System Maintenance', 'the-library' ),
			esc_html__( 'Maintenance', 'the-library' ),
			'manage_options',
			'wprl-maintenance',
			array( $this, 'maintenance_page' )
		);
	}

	/**
	 * Download requests admin page.
	 */
	public function download_requests_page() {
		// Handle bulk actions with proper nonce verification.
		if ( isset( $_POST['action'] ) && 'bulk_delete' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) && isset( $_POST['request_ids'] ) ) {
			// Verify nonce for security.
			if ( ! isset( $_POST['wprl_bulk_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprl_bulk_action_nonce'] ) ), 'wprl_bulk_delete_requests' ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'the-library' ) );
			}

			// Check user permissions.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'the-library' ) );
			}

			$request_ids = array_map( 'intval', (array) wp_unslash( $_POST['request_ids'] ) );
			if ( ! empty( $request_ids ) ) {
				$deleted = Database::get_instance()->delete_download_requests( $request_ids );

				if ( $deleted > 0 ) {
					echo '<div class="notice notice-success"><p>' . esc_html(
						sprintf(
						/* translators: %d: number of deleted requests */
							_n( '%d request deleted successfully.', '%d requests deleted successfully.', $deleted, 'the-library' ),
							$deleted
						)
					) . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Error deleting requests. Please try again.', 'the-library' ) . '</p></div>';
				}
			}
		}

		// Pagination with proper sanitization.
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( wp_unslash( $_GET['paged'] ) ) ) : 1;

		// Search functionality with proper sanitization.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Get download requests using Database class.
		$results = Database::get_instance()->get_download_requests(
			array(
				'search'   => $search,
				'per_page' => $per_page,
				'page'     => $current_page,
			)
		);

		$requests    = $results['data'];
		$total_items = $results['total_items'];
		$total_pages = $results['total_pages'];

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Download Requests', 'the-library' ); ?></h1>

			<!-- Export button with nonce -->
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'   => 'wprl-download-requests',
						'action' => 'export_csv',
						'nonce'  => wp_create_nonce( 'wprl_export_csv' ),
					),
					admin_url( 'edit.php?post_type=wprl_files_library' )
				)
			);
			?>
						" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'the-library' ); ?>
			</a>

			<hr class="wp-header-end">

			<!-- Search form -->
			<form method="get" class="search-form">
				<input type="hidden" name="post_type" value="wprl_files_library">
				<input type="hidden" name="page" value="wprl-download-requests">
				<p class="search-box">
					<label class="screen-reader-text" for="request-search-input"><?php esc_html_e( 'Search requests:', 'the-library' ); ?></label>
					<input type="search" id="request-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_html_e( 'Search by name, email, or mobile...', 'the-library' ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_html_e( 'Search', 'the-library' ); ?>">
				</p>
			</form>

			<!-- Requests table -->
			<form method="post">
				<?php wp_nonce_field( 'wprl_bulk_delete_requests', 'wprl_bulk_action_nonce' ); ?>
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="action">
							<option value="-1"><?php esc_html_e( 'Bulk Actions', 'the-library' ); ?></option>
							<option value="bulk_delete"><?php esc_html_e( 'Delete', 'the-library' ); ?></option>
						</select>
						<input type="submit" class="button action" value="<?php esc_html_e( 'Apply', 'the-library' ); ?>">
					</div>

					<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							/* translators: %s: number of items */
							printf( esc_html( _n( '%s item', '%s items', $total_items, 'the-library' ) ), esc_html( number_format_i18n( $total_items ) ) );
							?>
						</span>

						<span class="pagination-links">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
										'next_text' => is_rtl() ? '&larr;' : '&rarr;',
										'total'     => $total_pages,
										'current'   => $current_page,
									)
								)
							);
							?>
						</span>
					</div>
					<?php endif; ?>
				</div>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-1">
							</td>
							<th scope="col" class="manage-column"><?php esc_html_e( 'File', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Name', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Email', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Mobile', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Download Date', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'IP Address', 'the-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Actions', 'the-library' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $requests ) ) : ?>
						<tr>
							<td colspan="8" class="no-items"><?php esc_html_e( 'No download requests found.', 'the-library' ); ?></td>
						</tr>
						<?php else : ?>
							<?php foreach ( $requests as $request ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="request_ids[]" value="<?php echo esc_attr( $request->id ); ?>">
							</th>
							<td>
								<?php if ( ! empty( $request->post_title ) ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $request->post_id ) ); ?>" target="_blank">
										<?php echo esc_html( $request->post_title ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'File not found', 'the-library' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $request->user_name ); ?></td>
							<td>
								<?php if ( ! empty( $request->user_email ) ) : ?>
									<a href="mailto:<?php echo esc_attr( $request->user_email ); ?>">
										<?php echo esc_html( $request->user_email ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Not provided', 'the-library' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $request->user_mobile ); ?></td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->download_date ) ) ); ?></td>
							<td><?php echo esc_html( $request->ip_address ); ?></td>
							<td>
								<button type="button" class="button button-small wprl-delete-request" data-id="<?php echo esc_attr( $request->id ); ?>">
									<?php esc_html_e( 'Delete', 'the-library' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	/**
	 * Delete download request via AJAX.
	 */
	public function delete_download_request() {
		check_ajax_referer( 'wprl_delete_request', 'nonce' );

		if ( ! Utils::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions to perform this action.', 'the-library' ) ) );
		}

		// Validate and sanitize input.
		if ( ! isset( $_POST['request_id'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request ID.', 'the-library' ) ) );
		}

		$request_id = intval( wp_unslash( $_POST['request_id'] ) );

		if ( $request_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request ID.', 'the-library' ) ) );
		}

		$result = Database::get_instance()->delete_download_request( $request_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Request deleted successfully.', 'the-library' ) ) );
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Error deleting request or request not found.', 'the-library' ) ) );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our plugin pages.
		if ( false === strpos( $hook, 'wprl-download-requests' ) && false === strpos( $hook, 'wprl-system-logs' ) && false === strpos( $hook, 'wprl-maintenance' ) ) {
			return;
		}

		wp_enqueue_style( 'wprl-admin-css', Utils::get_plugin_url( 'assets/css/admin.css' ), array(), Utils::get_version() );
		wp_enqueue_script( 'wprl-admin-js', Utils::get_plugin_url( 'assets/js/admin.js' ), array( 'jquery' ), Utils::get_version(), true );

		// Localize nonces and i18n strings for admin JS.
		wp_localize_script(
			'wprl-admin-js',
			'wprl_admin_nonces',
			array(
				'wprl_delete_request' => wp_create_nonce( 'wprl_delete_request' ),
				'wprl_clear_logs'     => wp_create_nonce( 'wprl_clear_logs' ),
				'wprl_run_cleanup'    => wp_create_nonce( 'wprl_run_cleanup' ),
				'wprl_clear_cache'    => wp_create_nonce( 'wprl_clear_cache' ),
			)
		);

		wp_localize_script(
			'wprl-admin-js',
			'wprl_admin_i18n',
			array(
				'confirmDeleteRequest' => __( 'Are you sure you want to delete this request?', 'the-library' ),
				'errorDeleteRequest'   => __( 'Error deleting request.', 'the-library' ),
				'confirmClearLogs'     => __( 'Are you sure you want to clear old logs?', 'the-library' ),
				'failedClearLogs'      => __( 'Failed to clear logs.', 'the-library' ),
				'running'              => __( 'Running...', 'the-library' ),
				'cleanupFailed'        => __( 'Cleanup failed. Please try again.', 'the-library' ),
				'clearing'             => __( 'Clearing...', 'the-library' ),
				'clearCacheFailed'     => __( 'Failed to clear cache. Please try again.', 'the-library' ),
			)
		);
	}

	/**
	 * System logs page.
	 */
	public function system_logs_page() {
		$database     = Database::get_instance();
		$table_exists = $database->table_exists( $database->get_error_logs_table() );
		$error_logs   = $table_exists ? Utils::get_error_logs( 100 ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'System Logs', 'the-library' ); ?></h1>

			<?php if ( ! $table_exists ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Error logs table does not exist.', 'the-library' ); ?></strong>
						<?php esc_html_e( 'Please deactivate and reactivate the plugin to create the required database tables.', 'the-library' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="wprl-logs-actions" style="margin: 20px 0;">
				<?php if ( $table_exists ) : ?>
					<button type="button" class="button" onclick="wprlClearLogs();">
						<?php esc_html_e( 'Clear Old Logs', 'the-library' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( ! $table_exists ) : ?>
				<p><?php esc_html_e( 'Error logs table is not available. Please reactivate the plugin.', 'the-library' ); ?></p>
			<?php elseif ( empty( $error_logs ) ) : ?>
				<p><?php esc_html_e( 'No error logs found.', 'the-library' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 150px;"><?php esc_html_e( 'Timestamp', 'the-library' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Level', 'the-library' ); ?></th>
							<th><?php esc_html_e( 'Message', 'the-library' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'User', 'the-library' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'IP Address', 'the-library' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $error_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->timestamp ); ?></td>
								<td>
									<span class="wprl-log-level wprl-log-<?php echo esc_attr( strtolower( $log->level ) ); ?>">
										<?php echo esc_html( $log->level ); ?>
									</span>
								</td>
								<td>
									<strong><?php echo esc_html( $log->message ); ?></strong>
									<?php if ( ! empty( $log->context ) ) : ?>
										<details style="margin-top: 5px;">
											<summary style="cursor: pointer; color: #666;">Context</summary>
											<pre style="background: #f9f9f9; padding: 10px; margin-top: 5px; font-size: 12px; overflow-x: auto;"><?php echo esc_html( $log->context ); ?></pre>
										</details>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $log->user_id ) : ?>
										<?php $user = get_user_by( 'id', $log->user_id ); ?>
										<?php echo $user ? esc_html( $user->display_name ) : esc_html( $log->user_id ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Guest', 'the-library' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log->ip_address ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle clear logs AJAX request.
	 */
	public function clear_logs_ajax() {
		check_ajax_referer( 'wprl_clear_logs', 'nonce' );

		if ( ! Utils::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'the-library' ) ) );
		}

		try {
			$deleted = Database::get_instance()->cleanup_error_logs( 7 ); // Keep only last 7 days.
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of deleted logs */
						esc_html__( '%d old logs cleared successfully.', 'the-library' ),
						$deleted
					),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * System maintenance page.
	 */
	public function maintenance_page() {
		// Get database statistics.
		$stats = Database::get_instance()->get_statistics();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'System Maintenance', 'the-library' ); ?></h1>

			<div class="wprl-stats-section" style="margin-bottom: 30px;">
				<h2><?php esc_html_e( 'Database Statistics', 'the-library' ); ?></h2>
				<div class="wprl-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
					<div class="wprl-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Total Downloads', 'the-library' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #2271b1;"><?php echo esc_html( number_format( $stats['total_downloads'] ) ); ?></p>
					</div>
					<div class="wprl-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Today', 'the-library' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a32a;"><?php echo esc_html( number_format( $stats['downloads_today'] ) ); ?></p>
					</div>
					<div class="wprl-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'This Week', 'the-library' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #996800;"><?php echo esc_html( number_format( $stats['downloads_this_week'] ) ); ?></p>
					</div>
					<div class="wprl-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'This Month', 'the-library' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #8c8f94;"><?php echo esc_html( number_format( $stats['downloads_this_month'] ) ); ?></p>
					</div>
					<div class="wprl-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
						<h3 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Error Logs', 'the-library' ); ?></h3>
						<p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;"><?php echo esc_html( number_format( $stats['error_logs_count'] ) ); ?></p>
					</div>
				</div>
			</div>

			<div class="wprl-maintenance-section">
				<h2><?php esc_html_e( 'Database Cleanup', 'the-library' ); ?></h2>
				<p><?php esc_html_e( 'Clean up old data to optimize database performance.', 'the-library' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Download Requests', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button" onclick="wprlRunCleanup('download_requests')">
								<?php esc_html_e( 'Clean Old Download Requests', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Remove download requests older than 1 year.', 'the-library' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Error Logs', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button" onclick="wprlRunCleanup('error_logs')">
								<?php esc_html_e( 'Clean Old Error Logs', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Remove error logs older than 30 days.', 'the-library' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Expired Transients', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button" onclick="wprlRunCleanup('transients')">
								<?php esc_html_e( 'Clean Expired Transients', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Remove expired download tokens and cache data.', 'the-library' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Orphaned Metadata', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button" onclick="wprlRunCleanup('orphaned_meta')">
								<?php esc_html_e( 'Clean Orphaned Metadata', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Remove metadata for deleted posts.', 'the-library' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Full Cleanup', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button button-primary" onclick="wprlRunCleanup('full')">
								<?php esc_html_e( 'Run Full Cleanup', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Run all cleanup tasks at once.', 'the-library' ); ?></p>
						</td>
					</tr>
				</table>

				<div id="wprl-cleanup-results" style="margin-top: 20px;"></div>
			</div>

			<div class="wprl-maintenance-section" style="margin-top: 40px;">
				<h2><?php esc_html_e( 'Cache Management', 'the-library' ); ?></h2>
				<p><?php esc_html_e( 'Manage plugin cache for better performance.', 'the-library' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'File Types Cache', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button" onclick="wprlClearCache('file_types')">
								<?php esc_html_e( 'Clear File Types Cache', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Force refresh of file types dropdown cache.', 'the-library' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'All Caches', 'the-library' ); ?></th>
						<td>
							<button type="button" class="button button-secondary" onclick="wprlClearCache('all')">
								<?php esc_html_e( 'Clear All Plugin Caches', 'the-library' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Clear all plugin-related cache data.', 'the-library' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle cleanup AJAX request.
	 */
	public function run_cleanup_ajax() {
		check_ajax_referer( 'wprl_run_cleanup', 'nonce' );

		if ( ! Utils::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'the-library' ) ) );
		}

		// Validate and sanitize input.
		if ( ! isset( $_POST['cleanup_type'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid cleanup type.', 'the-library' ) ) );
		}

		$cleanup_type = sanitize_text_field( wp_unslash( $_POST['cleanup_type'] ) );

		try {
			$database = Database::get_instance();

			switch ( $cleanup_type ) {
				case 'download_requests':
					$deleted = $database->cleanup_download_requests( 365 );
					/* translators: %d: number of deleted requests */
					wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Cleaned up %d old download requests.', 'the-library' ), $deleted ) ) );
					break;

				case 'error_logs':
					$deleted = $database->cleanup_error_logs( 30 );
					/* translators: %d: number of deleted logs */
					wp_send_json_success( array( 'message' => sprintf( esc_html__( 'Cleaned up %d error logs.', 'the-library' ), $deleted ) ) );
					break;

				case 'full':
					$results = $database->run_full_cleanup();
					$message = sprintf(
						/* translators: 1: number of download requests cleaned, 2: number of error logs cleaned, 3: number of transients cleaned, 4: number of orphaned metadata cleaned */
						esc_html__( 'Full cleanup completed. Download requests: %1$d, Error logs: %2$d, Transients: %3$d, Orphaned metadata: %4$d', 'the-library' ),
						$results['download_requests'] ?? 0,
						$results['error_logs'] ?? 0,
						$results['expired_transients'] ?? 0,
						$results['orphaned_meta'] ?? 0
					);
					wp_send_json_success( array( 'message' => $message ) );
					break;

				default:
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid cleanup type.', 'the-library' ) ) );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Handle clear cache AJAX request.
	 */
	public function clear_cache_ajax() {
		check_ajax_referer( 'wprl_clear_cache', 'nonce' );

		if ( ! Utils::current_user_can_manage() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'the-library' ) ) );
		}

		// Validate and sanitize input.
		if ( ! isset( $_POST['cache_type'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid cache type.', 'the-library' ) ) );
		}

		$cache_type = sanitize_text_field( wp_unslash( $_POST['cache_type'] ) );

		try {
			switch ( $cache_type ) {
				case 'file_types':
					Utils::clear_file_types_cache();
					wp_send_json_success( array( 'message' => esc_html__( 'File types cache cleared successfully.', 'the-library' ) ) );
					break;

				case 'all':
					Utils::clear_all_caches();
					wp_send_json_success( array( 'message' => esc_html__( 'All plugin caches cleared successfully.', 'the-library' ) ) );
					break;

				default:
					wp_send_json_error( array( 'message' => esc_html__( 'Invalid cache type.', 'the-library' ) ) );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html( $e->getMessage() ) ) );
		}
	}
}
