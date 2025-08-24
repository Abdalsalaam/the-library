<?php
/**
 * Frontend Class
 *
 * @package TheLibrary
 */

namespace TheLibrary;

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
				'loading_text'          => esc_html__( 'Loading...', 'the-library' ),
				'error_message'         => esc_html__( 'Error loading files. Please try again.', 'the-library' ),
				'form_validation'       => self::get_form_validation_rules(),
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
		if ( Utils::is_files_archive() || Utils::is_file_category() ) {
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
							'taxonomy' => 'wprl_file_category',
							'field'    => 'term_id',
							'terms'    => intval( wp_unslash( $_GET['wprl_category'] ) ),
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
							'key'   => '_wprl_file_type',
							'value' => sanitize_title_for_query( wp_unslash( $_GET['wprl_file_type'] ) ),
						),
					)
				);
			}

			// Handle sorting.
			if ( ! empty( $_GET['wprl_sort'] ) ) {
				switch ( sanitize_text_field( wp_unslash( $_GET['wprl_sort'] ) ) ) {
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
					case 'date_desc':
					default:
						$query->set( 'orderby', 'date' );
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
		$categories = get_the_terms( get_the_ID(), 'wprl_file_category' );
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
							__( 'Download %s', 'the-library' ),
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
							__( 'View details for %s', 'the-library' ),
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
								__( 'Download %s', 'the-library' ),
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
										__( 'View all %s files', 'the-library' ),
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
							<span class="screen-reader-text"><?php esc_html_e( 'File size:', 'the-library' ); ?></span>
							<?php echo esc_html( size_format( $file_data['size'] ) ); ?>
						</span>
					<?php endif; ?>

					<?php if ( $file_data['type'] ) : ?>
						<span class="wprl-file-type">
							<i class="wprl-icon-type" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'File type:', 'the-library' ); ?></span>
							<?php echo esc_html( $file_data['type'] ); ?>
						</span>
					<?php endif; ?>

					<span class="wprl-download-count">
						<i class="wprl-icon-download" aria-hidden="true"></i>
						<span class="screen-reader-text"><?php esc_html_e( 'Download count:', 'the-library' ); ?></span>
						<?php echo esc_html( intval( $file_data['download_count'] ) ); ?>
						<?php
						/* translators: %d: download count */
						printf( esc_html( _n( 'download', 'downloads', intval( $file_data['download_count'] ), 'the-library' ) ) );
						?>
					</span>

					<?php if ( $args['show_date'] ) : ?>
						<span class="wprl-file-date">
							<i class="wprl-icon-date" aria-hidden="true"></i>
							<span class="screen-reader-text"><?php esc_html_e( 'Published:', 'the-library' ); ?></span>
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
								__( 'View details and download %s', 'the-library' ),
								$post_title
							)
						);
						?>
						">
						<?php esc_html_e( 'View Details', 'the-library' ); ?>
						<span class="screen-reader-text">
						<?php
						echo esc_html(
							sprintf(
							/* translators: %s: file title */
								__( 'for %s', 'the-library' ),
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
	 * Generate dynamic download form fields based on admin settings.
	 *
	 * @return string Form fields HTML.
	 */
	public static function get_download_form_fields(): string {
		$enabled_fields  = Settings::get_enabled_fields();
		$required_fields = Settings::get_required_fields();
		$form_html       = '';

		// Name field.
		if ( in_array( 'name_field', $enabled_fields, true ) ) {
			$is_required = in_array( 'name_field', $required_fields, true );
			$form_html  .= self::render_form_field(
				'name',
				'user_name',
				esc_html__( 'Full Name', 'the-library' ),
				'text',
				$is_required
			);
		}

		// Email field.
		if ( in_array( 'email_field', $enabled_fields, true ) ) {
			$is_required = in_array( 'email_field', $required_fields, true );
			$form_html  .= self::render_form_field(
				'email',
				'user_email',
				esc_html__( 'Email Address', 'the-library' ),
				'email',
				$is_required
			);
		}

		// Phone field.
		if ( in_array( 'phone_field', $enabled_fields, true ) ) {
			$is_required = in_array( 'phone_field', $required_fields, true );
			$form_html  .= self::render_form_field(
				'phone',
				'user_mobile',
				esc_html__( 'Mobile Number', 'the-library' ),
				'tel',
				$is_required
			);
		}

		return $form_html;
	}

	/**
	 * Render individual form field.
	 *
	 * @param string $field_type Field type identifier.
	 * @param string $field_name Field name attribute.
	 * @param string $field_label Field label text.
	 * @param string $input_type HTML input type.
	 * @param bool   $is_required Whether field is required.
	 * @return string Field HTML.
	 */
	private static function render_form_field( string $field_type, string $field_name, string $field_label, string $input_type, bool $is_required ): string {
		$required_attr = $is_required ? 'required' : '';
		$required_text = $is_required ? ' *' : '';
		$field_id      = 'wprl_' . $field_name;

		ob_start();
		?>
		<div class="wprl-form-field">
			<label for="<?php echo esc_attr( $field_id ); ?>">
				<?php echo esc_html( $field_label . $required_text ); ?>
			</label>
			<input type="<?php echo esc_attr( $input_type ); ?>"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					<?php echo esc_attr( $required_attr ); ?>
					data-field-type="<?php echo esc_attr( $field_type ); ?>">
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get form validation rules for JavaScript.
	 *
	 * @return array Validation rules.
	 */
	public static function get_form_validation_rules(): array {
		$enabled_fields   = Settings::get_enabled_fields();
		$required_fields  = Settings::get_required_fields();
		$validation_rules = array();

		// Name field rules.
		if ( in_array( 'name_field', $enabled_fields, true ) ) {
			$validation_rules['user_name'] = array(
				'enabled'  => true,
				'required' => in_array( 'name_field', $required_fields, true ),
				'type'     => 'text',
			);
		}

		// Email field rules.
		if ( in_array( 'email_field', $enabled_fields, true ) ) {
			$validation_rules['user_email'] = array(
				'enabled'  => true,
				'required' => in_array( 'email_field', $required_fields, true ),
				'type'     => 'email',
			);
		}

		// Phone field rules.
		if ( in_array( 'phone_field', $enabled_fields, true ) ) {
			$validation_rules['user_mobile'] = array(
				'enabled'  => true,
				'required' => in_array( 'phone_field', $required_fields, true ),
				'type'     => 'tel',
			);
		}

		return $validation_rules;
	}
}