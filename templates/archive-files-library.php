<?php
/**
 * Archive template for Files Library.
 *
 * @package WPResourceLibrary
 */

use WPResourceLibrary\Utils;

get_header(); ?>

<div class="wprl-archive-wrapper">
	<div class="container">
		<header class="wprl-archive-header">
			<h1 class="wprl-archive-title">
				<?php
				if ( is_tax( 'wprl_file_category' ) ) {
					single_term_title( esc_html__( 'Files in Category: ', 'wp-resource-library' ) );
				} else {
					esc_html_e( 'Files Library', 'wp-resource-library' );
				}
				?>
			</h1>

			<?php if ( is_tax( 'wprl_file_category' ) && term_description() ) : ?>
				<div class="wprl-archive-description">
					<?php echo wp_kses_post( term_description() ); ?>
				</div>
			<?php endif; ?>
		</header>

		<?php
		if ( ! Utils::is_file_category() ) {
			?>
			<!-- Filters and Search -->
			<div class="wprl-filters-wrapper">
				<form method="get" class="wprl-filters-form">
					<div class="wprl-filters-row">
						<!-- Search -->
						<div class="wprl-search-field">
							<?php
							$search_value = isset( $_GET['wprl_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_search'] ) ) : '';
							?>
							<input type="text"
									name="wprl_search"
									value="<?php echo esc_attr( $search_value ); ?>"
									placeholder="<?php esc_html_e( 'Search files...', 'wp-resource-library' ); ?>"
									class="wprl-search-input">
						</div>

						<!-- Category Filter -->
						<div class="wprl-filter-field">
							<select name="wprl_category" class="wprl-category-filter">
								<option value=""><?php esc_html_e( 'All Categories', 'wp-resource-library' ); ?></option>
								<?php
								$categories        = get_terms(
									array(
										'taxonomy'   => 'wprl_file_category',
										'hide_empty' => true,
									)
								);
								$selected_category = isset( $_GET['wprl_category'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_category'] ) ) : '';

								foreach ( $categories as $category ) {
									$selected = selected( $selected_category, $category->term_id, false );
									echo '<option value="' . esc_attr( $category->term_id ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $category->name ) . ' (' . esc_html( $category->count ) . ')</option>';
								}
								?>
							</select>
						</div>

						<!-- File Type Filter -->
						<div class="wprl-filter-field">
							<select name="wprl_file_type" class="wprl-file-type-filter">
								<option value=""><?php esc_html_e( 'All File Types', 'wp-resource-library' ); ?></option>
								<?php
								// Get cached file types for better performance.
								$file_types         = WPResourceLibrary\Utils::get_cached_file_types();
								$selected_file_type = isset( $_GET['wprl_file_type'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_file_type'] ) ) : '';

								foreach ( $file_types as $type_name => $count ) {
									$selected = selected( $selected_file_type, $type_name, false );
									echo '<option value="' . esc_attr( $type_name ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $type_name ) . ' (' . esc_html( $count ) . ')</option>';
								}
								?>
							</select>
						</div>

						<!-- Sort Filter -->
						<div class="wprl-filter-field">
							<select name="wprl_sort" class="wprl-sort-filter">
								<?php
								$sort_options = array(
									'date_desc'  => esc_html__( 'Newest First', 'wp-resource-library' ),
									'date_asc'   => esc_html__( 'Oldest First', 'wp-resource-library' ),
									'title_asc'  => esc_html__( 'Title A-Z', 'wp-resource-library' ),
									'title_desc' => esc_html__( 'Title Z-A', 'wp-resource-library' ),
									'downloads'  => esc_html__( 'Most Downloaded', 'wp-resource-library' ),
								);

								$selected_sort = isset( $_GET['wprl_sort'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_sort'] ) ) : 'date_desc';

								foreach ( $sort_options as $value => $label ) {
									$selected = selected( $selected_sort, $value, false );
									echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
								}
								?>
							</select>
						</div>

						<!-- Submit Button -->
						<div class="wprl-filter-field">
							<button type="submit" class="wprl-filter-submit">
								<?php esc_html_e( 'Filter', 'wp-resource-library' ); ?>
							</button>
						</div>

						<!-- Clear Filters -->
						<?php
						$has_filters = ( ! empty( $_GET['wprl_search'] ) || ! empty( $_GET['wprl_category'] ) || ! empty( $_GET['wprl_file_type'] ) || ! empty( $_GET['wprl_sort'] ) );
						if ( $has_filters ) :
							?>
							<div class="wprl-filter-field">
								<a href="<?php echo esc_url( get_post_type_archive_link( 'wprl_files_library' ) ); ?>" class="wprl-clear-filters">
									<?php esc_html_e( 'Clear Filters', 'wp-resource-library' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				</form>
			</div>
			<?php
		}
		?>

		<!-- Results Info -->
		<div class="wprl-results-info">
			<?php
			global $wp_query;
			$total_files    = $wp_query->found_posts;
			$current_page   = max( 1, get_query_var( 'paged' ) );
			$posts_per_page = get_query_var( 'posts_per_page' );
			$start          = ( ( $current_page - 1 ) * $posts_per_page ) + 1;
			$end            = min( $current_page * $posts_per_page, $total_files );

			if ( $total_files > 0 ) {
				printf(
					/* translators: 1: start number, 2: end number, 3: total number */
					esc_html__( 'Showing %1$d-%2$d of %3$d files', 'wp-resource-library' ),
					esc_html( number_format_i18n( $start ) ),
					esc_html( number_format_i18n( $end ) ),
					esc_html( number_format_i18n( $total_files ) )
				);
			}
			?>
		</div>

		<!-- Files Grid -->
		<?php if ( have_posts() ) : ?>
		<div class="wprl-files-grid">
			<?php
			$frontend_instance = WPResourceLibrary\Frontend::get_instance();
			while ( have_posts() ) :
				the_post();

				if ( $frontend_instance ) {
					$frontend_instance->render_file_card(
						array(
							'heading_tag'     => 'h2',
							'show_date'       => true,
							'link_categories' => true,
							'show_excerpt'    => true,
							'show_file_size'  => false, // Hide file size in archive for cleaner look.
						)
					);
				}
			endwhile;
			?>
		</div>

		<!-- Pagination -->
		<div class="wprl-pagination">
			<?php
			echo wp_kses_post(
				paginate_links(
					apply_filters(
						'wprl_pagination_args',
						array(
							'prev_text' => esc_html__( '&laquo; Previous', 'wp-resource-library' ),
							'next_text' => esc_html__( 'Next &raquo;', 'wp-resource-library' ),
							'type'      => 'list',
						)
					)
				)
			);
			?>
		</div>

		<?php else : ?>

		<div class="wprl-no-files">
			<h2><?php esc_html_e( 'No files found', 'wp-resource-library' ); ?></h2>
			<p><?php esc_html_e( 'Try adjusting your search criteria or browse all files.', 'wp-resource-library' ); ?></p>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'wprl_files_library' ) ); ?>" class="wprl-browse-all">
				<?php esc_html_e( 'Browse All Files', 'wp-resource-library' ); ?>
			</a>
		</div>

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
