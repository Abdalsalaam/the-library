<?php
/**
 * Settings Class - Handle admin configuration for download form fields
 *
 * @package TheLibrary
 */

namespace TheLibrary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class for managing download form field configuration.
 */
class Settings {

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wprl_form_settings';

	/**
	 * Default field settings.
	 * Name and mobile required, email optional.
	 *
	 * @var array
	 */
	private static array $default_settings = array(
		'name_field'  => array(
			'enabled'  => true,
			'required' => true,
		),
		'email_field' => array(
			'enabled'  => true,
			'required' => false,
		),
		'phone_field' => array(
			'enabled'  => true,
			'required' => true,
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_scripts' ) );
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings() {
		// Register settings.
		register_setting(
			'wprl_form_settings_group',
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::$default_settings,
			)
		);

		// Add settings sections.
		add_settings_section(
			'wprl_form_fields_section',
			esc_html__( 'Download Form Fields Configuration', 'the-library' ),
			array( $this, 'form_fields_section_callback' ),
			'wprl_form_settings'
		);

		// Add individual field settings.
		$this->add_field_settings();
	}

	/**
	 * Add field settings.
	 */
	private function add_field_settings() {
		$fields = array(
			'name_field'  => esc_html__( 'Name Field', 'the-library' ),
			'email_field' => esc_html__( 'Email Field', 'the-library' ),
			'phone_field' => esc_html__( 'Phone Field', 'the-library' ),
		);

		foreach ( $fields as $field_key => $field_label ) {
			add_settings_field(
				$field_key,
				$field_label,
				array( $this, 'field_setting_callback' ),
				'wprl_form_settings',
				'wprl_form_fields_section',
				array(
					'field_key' => $field_key,
					'label'     => $field_label,
				)
			);
		}
	}

	/**
	 * Form fields section callback.
	 */
	public function form_fields_section_callback() {
		echo '<p>' . esc_html__( 'Configure which fields to display in the download form and whether they should be required.', 'the-library' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Note:', 'the-library' ) . '</strong> ' . esc_html__( 'Changes will apply to new download requests. Existing data will remain unchanged.', 'the-library' ) . '</p>';
	}

	/**
	 * Field setting callback.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_setting_callback( array $args ) {
		$field_key      = $args['field_key'];
		$settings       = self::get_settings();
		$field_settings = $settings[ $field_key ] ?? self::$default_settings[ $field_key ];

		$enabled_name  = self::OPTION_NAME . '[' . $field_key . '][enabled]';
		$required_name = self::OPTION_NAME . '[' . $field_key . '][required]';
		?>
		<div class="wprl-field-setting">
			<label class="wprl-field-setting-row">
				<input type="checkbox"
						name="<?php echo esc_attr( $enabled_name ); ?>"
						value="1"
						<?php checked( $field_settings['enabled'], true ); ?>
						class="wprl-field-enabled">
				<span><?php esc_html_e( 'Display this field', 'the-library' ); ?></span>
			</label>

			<label class="wprl-field-setting-row wprl-required-setting"
					style="margin-left: 20px; <?php echo $field_settings['enabled'] ? '' : 'display: none;'; ?>">
				<input type="checkbox"
						name="<?php echo esc_attr( $required_name ); ?>"
						value="1"
						<?php checked( $field_settings['required'], true ); ?>
						<?php disabled( ! $field_settings['enabled'] ); ?>
						class="wprl-field-required">
				<span><?php esc_html_e( 'Make this field required', 'the-library' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw input data (can be null when no checkboxes are checked).
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		// Handle null input (when no checkboxes are checked).
		if ( null === $input || ! is_array( $input ) ) {
			$input = array();
		}

		$sanitized = array();

		foreach ( self::$default_settings as $field_key => $default_values ) {
			$sanitized[ $field_key ] = array(
				'enabled'  => ! empty( $input[ $field_key ]['enabled'] ),
				'required' => ! empty( $input[ $field_key ]['required'] ) && ! empty( $input[ $field_key ]['enabled'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * Enqueue settings scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_settings_scripts( string $hook ) {
		if ( false === strpos( $hook, 'wprl-form-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'wprl-admin-css' );
		wp_add_inline_style( 'wprl-admin-css', $this->get_settings_css() );
		wp_add_inline_script( 'wprl-admin-js', $this->get_settings_js() );
	}

	/**
	 * Get settings CSS.
	 *
	 * @return string CSS styles.
	 */
	private function get_settings_css(): string {
		return '
		.wprl-field-setting {
			margin-bottom: 15px;
			padding: 15px;
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.wprl-field-setting-row {
			display: block;
			margin-bottom: 8px;
			cursor: pointer;
		}
		.wprl-field-setting-row input[type="checkbox"] {
			margin-right: 8px;
		}
		.wprl-required-setting.disabled {
			opacity: 0.5;
		}
		';
	}

	/**
	 * Get settings JavaScript.
	 *
	 * @return string JavaScript code.
	 */
	private function get_settings_js(): string {
		return '
		jQuery(document).ready(function($) {
			$(".wprl-field-enabled").on("change", function() {
				var $requiredSetting = $(this).closest(".wprl-field-setting").find(".wprl-required-setting");
				var $requiredCheckbox = $requiredSetting.find(".wprl-field-required");

				if ($(this).is(":checked")) {
					$requiredSetting.show();
					$requiredCheckbox.prop("disabled", false);
				} else {
					$requiredSetting.hide();
					$requiredCheckbox.prop("checked", false).prop("disabled", true);
				}
			});
		});
		';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! Utils::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'the-library' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Download Form Settings', 'the-library' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'wprl_form_settings_group' );
				do_settings_sections( 'wprl_form_settings' );
				submit_button();
				?>
			</form>

			<div class="wprl-settings-info" style="margin-top: 30px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<h3><?php esc_html_e( 'Field Information', 'the-library' ); ?></h3>
				<ul>
					<li><strong><?php esc_html_e( 'Name Field:', 'the-library' ); ?></strong> <?php esc_html_e( 'Collects the user\'s full name', 'the-library' ); ?></li>
					<li><strong><?php esc_html_e( 'Email Field:', 'the-library' ); ?></strong> <?php esc_html_e( 'Collects the user\'s email address', 'the-library' ); ?></li>
					<li><strong><?php esc_html_e( 'Phone Field:', 'the-library' ); ?></strong> <?php esc_html_e( 'Collects the user\'s mobile/phone number', 'the-library' ); ?></li>
				</ul>
				<p><em><?php esc_html_e( 'At least one field must be enabled and required to ensure user data collection.', 'the-library' ); ?></em></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Get current settings.
	 *
	 * @return array Current settings with defaults applied.
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, self::$default_settings );

		// Ensure all fields exist with defaults.
		foreach ( self::$default_settings as $field_key => $default_values ) {
			if ( ! isset( $settings[ $field_key ] ) ) {
				$settings[ $field_key ] = $default_values;
			} else {
				$settings[ $field_key ] = wp_parse_args( $settings[ $field_key ], $default_values );
			}
		}

		return $settings;
	}

	/**
	 * Get field configuration.
	 *
	 * @param string $field_key Field key (name_field, email_field, phone_field).
	 * @return array Field configuration.
	 */
	public static function get_field_config( string $field_key ): array {
		$settings = self::get_settings();
		return $settings[ $field_key ] ?? self::$default_settings[ $field_key ] ?? array(
			'enabled'  => false,
			'required' => false,
		);
	}

	/**
	 * Check if field is enabled.
	 *
	 * @param string $field_key Field key.
	 * @return bool True if field is enabled.
	 */
	public static function is_field_enabled( string $field_key ): bool {
		$config = self::get_field_config( $field_key );
		return ! empty( $config['enabled'] );
	}

	/**
	 * Check if field is required.
	 *
	 * @param string $field_key Field key.
	 * @return bool True if field is required.
	 */
	public static function is_field_required( string $field_key ): bool {
		$config = self::get_field_config( $field_key );
		return ! empty( $config['required'] ) && ! empty( $config['enabled'] );
	}

	/**
	 * Get enabled fields.
	 *
	 * @return array Array of enabled field keys.
	 */
	public static function get_enabled_fields(): array {
		$settings       = self::get_settings();
		$enabled_fields = array();

		foreach ( $settings as $field_key => $config ) {
			if ( ! empty( $config['enabled'] ) ) {
				$enabled_fields[] = $field_key;
			}
		}

		return $enabled_fields;
	}

	/**
	 * Get required fields.
	 *
	 * @return array Array of required field keys.
	 */
	public static function get_required_fields(): array {
		$settings        = self::get_settings();
		$required_fields = array();

		foreach ( $settings as $field_key => $config ) {
			if ( ! empty( $config['enabled'] ) && ! empty( $config['required'] ) ) {
				$required_fields[] = $field_key;
			}
		}

		return $required_fields;
	}
}