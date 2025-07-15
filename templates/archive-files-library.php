<?php
/**
 * Archive template for Files Library
 */

get_header(); ?>

<div class="wprl-archive-wrapper">
	<div class="container">
		<header class="wprl-archive-header">
			<h1 class="wprl-archive-title">
				<?php
				if ( is_tax( 'file_category' ) ) {
					single_term_title( esc_html__( 'Files in Category: ', 'wp-resource-library' ) );
				} else {
					_e( 'Files Library', 'wp-resource-library' );
				}
				?>
			</h1>

			<?php if ( is_tax( 'file_category' ) && term_description() ) : ?>
				<div class="wprl-archive-description">
					<?php echo term_description(); ?>
				</div>
			<?php endif; ?>
		</header>

		<!-- Filters and Search -->
		<div class="wprl-filters-wrapper">
			<form method="get" class="wprl-filters-form">
				<div class="wprl-filters-row">
					<!-- Search -->
					<div class="wprl-search-field">
						<input type="text"
								name="wprl_search"
								value="<?php echo esc_attr( isset( $_GET['wprl_search'] ) ? $_GET['wprl_search'] : '' ); ?>"
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
									'taxonomy'   => 'file_category',
									'hide_empty' => true,
								)
							);
							$selected_category = isset( $_GET['wprl_category'] ) ? $_GET['wprl_category'] : '';

							foreach ( $categories as $category ) {
								$selected = selected( $selected_category, $category->term_id, false );
								echo '<option value="' . esc_attr( $category->term_id ) . '" ' . $selected . '>' . esc_html( $category->name ) . ' (' . $category->count . ')</option>';
							}
							?>
						</select>
					</div>

					<!-- File Type Filter -->
					<div class="wprl-filter-field">
						<select name="wprl_file_type" class="wprl-file-type-filter">
							<option value=""><?php esc_html_e( 'All File Types', 'wp-resource-library' ); ?></option>
							<?php
							global $wpdb;
							$file_types = $wpdb->get_results(
								"SELECT DISTINCT meta_value as file_type, COUNT(*) as count
                                 FROM {$wpdb->postmeta} pm
                                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                                 WHERE pm.meta_key = '_wprl_file_type'
                                 AND pm.meta_value != ''
                                 AND p.post_status = 'publish'
                                 AND p.post_type = 'files_library'
                                 GROUP BY pm.meta_value
                                 ORDER BY count DESC"
							);

							$selected_file_type = isset( $_GET['wprl_file_type'] ) ? $_GET['wprl_file_type'] : '';

							foreach ( $file_types as $type ) {
								$type_name = strtoupper( pathinfo( $type->file_type, PATHINFO_EXTENSION ) );
								if ( empty( $type_name ) ) {
									$type_name = ucfirst( explode( '/', $type->file_type )[1] );
								}
								$selected = selected( $selected_file_type, $type->file_type, false );
								echo '<option value="' . esc_attr( $type->file_type ) . '" ' . $selected . '>' . esc_html( $type_name ) . ' (' . $type->count . ')</option>';
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

							$selected_sort = isset( $_GET['wprl_sort'] ) ? $_GET['wprl_sort'] : 'date_desc';

							foreach ( $sort_options as $value => $label ) {
								$selected = selected( $selected_sort, $value, false );
								echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
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
					<?php if ( ! empty( $_GET['wprl_search'] ) || ! empty( $_GET['wprl_category'] ) || ! empty( $_GET['wprl_file_type'] ) || ! empty( $_GET['wprl_sort'] ) ) : ?>
					<div class="wprl-filter-field">
						<a href="<?php echo get_post_type_archive_link( 'files_library' ); ?>" class="wprl-clear-filters">
							<?php esc_html_e( 'Clear Filters', 'wp-resource-library' ); ?>
						</a>
					</div>
					<?php endif; ?>
				</div>
			</form>
		</div>

		<!-- Results Info -->
		<div class="wprl-results-info">
			<?php
			global $wp_query;
			$total_files  = $wp_query->found_posts;
			$current_page = max( 1, get_query_var( 'paged' ) );
			$per_page     = get_query_var( 'posts_per_page' );
			$start        = ( ( $current_page - 1 ) * $per_page ) + 1;
			$end          = min( $current_page * $per_page, $total_files );

			if ( $total_files > 0 ) {
				printf(
					__( 'Showing %1$d-%2$d of %3$d files', 'wp-resource-library' ),
					$start,
					$end,
					$total_files
				);
			} else {
				_e( 'No files found', 'wp-resource-library' );
			}
			?>
		</div>

		<!-- Files Grid -->
		<?php if ( have_posts() ) : ?>
		<div class="wprl-files-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<?php
				$file_url       = get_post_meta( get_the_ID(), '_wprl_file_url', true );
				$file_type      = get_post_meta( get_the_ID(), '_wprl_file_type', true );
				$download_count = get_post_meta( get_the_ID(), '_wprl_download_count', true );
				$categories     = get_the_terms( get_the_ID(), 'file_category' );
				?>

				<article class="wprl-file-card">
					<?php if ( has_post_thumbnail() ) : ?>
					<div class="wprl-file-thumbnail">
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'medium' ); ?>
						</a>
					</div>
					<?php endif; ?>

					<div class="wprl-file-content">
						<h2>
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>

						<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
						<div class="wprl-file-categories">
							<?php foreach ( $categories as $category ) : ?>
								<a href="<?php echo get_term_link( $category ); ?>" class="wprl-category-tag">
									<?php echo esc_html( $category->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<div class="wprl-file-excerpt">
							<?php the_excerpt(); ?>
						</div>

						<div class="wprl-file-meta">
							<?php if ( $file_type ) : ?>
								<span class="wprl-file-type">
									<i class="wprl-icon-type"></i>
									<?php echo esc_html( strtoupper( pathinfo( $file_url, PATHINFO_EXTENSION ) ) ); ?>
								</span>
							<?php endif; ?>

							<span class="wprl-download-count">
								<i class="wprl-icon-download"></i>
								<?php printf( esc_html__( '%d downloads', 'wp-resource-library' ), intval( $download_count ) ); ?>
							</span>

							<span class="wprl-file-date">
								<i class="wprl-icon-date"></i>
								<?php echo get_the_date(); ?>
							</span>
						</div>

						<div class="wprl-file-actions">
							<a href="<?php the_permalink(); ?>" class="btn button">
								<?php esc_html_e( 'View Details', 'wp-resource-library' ); ?>
							</a>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<!-- Pagination -->
		<div class="wprl-pagination">
			<?php
			echo paginate_links(
				array(
					'prev_text' => esc_html__( '&laquo; Previous', 'wp-resource-library' ),
					'next_text' => esc_html__( 'Next &raquo;', 'wp-resource-library' ),
					'type'      => 'list',
				)
			);
			?>
		</div>

		<?php else : ?>

		<div class="wprl-no-files">
			<h2><?php esc_html_e( 'No files found', 'wp-resource-library' ); ?></h2>
			<p><?php esc_html_e( 'Try adjusting your search criteria or browse all files.', 'wp-resource-library' ); ?></p>
			<a href="<?php echo get_post_type_archive_link( 'files_library' ); ?>" class="wprl-browse-all">
				<?php esc_html_e( 'Browse All Files', 'wp-resource-library' ); ?>
			</a>
		</div>

		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
