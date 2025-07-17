<?php
/**
 * Frontend Class
 *
 * @package WPResourceLibrary
 */

namespace WPResourceLibrary;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class for handling frontend display and functionality.
 */
class Frontend {

	/**
	 * Static instance for template access.
	 *
	 * @var ?Frontend
	 */
	private static ?Frontend $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'auto_enqueue_scripts' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_action( 'pre_get_posts', array( $this, 'modify_main_query' ) );
		add_action( 'wp_ajax_wprl_load_more_files', array( $this, 'load_more_files' ) );
		add_action( 'wp_ajax_nopriv_wprl_load_more_files', array( $this, 'load_more_files' ) );
	}

	/**
	 * Get instance for template access.
	 *
	 * @return Frontend
	 */
	public static function get_instance(): ?Frontend {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register frontend scripts and styles.
	 */
	public function register_scripts() {
		wp_register_style( 'wprl-frontend-css', Utils::get_plugin_url( 'assets/css/frontend.css' ), array(), Utils::get_version() );
		wp_register_script( 'wprl-frontend-js', Utils::get_plugin_url( 'assets/js/frontend.js' ), array( 'jquery' ), Utils::get_version(), true );

		wp_localize_script(
			'wprl-frontend-js',
			'wprl_ajax',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'wprl_frontend_nonce' ),
				'direct_download_nonce' => wp_create_nonce( 'wprl_direct_download_nonce' ),
				'loading_text'          => esc_html__( 'Loading...', 'wp-resource-library' ),
				'no_more_files'         => esc_html__( 'No more files to load.', 'wp-resource-library' ),
				'error_message'         => esc_html__( 'Error loading files. Please try again.', 'wp-resource-library' ),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'wprl-frontend-css' );
		wp_enqueue_script( 'wprl-frontend-js' );

		wp_localize_script(
			'wprl-frontend-js',
			'wprl_ajax',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'wprl_frontend_nonce' ),
				'direct_download_nonce' => wp_create_nonce( 'wprl_direct_download_nonce' ),
				'loading_text'          => esc_html__( 'Loading...', 'wp-resource-library' ),
				'no_more_files'         => esc_html__( 'No more files to load.', 'wp-resource-library' ),
				'error_message'         => esc_html__( 'Error loading files. Please try again.', 'wp-resource-library' ),
			)
		);
	}

	/**
	 * Auto enqueue frontend scripts and styles.
	 */
	public function auto_enqueue_scripts() {
		if ( ! Utils::is_frontend_script_required() ) {
			return;
		}

		self::enqueue_scripts();
	}

	/**
	 * Include custom templates.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The path of the template to include.
	 */
	public function template_include( string $template ): string {
		if ( Utils::is_files_archive() ) {
			$custom_template = Utils::get_plugin_path( 'templates/archive-files-library.php' );
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		if ( Utils::is_single_file() ) {
			$custom_template = Utils::get_plugin_path( 'templates/single-files-library.php' );
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Modify main query for files library archive.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	public function modify_main_query( WP_Query $query ) {
		if ( Utils::is_admin_request() || ! $query->is_main_query() ) {
			return;
		}

		if ( Utils::is_files_archive() ) {
			$query->set( 'posts_per_page', 12 );

			// Handle search.
			if ( ! empty( $_GET['wprl_search'] ) ) {
				$query->set( 's', sanitize_text_field( wp_unslash( $_GET['wprl_search'] ) ) );
			}

			// Handle category filter.
			if ( ! empty( $_GET['wprl_category'] ) ) {
				$query->set(
					'tax_query',
					array(
						array(
							'taxonomy' => 'file_category',
							'field'    => 'term_id',
							'terms'    => sanitize_text_field( wp_unslash( $_GET['wprl_category'] ) ),
						),
					)
				);
			}

			// Handle file type filter.
			if ( ! empty( $_GET['wprl_file_type'] ) ) {
				$query->set(
					'meta_query',
					array(
						array(
							'key'     => '_wprl_file_data',
							'value'   => '"type":"' . sanitize_text_field( wp_unslash( $_GET['wprl_file_type'] ) ) . '"',
							'compare' => 'LIKE',
						),
					)
				);
			}

			// Handle sorting.
			if ( ! empty( $_GET['wprl_sort'] ) ) {
				switch ( sanitize_text_field( wp_unslash( $_GET['wprl_sort'] ) ) ) {
					case 'date_desc':
						$query->set( 'orderby', 'date' );
						$query->set( 'order', 'DESC' );
						break;
					case 'date_asc':
						$query->set( 'orderby', 'date' );
						$query->set( 'order', 'ASC' );
						break;
					case 'title_asc':
						$query->set( 'orderby', 'title' );
						$query->set( 'order', 'ASC' );
						break;
					case 'title_desc':
						$query->set( 'orderby', 'title' );
						$query->set( 'order', 'DESC' );
						break;
					case 'downloads':
						$query->set( 'meta_key', '_wprl_download_count' );
						$query->set( 'orderby', 'meta_value_num' );
						$query->set( 'order', 'DESC' );
						break;
				}
			}
		}
	}

	/**
	 * Render file card.
	 *
	 * @param array $args Optional arguments to customize the card display.
	 */
	public function render_file_card( array $args = array() ) {
		$defaults = array(
			'heading_tag'     => 'h3',
			'show_excerpt'    => true,
			'show_date'       => false,
			'show_file_size'  => true,
			'show_categories' => true,
			'thumbnail_size'  => 'medium',
			'link_categories' => false,
		);

		$args       = wp_parse_args( $args, $defaults );
		$file_data  = Utils::get_file_data();
		$categories = get_the_terms( get_the_ID(), 'file_category' );
		$post_title = get_the_title();
		$permalink  = get_the_permalink();
		?>
		<article class="wprl-file-card">
			<?php if ( has_post_thumbnail() ) : ?>
			<div class="wprl-file-thumbnail">
				<a href="<?php echo esc_url( $permalink ); ?>"
					title="
					<?php
					echo esc_attr(
						sprintf(
						/* translators: %s: file title */
							__( 'Download %s', 'wp-resource-library' ),
							$post_title
						)
					);
					?>
					"
					aria-label="
					<?php
					echo esc_attr(
						sprintf(
						/* translators: %s: file title */
							__( 'View details for %s', 'wp-resource-library' ),
							$post_title
						)
					);
					?>
					">
					<?php the_post_thumbnail( $args['thumbnail_size'] ); ?>
				</a>
			</div>
			<?php endif; ?>

			<div class="wprl-file-content">
				<<?php echo esc_html( $args['heading_tag'] ); ?> class="wprl-file-title">
					<a href="<?php echo esc_url( $permalink ); ?>"
						title="
						<?php
						echo esc_attr(
							sprintf(
							/* translators: %s: file title */
								__( 'Download %s', 'wp-resource-library' ),
								$post_title
							)
						);
						?>
						">
						<?php echo esc_html( $post_title ); ?>
					</a>
				</<?php echo esc_html( $args['heading_tag'] ); ?>>

				<?php if ( $args['show_categories'] && $categories && ! is_wp_error( $categories ) ) : ?>
				<div class="wprl-file-categories">
					<?php foreach ( $categories as $category ) : ?>
						<?php if ( $args['link_categories'] ) : ?>
							<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"
								class="wprl-category-tag"
								title="
								<?php
								echo esc_attr(
									sprintf(
									/* translators: %s: category name. */
										__( 'View all %s files', 'wp-resource-library' ),
										$category->name
									)
								);
								?>
								">
								<?php echo esc_html( $category->name ); ?>
							</a>
						<?php else : ?>
							<span class="wprl-category-tag"><?php echo esc_html( $category->name ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php if ( $args['show_excerpt'] ) : ?>
				<div class="wprl-file-excerpt">
					<?php the_excerpt(); ?>
				</div>
				<?php endif; ?>

				<div class="wprl-file-meta">
					<?php if ( $args['show_file_size'] && $file_data['size'] ) : ?>
						<span class="wprl-file-size">
							<i class="wprl-icon-size" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'File size:', 'wp-resource-library' ); ?></span>
							<?php echo esc_html( size_format( $file_data['size'] ) ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $file_data['type'] ) : ?>
						<span class="wprl-file-type">
							<i class="wprl-icon-type" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'File type:', 'wp-resource-library' ); ?></span>
							<?php echo esc_html( $file_data['type'] ); ?>
						</span>
					<?php endif; ?>

					<span class="wprl-download-count">
						<i class="wprl-icon-download" aria-hidden="true"></i>
						<span class="screen-reader-text"><?php esc_html_e( 'Download count:', 'wp-resource-library' ); ?></span>
						<?php echo esc_html( intval( $file_data['download_count'] ) ); ?>
						<?php
						/* translators: %d: download count */
						printf( esc_html( _n( 'download', 'downloads', intval( $file_data['download_count'] ), 'wp-resource-library' ) ) );
						?>
					</span>

					<?php if ( $args['show_date'] ) : ?>
						<span class="wprl-file-date">
							<i class="wprl-icon-date" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'Published:', 'wp-resource-library' ); ?></span>
							<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
								<?php echo esc_html( get_the_date() ); ?>
							</time>
						</span>
					<?php endif; ?>
				</div>

				<div class="wprl-file-actions">
					<a href="<?php echo esc_url( $permalink ); ?>"
						class="btn button button-primary wprl-view-details"
						title="
						<?php
						echo esc_attr(
							sprintf(
							/* translators: %s: file title */
								__( 'View details and download %s', 'wp-resource-library' ),
								$post_title
							)
						);
						?>
						">
						<?php esc_html_e( 'View Details', 'wp-resource-library' ); ?>
						<span class="screen-reader-text">
						<?php
						echo esc_html(
							sprintf(
							/* translators: %s: file title */
								__( 'for %s', 'wp-resource-library' ),
								$post_title
							)
						);
						?>
						</span>
					</a>
				</div>
			</div>
		</article>
		<?php
	}

	/**
	 * Load more files via AJAX.
	 */
	public function load_more_files() {
		check_ajax_referer( 'wprl_frontend_nonce', 'nonce' );

		$page     = isset( $_POST['page'] ) ? intval( wp_unslash( $_POST['page'] ) ) : 1;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$sort     = isset( $_POST['sort'] ) ? sanitize_text_field( wp_unslash( $_POST['sort'] ) ) : 'date_desc';

		$args = array(
			'post_type'      => 'files_library',
			'posts_per_page' => 12,
			'paged'          => $page,
			'post_status'    => 'publish',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		if ( ! empty( $category ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'file_category',
					'field'    => 'term_id',
					'terms'    => $category,
				),
			);
		}

		// Handle sorting.
		switch ( $sort ) {
			case 'date_asc':
				$args['orderby'] = 'date';
				$args['order']   = 'ASC';
				break;
			case 'title_asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'title_desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			case 'downloads':
				$args['meta_key'] = '_wprl_download_count';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_file_card();
			}
			$html = ob_get_clean();
			wp_reset_postdata();

			wp_send_json_success(
				array(
					'html'     => $html,
					'has_more' => $page < $query->max_num_pages,
				)
			);
		} else {
			wp_send_json_error();
		}
	}
}
