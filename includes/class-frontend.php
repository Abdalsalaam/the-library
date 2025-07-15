<?php
/**
 * Frontend Class
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRL_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'template_include', array( $this, 'template_include' ) );
		add_action( 'pre_get_posts', array( $this, 'modify_main_query' ) );
		add_shortcode( 'files_library', array( $this, 'files_library_shortcode' ) );
		add_action( 'wp_ajax_wprl_load_more_files', array( $this, 'load_more_files' ) );
		add_action( 'wp_ajax_nopriv_wprl_load_more_files', array( $this, 'load_more_files' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		if ( is_post_type_archive( 'files_library' ) || is_singular( 'files_library' ) || is_tax( 'file_category' ) ) {
			wp_enqueue_style( 'wprl-frontend-css', WPRL_PLUGIN_URL . 'assets/css/frontend.css', array(), WPRL_VERSION );
			wp_enqueue_script( 'wprl-frontend-js', WPRL_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WPRL_VERSION, true );

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
	}

	/**
	 * Include custom templates.
	 */
	public function template_include( $template ) {
		if ( is_post_type_archive( 'files_library' ) ) {
			$custom_template = WPRL_PLUGIN_PATH . 'templates/archive-files-library.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		if ( is_singular( 'files_library' ) ) {
			$custom_template = WPRL_PLUGIN_PATH . 'templates/single-files-library.php';
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}

		return $template;
	}

	/**
	 * Modify main query for files library archive.
	 */
	public function modify_main_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( is_post_type_archive( 'files_library' ) ) {
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
							'key'     => '_wprl_file_type',
							'value'   => sanitize_text_field( wp_unslash( $_GET['wprl_file_type'] ) ),
							'compare' => 'LIKE',
						),
					)
				);
			}

			// Handle sorting.
			if ( ! empty( $_GET['wprl_sort'] ) ) {
				switch ( wp_unslash( $_GET['wprl_sort'] ) ) {
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
	 * Files library shortcode.
	 */
	public function files_library_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'posts_per_page' => 12,
				'category'       => '',
				'show_filters'   => 'true',
				'show_search'    => 'true',
				'columns'        => 3,
			),
			$atts,
			'files_library'
		);

		$args = array(
			'post_type'      => 'files_library',
			'posts_per_page' => intval( $atts['posts_per_page'] ),
			'post_status'    => 'publish',
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'file_category',
					'field'    => 'term_id',
					'terms'    => explode( ',', $atts['category'] ),
				),
			);
		}

		$query = new WP_Query( $args );

		ob_start();

		if ( $query->have_posts() ) {
			?>
			<div class="wprl-files-library-shortcode">
				<?php if ( $atts['show_search'] === 'true' || $atts['show_filters'] === 'true' ) : ?>
				<div class="wprl-filters-wrapper">
					<?php if ( $atts['show_search'] === 'true' ) : ?>
					<div class="wprl-search-form">
						<input type="text" id="wprl-search-input" placeholder="<?php esc_html_e( 'Search files...', 'wp-resource-library' ); ?>">
						<button type="button" id="wprl-search-button"><?php esc_html_e( 'Search', 'wp-resource-library' ); ?></button>
					</div>
					<?php endif; ?>
					
					<?php if ( $atts['show_filters'] === 'true' ) : ?>
					<div class="wprl-filters">
						<select id="wprl-category-filter">
							<option value=""><?php esc_html_e( 'All Categories', 'wp-resource-library' ); ?></option>
							<?php
							$categories = get_terms(
								array(
									'taxonomy'   => 'file_category',
									'hide_empty' => true,
								)
							);
							foreach ( $categories as $category ) {
								echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
							}
							?>
						</select>
						
						<select id="wprl-sort-filter">
							<option value="date_desc"><?php esc_html_e( 'Newest First', 'wp-resource-library' ); ?></option>
							<option value="date_asc"><?php esc_html_e( 'Oldest First', 'wp-resource-library' ); ?></option>
							<option value="title_asc"><?php esc_html_e( 'Title A-Z', 'wp-resource-library' ); ?></option>
							<option value="title_desc"><?php esc_html_e( 'Title Z-A', 'wp-resource-library' ); ?></option>
							<option value="downloads"><?php esc_html_e( 'Most Downloaded', 'wp-resource-library' ); ?></option>
						</select>
					</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="wprl-files-grid" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						?>
						<?php $this->render_file_card(); ?>
					<?php endwhile; ?>
				</div>

				<?php if ( $query->max_num_pages > 1 ) : ?>
				<div class="wprl-load-more-wrapper">
					<button type="button" id="wprl-load-more" data-page="1" data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>">
						<?php esc_html_e( 'Load More', 'wp-resource-library' ); ?>
					</button>
				</div>
				<?php endif; ?>
			</div>
			<?php
		} else {
			echo '<p>' . esc_html__( 'No files found.', 'wp-resource-library' ) . '</p>';
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Render file card.
	 */
	public function render_file_card() {
		$file_url       = get_post_meta( get_the_ID(), '_wprl_file_url', true );
		$file_size      = get_post_meta( get_the_ID(), '_wprl_file_size', true );
		$file_type      = get_post_meta( get_the_ID(), '_wprl_file_type', true );
		$download_count = get_post_meta( get_the_ID(), '_wprl_download_count', true );
		$categories     = get_the_terms( get_the_ID(), 'file_category' );
		?>
		<div class="wprl-file-card">
			<?php if ( has_post_thumbnail() ) : ?>
			<div class="wprl-file-thumbnail">
				<a href="<?php the_permalink(); ?>">
					<?php the_post_thumbnail( 'medium' ); ?>
				</a>
			</div>
			<?php endif; ?>

			<div class="wprl-file-content">
				<h3>
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
				<div class="wprl-file-categories">
					<?php foreach ( $categories as $category ) : ?>
						<span class="wprl-category-tag"><?php echo esc_html( $category->name ); ?></span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<div class="wprl-file-excerpt">
					<?php the_excerpt(); ?>
				</div>

				<div class="wprl-file-meta">
					<?php if ( $file_size ) : ?>
						<span class="wprl-file-size"><?php echo size_format( $file_size ); ?></span>
					<?php endif; ?>

					<?php if ( $file_type ) : ?>
						<span class="wprl-file-type"><?php echo esc_html( strtoupper( pathinfo( $file_url, PATHINFO_EXTENSION ) ) ); ?></span>
					<?php endif; ?>

					<span class="wprl-download-count"><?php printf( esc_html__( '%d downloads', 'wp-resource-library' ), intval( $download_count ) ); ?></span>
				</div>

				<div class="wprl-file-actions">
					<a href="<?php the_permalink(); ?>" class="btn button button-primary">
						<?php esc_html_e( 'View Details', 'wp-resource-library' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Load more files via AJAX.
	 */
	public function load_more_files() {
		check_ajax_referer( 'wprl_frontend_nonce', 'nonce' );

		$page     = intval( wp_unslash( $_POST['page'] ) );
		$search   = sanitize_text_field( wp_unslash( $_POST['search'] ) );
		$category = sanitize_text_field( wp_unslash( $_POST['category'] ) );
		$sort     = sanitize_text_field( wp_unslash( $_POST['sort'] ) );

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
