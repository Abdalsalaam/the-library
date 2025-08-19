<?php
/**
 * Custom Post Type Class
 *
 * @package TheLibrary
 */

namespace TheLibrary;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom Post Type class for managing Files Library post type.
 */
class Custom_Post_Type {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'delete_post', array( $this, 'clear_cache_on_delete' ) );
	}

	/**
	 * Register custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => esc_html_x( 'Files Library', 'Post type general name', 'the-library' ),
			'singular_name'         => esc_html_x( 'File', 'Post type singular name', 'the-library' ),
			'menu_name'             => esc_html_x( 'Files Library', 'Admin Menu text', 'the-library' ),
			'name_admin_bar'        => esc_html_x( 'File', 'Add New on Toolbar', 'the-library' ),
			'add_new'               => esc_html__( 'Add New', 'the-library' ),
			'add_new_item'          => esc_html__( 'Add New File', 'the-library' ),
			'new_item'              => esc_html__( 'New File', 'the-library' ),
			'edit_item'             => esc_html__( 'Edit File', 'the-library' ),
			'view_item'             => esc_html__( 'View File', 'the-library' ),
			'all_items'             => esc_html__( 'All Files', 'the-library' ),
			'search_items'          => esc_html__( 'Search Files', 'the-library' ),
			'parent_item_colon'     => esc_html__( 'Parent Files:', 'the-library' ),
			'not_found'             => esc_html__( 'No files found.', 'the-library' ),
			'not_found_in_trash'    => esc_html__( 'No files found in Trash.', 'the-library' ),
			'featured_image'        => esc_html_x( 'File Featured Image', 'Overrides the "Featured Image" phrase', 'the-library' ),
			'set_featured_image'    => esc_html_x( 'Set featured image', 'Overrides the "Set featured image" phrase', 'the-library' ),
			'remove_featured_image' => esc_html_x( 'Remove featured image', 'Overrides the "Remove featured image" phrase', 'the-library' ),
			'use_featured_image'    => esc_html_x( 'Use as featured image', 'Overrides the "Use as featured image" phrase', 'the-library' ),
			'archives'              => esc_html_x( 'File archives', 'The post type archive label', 'the-library' ),
			'insert_into_item'      => esc_html_x( 'Insert into file', 'Overrides the "Insert into post" phrase', 'the-library' ),
			'uploaded_to_this_item' => esc_html_x( 'Uploaded to this file', 'Overrides the "Uploaded to this post" phrase', 'the-library' ),
			'filter_items_list'     => esc_html_x( 'Filter files list', 'Screen reader text for the filter links', 'the-library' ),
			'items_list_navigation' => esc_html_x( 'Files list navigation', 'Screen reader text for the pagination', 'the-library' ),
			'items_list'            => esc_html_x( 'Files list', 'Screen reader text for the items list', 'the-library' ),
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

		register_post_type( 'wprl_files_library', $args );
		flush_rewrite_rules();
	}

	/**
	 * Register taxonomy for categories.
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => esc_html_x( 'File Categories', 'taxonomy general name', 'the-library' ),
			'singular_name'     => esc_html_x( 'File Category', 'taxonomy singular name', 'the-library' ),
			'search_items'      => esc_html__( 'Search File Categories', 'the-library' ),
			'all_items'         => esc_html__( 'All File Categories', 'the-library' ),
			'parent_item'       => esc_html__( 'Parent File Category', 'the-library' ),
			'parent_item_colon' => esc_html__( 'Parent File Category:', 'the-library' ),
			'edit_item'         => esc_html__( 'Edit File Category', 'the-library' ),
			'update_item'       => esc_html__( 'Update File Category', 'the-library' ),
			'add_new_item'      => esc_html__( 'Add New File Category', 'the-library' ),
			'new_item_name'     => esc_html__( 'New File Category Name', 'the-library' ),
			'menu_name'         => esc_html__( 'File Categories', 'the-library' ),
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

		register_taxonomy( 'wprl_file_category', array( 'wprl_files_library' ), $args );
	}

	/**
	 * Add meta boxes.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'wprl_file_upload',
			esc_html__( 'File Upload', 'the-library' ),
			array( $this, 'file_upload_meta_box' ),
			'wprl_files_library',
			'normal',
			'high'
		);

		add_meta_box(
			'wprl_file_details',
			esc_html__( 'File Details', 'the-library' ),
			array( $this, 'file_details_meta_box' ),
			'wprl_files_library',
			'side',
			'default'
		);
	}

	/**
	 * File upload meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function file_upload_meta_box( WP_Post $post ) {
		wp_nonce_field( 'wprl_save_meta_box_data', 'wprl_meta_box_nonce' );

		$file_data = Utils::get_file_data( $post->ID );
		$file_url  = $file_data['url'];
		$file_id   = $file_data['file_id'];

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wprl_file_upload"><?php esc_html_e( 'Upload File', 'the-library' ); ?></label>
				</th>
				<td>
					<input type="hidden" id="wprl_file_id" name="wprl_file_id" value="<?php echo esc_attr( $file_id ); ?>" />
					<input type="text" id="wprl_file_url" name="wprl_file_url" value="<?php echo esc_attr( $file_url ); ?>" class="regular-text" readonly />
					<input type="button" id="wprl_upload_file_button" class="button" value="<?php esc_html_e( 'Upload File', 'the-library' ); ?>" />
					<input type="button" id="wprl_remove_file_button" class="button" value="<?php esc_html_e( 'Remove File', 'the-library' ); ?>" <?php echo empty( $file_url ) ? 'style="display:none;"' : ''; ?> />
					<p class="description"><?php esc_html_e( 'Upload the file that users will be able to download.', 'the-library' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * File details meta box.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function file_details_meta_box( WP_Post $post ) {
		$file_data = Utils::get_file_data( $post->ID );

		?>
		<p><strong><?php esc_html_e( 'File Size:', 'the-library' ); ?></strong> <?php echo $file_data['size'] ? esc_html( size_format( $file_data['size'] ) ) : esc_html__( 'N/A', 'the-library' ); ?></p>
		<p><strong><?php esc_html_e( 'File Type:', 'the-library' ); ?></strong> <?php echo $file_data['type'] ? esc_html( $file_data['type'] ) : esc_html__( 'N/A', 'the-library' ); ?></p>
		<p><strong><?php esc_html_e( 'Download Count:', 'the-library' ); ?></strong> <?php echo intval( $file_data['download_count'] ); ?></p>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_meta_boxes( int $post_id ) {
		if ( ! isset( $_POST['wprl_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wprl_meta_box_nonce'] ) ), 'wprl_save_meta_box_data' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || 'wprl_files_library' !== $_POST['post_type'] || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save file data using consolidated approach.
		$file_data = Utils::get_file_data( $post_id );

		if ( isset( $_POST['wprl_file_url'] ) ) {
			$file_data['url'] = sanitize_url( wp_unslash( $_POST['wprl_file_url'] ) );
		}

		if ( isset( $_POST['wprl_file_id'] ) ) {
			$file_id              = intval( $_POST['wprl_file_id'] );
			$file_data['file_id'] = $file_id;

			// Get file details.
			$file_path = get_attached_file( $file_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_data['size'] = filesize( $file_path );
				$file_type_info    = wp_check_filetype( $file_path );
				$file_data['type'] = Utils::get_simplified_file_type( $file_type_info['type'] );
			}
		}

		// Update consolidated file data.
		Utils::update_file_data( $post_id, $file_data );

		// Clear file types cache when file data changes.
		Utils::clear_file_types_cache();
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ) {
		global $post;

		if ( ( 'post.php' !== $hook && 'post-new.php' !== $hook ) || 'wprl_files_library' !== $post->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'wprl-admin-js', Utils::get_plugin_url( 'assets/js/admin.js' ), array( 'jquery' ), Utils::get_version(), true );
		wp_enqueue_style( 'wprl-admin-css', Utils::get_plugin_url( 'assets/css/admin.css' ), array(), Utils::get_version() );
	}

	/**
	 * Clear cache when wprl_files_library post is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function clear_cache_on_delete( int $post_id ) {
		if ( get_post_type( $post_id ) === 'wprl_files_library' ) {
			Utils::clear_file_types_cache();
		}
	}
}
