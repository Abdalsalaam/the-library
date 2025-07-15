<?php
/**
 * Custom Post Type Class
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRL_Custom_Post_Type {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => esc_html_x( 'Files Library', 'Post type general name', 'wp-resource-library' ),
			'singular_name'         => esc_html_x( 'File', 'Post type singular name', 'wp-resource-library' ),
			'menu_name'             => esc_html_x( 'Files Library', 'Admin Menu text', 'wp-resource-library' ),
			'name_admin_bar'        => esc_html_x( 'File', 'Add New on Toolbar', 'wp-resource-library' ),
			'add_new'               => esc_html__( 'Add New', 'wp-resource-library' ),
			'add_new_item'          => esc_html__( 'Add New File', 'wp-resource-library' ),
			'new_item'              => esc_html__( 'New File', 'wp-resource-library' ),
			'edit_item'             => esc_html__( 'Edit File', 'wp-resource-library' ),
			'view_item'             => esc_html__( 'View File', 'wp-resource-library' ),
			'all_items'             => esc_html__( 'All Files', 'wp-resource-library' ),
			'search_items'          => esc_html__( 'Search Files', 'wp-resource-library' ),
			'parent_item_colon'     => esc_html__( 'Parent Files:', 'wp-resource-library' ),
			'not_found'             => esc_html__( 'No files found.', 'wp-resource-library' ),
			'not_found_in_trash'    => esc_html__( 'No files found in Trash.', 'wp-resource-library' ),
			'featured_image'        => esc_html_x( 'File Featured Image', 'Overrides the "Featured Image" phrase', 'wp-resource-library' ),
			'set_featured_image'    => esc_html_x( 'Set featured image', 'Overrides the "Set featured image" phrase', 'wp-resource-library' ),
			'remove_featured_image' => esc_html_x( 'Remove featured image', 'Overrides the "Remove featured image" phrase', 'wp-resource-library' ),
			'use_featured_image'    => esc_html_x( 'Use as featured image', 'Overrides the "Use as featured image" phrase', 'wp-resource-library' ),
			'archives'              => esc_html_x( 'File archives', 'The post type archive label', 'wp-resource-library' ),
			'insert_into_item'      => esc_html_x( 'Insert into file', 'Overrides the "Insert into post" phrase', 'wp-resource-library' ),
			'uploaded_to_this_item' => esc_html_x( 'Uploaded to this file', 'Overrides the "Uploaded to this post" phrase', 'wp-resource-library' ),
			'filter_items_list'     => esc_html_x( 'Filter files list', 'Screen reader text for the filter links', 'wp-resource-library' ),
			'items_list_navigation' => esc_html_x( 'Files list navigation', 'Screen reader text for the pagination', 'wp-resource-library' ),
			'items_list'            => esc_html_x( 'Files list', 'Screen reader text for the items list', 'wp-resource-library' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'files-library' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'files_library', $args );
	}

	/**
	 * Register taxonomy for categories.
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => esc_html_x( 'File Categories', 'taxonomy general name', 'wp-resource-library' ),
			'singular_name'     => esc_html_x( 'File Category', 'taxonomy singular name', 'wp-resource-library' ),
			'search_items'      => esc_html__( 'Search File Categories', 'wp-resource-library' ),
			'all_items'         => esc_html__( 'All File Categories', 'wp-resource-library' ),
			'parent_item'       => esc_html__( 'Parent File Category', 'wp-resource-library' ),
			'parent_item_colon' => esc_html__( 'Parent File Category:', 'wp-resource-library' ),
			'edit_item'         => esc_html__( 'Edit File Category', 'wp-resource-library' ),
			'update_item'       => esc_html__( 'Update File Category', 'wp-resource-library' ),
			'add_new_item'      => esc_html__( 'Add New File Category', 'wp-resource-library' ),
			'new_item_name'     => esc_html__( 'New File Category Name', 'wp-resource-library' ),
			'menu_name'         => esc_html__( 'File Categories', 'wp-resource-library' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'file-category' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'file_category', array( 'files_library' ), $args );
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wprl_file_upload',
			esc_html__( 'File Upload', 'wp-resource-library' ),
			array( $this, 'file_upload_meta_box' ),
			'files_library',
			'normal',
			'high'
		);

		add_meta_box(
			'wprl_file_details',
			esc_html__( 'File Details', 'wp-resource-library' ),
			array( $this, 'file_details_meta_box' ),
			'files_library',
			'side',
			'default'
		);
	}

	/**
	 * File upload meta box.
	 */
	public function file_upload_meta_box( $post ) {
		wp_nonce_field( 'wprl_save_meta_box_data', 'wprl_meta_box_nonce' );

		$file_url = get_post_meta( $post->ID, '_wprl_file_url', true );
		$file_id  = get_post_meta( $post->ID, '_wprl_file_id', true );

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wprl_file_upload"><?php esc_html_e( 'Upload File', 'wp-resource-library' ); ?></label>
				</th>
				<td>
					<input type="hidden" id="wprl_file_id" name="wprl_file_id" value="<?php echo esc_attr( $file_id ); ?>" />
					<input type="text" id="wprl_file_url" name="wprl_file_url" value="<?php echo esc_attr( $file_url ); ?>" class="regular-text" readonly />
					<input type="button" id="wprl_upload_file_button" class="button" value="<?php esc_html_e( 'Upload File', 'wp-resource-library' ); ?>" />
					<input type="button" id="wprl_remove_file_button" class="button" value="<?php esc_html_e( 'Remove File', 'wp-resource-library' ); ?>" <?php echo empty( $file_url ) ? 'style="display:none;"' : ''; ?> />
					<p class="description"><?php esc_html_e( 'Upload the file that users will be able to download.', 'wp-resource-library' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * File details meta box.
	 */
	public function file_details_meta_box( $post ) {
		$file_size      = get_post_meta( $post->ID, '_wprl_file_size', true );
		$file_type      = get_post_meta( $post->ID, '_wprl_file_type', true );
		$download_count = get_post_meta( $post->ID, '_wprl_download_count', true );

		?>
		<p><strong><?php esc_html_e( 'File Size:', 'wp-resource-library' ); ?></strong> <?php echo $file_size ? esc_html__( size_format( $file_size ) ) : esc_html__( 'N/A', 'wp-resource-library' ); ?></p>
		<p><strong><?php esc_html_e( 'File Type:', 'wp-resource-library' ); ?></strong> <?php echo $file_type ? esc_html( $file_type ) : esc_html__( 'N/A', 'wp-resource-library' ); ?></p>
		<p><strong><?php esc_html_e( 'Download Count:', 'wp-resource-library' ); ?></strong> <?php echo intval( $download_count ); ?></p>
		<?php
	}

	/**
	 * Save meta box data.
	 */
	public function save_meta_boxes( $post_id ) {
		if ( ! isset( $_POST['wprl_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( wp_unslash( $_POST['wprl_meta_box_nonce'] ), 'wprl_save_meta_box_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || 'files_library' !== $_POST['post_type'] || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save file data.
		if ( isset( $_POST['wprl_file_url'] ) ) {
			update_post_meta( $post_id, '_wprl_file_url', sanitize_url( wp_unslash( $_POST['wprl_file_url'] ) ) );
		}

		if ( isset( $_POST['wprl_file_id'] ) ) {
			$file_id = intval( $_POST['wprl_file_id'] );
			update_post_meta( $post_id, '_wprl_file_id', $file_id );

			// Get file details.
			$file_path = get_attached_file( $file_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				$file_type = wp_check_filetype( $file_path );

				update_post_meta( $post_id, '_wprl_file_size', $file_size );
				update_post_meta( $post_id, '_wprl_file_type', $file_type['type'] );
			}
		}
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $post;

		if ( ( $hook !== 'post.php' && $hook !== 'post-new.php' ) || 'files_library' !== $post->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'wprl-admin-js', WPRL_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WPRL_VERSION, true );
		wp_enqueue_style( 'wprl-admin-css', WPRL_PLUGIN_URL . 'assets/css/admin.css', array(), WPRL_VERSION );
	}
}
