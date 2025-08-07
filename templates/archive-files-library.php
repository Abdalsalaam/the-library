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
					single_term_title( esc_html__( 'Files in Category: ', 'the-library' ) );
				} else {
					esc_html_e( 'Files Library', 'the-library' );
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
			<!-- Modern Filters and Search -->
			<div class="wprl-filters-wrapper">
				<form method="get" class="wprl-filters-form">
					<div class="wprl-filters-container">
						<div class="wprl-filters-section">
							<div class="wprl-filters-row">
								<!-- Search Field -->
								<div class="wprl-search-field">
									<label for="wprl-search-input" class="wprl-search-label">
										<svg class="wprl-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<circle cx="11" cy="11" r="8"></circle>
											<path d="m21 21-4.35-4.35"></path>
										</svg>
									</label>
									<?php
									$search_value = isset( $_GET['wprl_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_search'] ) ) : '';
									?>
									<input type="text"
											id="wprl-search-input"
											name="wprl_search"
											value="<?php echo esc_attr( $search_value ); ?>"
											placeholder="<?php esc_html_e( 'Search files...', 'the-library' ); ?>"
											class="wprl-search-input"
											aria-label="<?php esc_attr_e( 'Search files', 'the-library' ); ?>">
									<?php if ( ! empty( $search_value ) ) : ?>
										<button type="button" class="wprl-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'the-library' ); ?>">
											<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<line x1="18" y1="6" x2="6" y2="18"></line>
												<line x1="6" y1="6" x2="18" y2="18"></line>
											</svg>
										</button>
									<?php endif; ?>
								</div>

								<!-- Category Filter -->
								<div class="wprl-filter-field">
									<label for="wprl-category-filter" class="wprl-filter-label">
										<?php esc_html_e( 'Category', 'the-library' ); ?>
									</label>
									<div class="wprl-select-wrapper">
										<select id="wprl-category-filter" name="wprl_category" class="wprl-category-filter" aria-label="<?php esc_attr_e( 'Filter by category', 'the-library' ); ?>">
											<option value=""><?php esc_html_e( 'All Categories', 'the-library' ); ?></option>
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
										<svg class="wprl-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<polyline points="6,9 12,15 18,9"></polyline>
										</svg>
									</div>
								</div>

								<!-- File Type Filter -->
								<div class="wprl-filter-field">
									<label for="wprl-file-type-filter" class="wprl-filter-label">
										<?php esc_html_e( 'File Type', 'the-library' ); ?>
									</label>
									<div class="wprl-select-wrapper">
										<select id="wprl-file-type-filter" name="wprl_file_type" class="wprl-file-type-filter" aria-label="<?php esc_attr_e( 'Filter by file type', 'the-library' ); ?>">
											<option value=""><?php esc_html_e( 'All File Types', 'the-library' ); ?></option>
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
										<svg class="wprl-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<polyline points="6,9 12,15 18,9"></polyline>
										</svg>
									</div>
								</div>

								<!-- Sort Filter -->
								<div class="wprl-filter-field">
									<label for="wprl-sort-filter" class="wprl-filter-label">
										<?php esc_html_e( 'Sort By', 'the-library' ); ?>
									</label>
									<div class="wprl-select-wrapper">
										<select id="wprl-sort-filter" name="wprl_sort" class="wprl-sort-filter" aria-label="<?php esc_attr_e( 'Sort files', 'the-library' ); ?>">
											<?php
											$sort_options = array(
												'date_desc'  => esc_html__( 'Newest First', 'the-library' ),
												'date_asc'   => esc_html__( 'Oldest First', 'the-library' ),
												'title_asc'  => esc_html__( 'Title A-Z', 'the-library' ),
												'title_desc' => esc_html__( 'Title Z-A', 'the-library' ),
												'downloads'  => esc_html__( 'Most Downloaded', 'the-library' ),
											);

											$selected_sort = isset( $_GET['wprl_sort'] ) ? sanitize_text_field( wp_unslash( $_GET['wprl_sort'] ) ) : 'date_desc';

											foreach ( $sort_options as $value => $label ) {
												$selected = selected( $selected_sort, $value, false );
												echo '<option value="' . esc_attr( $value ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
											}
											?>
										</select>
										<svg class="wprl-select-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<polyline points="6,9 12,15 18,9"></polyline>
										</svg>
									</div>
								</div>

								<!-- Action Buttons -->
								<div class="wprl-filter-actions">
									<button type="submit" class="wprl-filter-submit">
										<svg class="wprl-button-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"></polygon>
										</svg>
										<?php esc_html_e( 'Apply Filters', 'the-library' ); ?>
									</button>

									<!-- Clear Filters -->
									<?php
									$has_filters = ( ! empty( $_GET['wprl_search'] ) || ! empty( $_GET['wprl_category'] ) || ! empty( $_GET['wprl_file_type'] ) || ! empty( $_GET['wprl_sort'] ) );
									if ( $has_filters ) :
										?>
										<a href="<?php echo esc_url( get_post_type_archive_link( 'wprl_files_library' ) ); ?>" class="wprl-clear-filters">
											<svg class="wprl-button-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<line x1="18" y1="6" x2="6" y2="18"></line>
												<line x1="6" y1="6" x2="18" y2="18"></line>
											</svg>
											<?php esc_html_e( 'Clear All', 'the-library' ); ?>
										</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
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
					esc_html__( 'Showing %1$d-%2$d of %3$d files', 'the-library' ),
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
							'prev_text' => esc_html__( '&laquo; Previous', 'the-library' ),
							'next_text' => esc_html__( 'Next &raquo;', 'the-library' ),
							'type'      => 'list',
						)
					)
				)
			);
			?>
		</div>

		<?php else : ?>

		<div class="wprl-no-files">
			<h2><?php esc_html_e( 'No files found', 'the-library' ); ?></h2>
			<p><?php esc_html_e( 'Try adjusting your search criteria or browse all files.', 'the-library' ); ?></p>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'wprl_files_library' ) ); ?>" class="wprl-browse-all">
				<?php esc_html_e( 'Browse All Files', 'the-library' ); ?>
			</a>
		</div>

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
