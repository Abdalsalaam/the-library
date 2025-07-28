<?php
/**
 * Single file template for Files Library.
 *
 * @package WPResourceLibrary
 */

get_header(); ?>

<div class="wprl-single-wrapper">
	<div class="container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php
			$file_data  = WPResourceLibrary\Utils::get_file_data();
			$categories = get_the_terms( get_the_ID(), 'wprl_file_category' );
			?>
			<article class="wprl-single-file">
				<header class="wprl-file-header">
					<h1><?php the_title(); ?></h1>

					<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
					<div class="wprl-file-categories">
						<?php foreach ( $categories as $category ) : ?>
							<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="wprl-category-tag">
								<?php echo esc_html( $category->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</header>

				<div class="wprl-file-content-wrapper">
					<div class="wprl-file-main">
						<?php if ( has_post_thumbnail() ) : ?>
						<div class="wprl-file-featured-image">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
						<?php endif; ?>

						<div class="wprl-file-description">
							<?php the_content(); ?>
						</div>

						<!-- Download Section -->
						<div class="wprl-download-section">
							<h3><?php esc_html_e( 'Download This File', 'wp-resource-library' ); ?></h3>

							<?php if ( ! empty( $file_data['url'] ) ) : ?>
								<?php if ( is_user_logged_in() ) : ?>
									<!-- Logged-in user - Direct download -->
									<div class="wprl-logged-in-download">
										<p><?php esc_html_e( 'Welcome back! You can download this file directly.', 'wp-resource-library' ); ?></p>
										<div class="wprl-direct-download-actions">
											<button type="button" id="wprl-direct-download" class="wprl-download-button" data-post-id="<?php echo esc_attr( get_the_ID() ); ?>">
												<i class="wprl-icon-download"></i>
												<?php esc_html_e( 'Download File', 'wp-resource-library' ); ?>
											</button>
										</div>
										<div id="wprl-direct-download-message" class="wprl-message" style="display: none;"></div>
									</div>
								<?php else : ?>
									<!-- Non-logged-in user - Form required -->
									<div class="wprl-download-info">
										<p><?php esc_html_e( 'To download this file, please provide your contact information below:', 'wp-resource-library' ); ?></p>
										<p><small>
										<?php
										/* translators: 1: opening link tag, 2: closing link tag */
										printf( esc_html__( 'Already have an account? %1$sLogin here%2$s for direct downloads.', 'wp-resource-library' ), '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">', '</a>' );
										?>
										</small></p>
									</div>
								<?php endif; ?>

								<?php if ( ! is_user_logged_in() ) : ?>
								<div id="wprl-download-form-container">
									<form id="wprl-download-form" class="wprl-download-form">
										<?php wp_nonce_field( 'wprl_download_nonce', 'wprl_download_nonce' ); ?>
										<input type="hidden" name="post_id" value="<?php echo esc_attr( get_the_ID() ); ?>">

										<div class="wprl-form-row">
											<div class="wprl-form-field">
												<label for="wprl_user_name"><?php esc_html_e( 'Full Name *', 'wp-resource-library' ); ?></label>
												<input type="text" id="wprl_user_name" name="user_name" required>
											</div>

											<div class="wprl-form-field">
												<label for="wprl_user_email"><?php esc_html_e( 'Email Address (Optional)', 'wp-resource-library' ); ?></label>
												<input type="email" id="wprl_user_email" name="user_email">
											</div>
										</div>

										<div class="wprl-form-row">
											<div class="wprl-form-field">
												<label for="wprl_user_mobile"><?php esc_html_e( 'Mobile Number *', 'wp-resource-library' ); ?></label>
												<input type="tel" id="wprl_user_mobile" name="user_mobile" required
														pattern="[\d\s\-\+\(\)]+"
														title="Please enter a valid mobile number (minimum 7 digits)"
														placeholder="e.g., +1234567890 or 123-456-7890"
														minlength="7" maxlength="20">
											</div>
										</div>

										<div class="wprl-form-actions">
											<button type="submit" class="wprl-download-button">
												<i class="wprl-icon-download"></i>
												<?php esc_html_e( 'Download File', 'wp-resource-library' ); ?>
											</button>
										</div>

										<div id="wprl-download-message" class="wprl-message" style="display: none;"></div>
									</form>
								</div>

								<div id="wprl-download-success" class="wprl-download-success" style="display: none;">
									<div class="wprl-success-message">
										<i class="wprl-icon-check"></i>
										<h4><?php esc_html_e( 'Thank you!', 'wp-resource-library' ); ?></h4>
										<p><?php esc_html_e( 'Your download will start automatically. If it doesn\'t start, click the button below.', 'wp-resource-library' ); ?></p>
										<button id="wprl-manual-download" class="wprl-manual-download-button">
											<?php esc_html_e( 'Download Now', 'wp-resource-library' ); ?>
										</button>
									</div>
								</div>
							<?php endif; ?>
							<?php else : ?>
								<div class="wprl-no-file">
									<p><?php esc_html_e( 'No file available for download.', 'wp-resource-library' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<aside class="wprl-file-sidebar">
						<!-- File Information -->
						<div class="wprl-sidebar-widget">
							<h4><?php esc_html_e( 'File Information', 'wp-resource-library' ); ?></h4>
							<ul class="wprl-file-info-list">
								<?php if ( $file_data['size'] ) : ?>
								<li>
									<strong><?php esc_html_e( 'File Size:', 'wp-resource-library' ); ?></strong>
									<?php echo esc_html( size_format( $file_data['size'] ) ); ?>
								</li>
								<?php endif; ?>

								<?php if ( $file_data['type'] ) : ?>
								<li>
									<strong><?php esc_html_e( 'File Type:', 'wp-resource-library' ); ?></strong>
									<?php echo esc_html( $file_data['type'] ); ?>
								</li>
								<?php endif; ?>

								<li>
									<strong><?php esc_html_e( 'Downloads:', 'wp-resource-library' ); ?></strong>
									<?php echo intval( $file_data['download_count'] ); ?>
								</li>

								<li>
									<strong><?php esc_html_e( 'Published:', 'wp-resource-library' ); ?></strong>
									<?php echo esc_html( get_the_date() ); ?>
								</li>

								<li>
									<strong><?php esc_html_e( 'Last Updated:', 'wp-resource-library' ); ?></strong>
									<?php echo esc_html( get_the_modified_date() ); ?>
								</li>
							</ul>
						</div>

						<!-- Categories -->
						<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
						<div class="wprl-sidebar-widget">
							<h4><?php esc_html_e( 'Categories', 'wp-resource-library' ); ?></h4>
							<ul class="wprl-categories-list">
								<?php foreach ( $categories as $category ) : ?>
								<li>
									<a href="<?php echo esc_url( get_term_link( $category ) ); ?>">
										<?php echo esc_html( $category->name ); ?>
										<span class="wprl-category-count">(<?php echo esc_html( $category->count ); ?>)</span>
									</a>
								</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<?php endif; ?>
						
						<!-- Related Files -->
						<?php
						$related_args = array(
							'post_type'      => 'wprl_files_library',
							'posts_per_page' => 6,
							'post_status'    => 'publish',
						);

						if ( $categories && ! is_wp_error( $categories ) ) {
							$related_args['tax_query'] = array(
								array(
									'taxonomy' => 'wprl_file_category',
									'field'    => 'term_id',
									'terms'    => wp_list_pluck( $categories, 'term_id' ),
								),
							);
						}

						$related_query = new WP_Query( $related_args );
						$exclude       = get_the_ID();
						$posts         = 0;

						if ( $related_query->have_posts() ) :
							?>
						<div class="wprl-sidebar-widget">
							<h4><?php esc_html_e( 'Related Files', 'wp-resource-library' ); ?></h4>
							<ul class="wprl-related-files">
								<?php
								while ( $related_query->have_posts() && $posts < 5 ) :
									$related_query->the_post();
                                    if ( get_the_ID() === $exclude ) {
                                        continue;
                                    }
									$posts++;
									?>
								<li>
									<a href="<?php the_permalink(); ?>" class="wprl-related-file">
										<?php if ( has_post_thumbnail() ) : ?>
											<div class="wprl-related-thumbnail">
												<?php the_post_thumbnail( 'thumbnail' ); ?>
											</div>
										<?php endif; ?>
										<div class="wprl-related-content">
											<h5><?php the_title(); ?></h5>
											<span class="wprl-related-date"><?php echo get_the_date(); ?></span>
										</div>
									</a>
								</li>
								<?php endwhile; ?>
							</ul>
						</div>
							<?php
							wp_reset_postdata();
						endif;
						?>

						<!-- Back to Library -->
						<div class="wprl-sidebar-widget">
							<a href="<?php echo esc_url( get_post_type_archive_link( 'wprl_files_library' ) ); ?>" class="wprl-back-to-library">
								<?php esc_html_e( '← Back to Files Library', 'wp-resource-library' ); ?>
							</a>
						</div>
					</aside>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var downloadToken = null;
	var downloadUrl = null;

	// Handle direct download for logged-in users
	$('#wprl-direct-download').on('click', function(e) {
		e.preventDefault();

		var $button = $(this);
		var postId = $button.data('post-id');
		var $messageDiv = $('#wprl-direct-download-message');
		var originalText = $button.html();

		// Show loading state.
		$button.html('<span class="wprl-spinner"></span> <?php esc_html_e( 'Processing...', 'wp-resource-library' ); ?>').prop('disabled', true);
		$messageDiv.hide();

		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'wprl_direct_download',
				nonce: '<?php echo esc_js( wp_create_nonce( 'wprl_direct_download_nonce' ) ); ?>',
				post_id: postId
			},
			success: function(response) {
				if (response.success) {
					// Start download immediately
					window.location.href = response.data.download_url;
				} else {
					$messageDiv.removeClass('wprl-success').addClass('wprl-error')
						.text(response.data.message).show();
				}
			},
			error: function() {
				$messageDiv.removeClass('wprl-success').addClass('wprl-error')
					.text('<?php esc_html_e( 'An error occurred. Please try again.', 'wp-resource-library' ); ?>').show();
			},
			complete: function() {
				$button.html(originalText).prop('disabled', false);
			}
		});
	});

	$('#wprl-download-form').on('submit', function(e) {
		e.preventDefault();

		var form = $(this);
		var submitButton = form.find('button[type="submit"]');
		var messageDiv = $('#wprl-download-message');

		// Disable submit button
		submitButton.prop('disabled', true).text('<?php esc_html_e( 'Processing...', 'wp-resource-library' ); ?>');

		$.ajax({
			url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
			type: 'POST',
			data: {
				action: 'wprl_submit_download_form',
				nonce: $('#wprl_download_nonce').val(),
				post_id: $('input[name="post_id"]').val(),
				user_name: $('#wprl_user_name').val(),
				user_email: $('#wprl_user_email').val(),
				user_mobile: $('#wprl_user_mobile').val()
			},
			success: function(response) {
				if (response.success) {
					downloadToken = response.data.download_token;
					downloadUrl = response.data.download_url;
					$('#wprl-download-form-container').hide();
					$('#wprl-download-success').show();

					// Auto-start download after 2 seconds
					setTimeout(function() {
						startDownload();
					}, 2000);
				} else {
					messageDiv.removeClass('wprl-success').addClass('wprl-error')
							.text(response.data.message).show();
				}
			},
			error: function() {
				messageDiv.removeClass('wprl-success').addClass('wprl-error')
						.text('<?php esc_html_e( 'An error occurred. Please try again.', 'wp-resource-library' ); ?>').show();
			},
			complete: function() {
				submitButton.prop('disabled', false).html('<i class="wprl-icon-download"></i> <?php esc_html_e( 'Download File', 'wp-resource-library' ); ?>');
			}
		});
	});

	$('#wprl-manual-download').on('click', function() {
		startDownload();
	});

	function startDownload() {
		if (downloadUrl) {
			// Directly use the download URL
			window.location.href = downloadUrl;
		} else if (downloadToken) {
			// Fallback: construct URL from token (for backward compatibility)
			var fallbackUrl = '<?php echo esc_js( home_url() ); ?>?wprl_download=1&token=' + downloadToken + '&post_id=<?php echo esc_js( get_the_ID() ); ?>';
			window.location.href = fallbackUrl;
		}
	}
});
</script>

<?php get_footer(); ?>
