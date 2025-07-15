<?php
/**
 * Admin Class
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRL_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_wprl_delete_download_request', array( $this, 'delete_download_request' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=files_library',
			esc_html__( 'Download Requests', 'wp-resource-library' ),
			esc_html__( 'Download Requests', 'wp-resource-library' ),
			'manage_options',
			'wprl-download-requests',
			array( $this, 'download_requests_page' )
		);
	}

	/**
	 * Download requests admin page.
	 */
	public function download_requests_page() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wprl_download_requests';

		// Handle bulk actions.
		if ( isset( $_POST['action'] ) && 'bulk_delete' === $_POST['action'] && isset( $_POST['request_ids'] ) ) {
			$ids = array_map( 'intval', $_POST['request_ids'] );
			if ( ! empty( $ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($placeholders)", $ids ) );
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected requests deleted successfully.', 'wp-resource-library' ) . '</p></div>';
			}
		}

		// Pagination.
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		// Search functionality.
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$where_clause  = '';
		$search_params = array();

		if ( ! empty( $search ) ) {
			$where_clause  = ' WHERE (user_name LIKE %s OR user_email LIKE %s OR user_mobile LIKE %s)';
			$search_term   = '%' . $wpdb->esc_like( $search ) . '%';
			$search_params = array( $search_term, $search_term, $search_term );
		}

		// Get total count.
		$total_query = "SELECT COUNT(*) FROM $table_name" . $where_clause;
		if ( ! empty( $search_params ) ) {
			$total_items = $wpdb->get_var( $wpdb->prepare( $total_query, $search_params ) );
		} else {
			$total_items = $wpdb->get_var( $total_query );
		}

		// Get requests.
		$query = "SELECT dr.*, p.post_title 
                  FROM $table_name dr 
                  LEFT JOIN {$wpdb->posts} p ON dr.post_id = p.ID"
					. $where_clause .
					' ORDER BY dr.download_date DESC 
                  LIMIT %d OFFSET %d';

		$query_params = array_merge( $search_params, array( $per_page, $offset ) );
		$requests     = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

		// Calculate pagination.
		$total_pages = ceil( $total_items / $per_page );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Download Requests', 'wp-resource-library' ); ?></h1>

			<!-- Export button -->
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'   => 'wprl-download-requests',
						'action' => 'export_csv',
					)
				)
			);
			?>
						" class="page-title-action">
				<?php esc_html_e( 'Export CSV', 'wp-resource-library' ); ?>
			</a>

			<hr class="wp-header-end">

			<!-- Search form -->
			<form method="get" class="search-form">
				<input type="hidden" name="post_type" value="files_library">
				<input type="hidden" name="page" value="wprl-download-requests">
				<p class="search-box">
					<label class="screen-reader-text" for="request-search-input"><?php esc_html_e( 'Search requests:', 'wp-resource-library' ); ?></label>
					<input type="search" id="request-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_html_e( 'Search by name, email, or mobile...', 'wp-resource-library' ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_html_e( 'Search', 'wp-resource-library' ); ?>">
				</p>
			</form>

			<!-- Requests table -->
			<form method="post">
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="action">
							<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp-resource-library' ); ?></option>
							<option value="bulk_delete"><?php esc_html_e( 'Delete', 'wp-resource-library' ); ?></option>
						</select>
						<input type="submit" class="button action" value="<?php esc_html_e( 'Apply', 'wp-resource-library' ); ?>">
					</div>

					<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( _n( '%s item', '%s items', $total_items, 'wp-resource-library' ), number_format_i18n( $total_items ) ); ?></span>
						<?php
						$page_links = paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => esc_html__( '&laquo;' ),
								'next_text' => esc_html__( '&raquo;' ),
								'total'     => $total_pages,
								'current'   => $current_page,
							)
						);
						if ( $page_links ) {
							echo '<span class="pagination-links">' . $page_links . '</span>';
						}
						?>
					</div>
					<?php endif; ?>
				</div>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<input type="checkbox" id="cb-select-all-1">
							</td>
							<th scope="col" class="manage-column"><?php esc_html_e( 'File', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Name', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Email', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Mobile', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Download Date', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'IP Address', 'wp-resource-library' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'Actions', 'wp-resource-library' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $requests ) ) : ?>
						<tr>
							<td colspan="8" class="no-items"><?php esc_html_e( 'No download requests found.', 'wp-resource-library' ); ?></td>
						</tr>
						<?php else : ?>
							<?php foreach ( $requests as $request ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" name="request_ids[]" value="<?php echo esc_attr( $request->id ); ?>">
							</th>
							<td>
								<?php if ( $request->post_title ) : ?>
									<a href="<?php echo get_edit_post_link( $request->post_id ); ?>" target="_blank">
										<?php echo esc_html( $request->post_title ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'File not found', 'wp-resource-library' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $request->user_name ); ?></td>
							<td>
								<?php if ( ! empty( $request->user_email ) ) : ?>
									<a href="mailto:<?php echo esc_attr( $request->user_email ); ?>">
										<?php echo esc_html( $request->user_email ); ?>
									</a>
								<?php else : ?>
									<em><?php esc_html_e( 'Not provided', 'wp-resource-library' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $request->user_mobile ); ?></td>
							<td><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $request->download_date ) ); ?></td>
							<td><?php echo esc_html( $request->ip_address ); ?></td>
							<td>
								<button type="button" class="button button-small wprl-delete-request" data-id="<?php echo esc_attr( $request->id ); ?>">
									<?php esc_html_e( 'Delete', 'wp-resource-library' ); ?>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( _n( '%s item', '%s items', $total_items, 'wp-resource-library' ), number_format_i18n( $total_items ) ); ?></span>
						<?php
						if ( $page_links ) {
							echo '<span class="pagination-links">' . $page_links . '</span>';
						}
						?>
					</div>
					<?php endif; ?>
				</div>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			// Handle select all checkbox
			$('#cb-select-all-1').on('change', function() {
				$('input[name="request_ids[]"]').prop('checked', this.checked);
			});
			
			// Handle individual delete buttons
			$('.wprl-delete-request').on('click', function() {
				if (confirm('<?php esc_html_e( 'Are you sure you want to delete this request?', 'wp-resource-library' ); ?>')) {
					var requestId = $(this).data('id');
					var row = $(this).closest('tr');

					$.post(ajaxurl, {
						action: 'wprl_delete_download_request',
						request_id: requestId,
						nonce: '<?php echo wp_create_nonce( 'wprl_delete_request' ); ?>'
					}, function(response) {
						if (response.success) {
							row.fadeOut();
						} else {
							alert('<?php esc_html_e( 'Error deleting request.', 'wp-resource-library' ); ?>');
						}
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Delete download request via AJAX
	 */
	public function delete_download_request() {
		check_ajax_referer( 'wprl_delete_request', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$request_id = intval( $_POST['request_id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'wprl_download_requests';

		$result = $wpdb->delete( $table_name, array( 'id' => $request_id ), array( '%d' ) );

		if ( $result !== false ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'wprl-download-requests' ) !== false ) {
			wp_enqueue_style( 'wprl-admin-css', WPRL_PLUGIN_URL . 'assets/css/admin.css', array(), WPRL_VERSION );
		}
	}
}
