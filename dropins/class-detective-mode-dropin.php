<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Debug
 * Dropin Description: Add some extra info to each logged context when SIMPLE_HISTORY_LOG_DEBUG is set and true, or when Detective mode is enabled.
 */
class Detective_Mode_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		$this->register_settings();

		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );

		// Bail if no debug mode is active.
		if ( ! Helpers::log_debug_is_enabled() && ! Helpers::detective_mode_is_enabled() ) {
			return;
		}

		add_action( 'simple_history/log_argument/context', array( $this, 'append_debug_info_to_context' ), 10, 4 );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		$settings_general_option_group = $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP;

		// Checkbox for debug setting that logs extra much info.
		register_setting(
			$settings_general_option_group,
			'simple_history_detective_mode_enabled',
			[
				'sanitize_callback' => [ Helpers::class, 'sanitize_checkbox_input' ],
			]
		);
	}

	/**
	 * Add settings field.
	 *
	 * Function fired from action `simple_history/settings_page/general_section_output`.
	 */
	public function on_general_section_output() {
		$settings_section_general_id = $this->simple_history::SETTINGS_SECTION_GENERAL_ID;
		$settings_menu_slug          = $this->simple_history::SETTINGS_MENU_SLUG;

		add_settings_field(
			'simple_history_debug',
			Helpers::get_settings_field_title_output( __( 'Detective mode', 'simple-history' ), 'mystery' ),
			[ $this, 'settings_field_detective_mode' ],
			$settings_menu_slug,
			$settings_section_general_id
		);
	}

	/**
	 * Settings field output.
	 */
	public function settings_field_detective_mode() {
		$detective_mode_enabled = Helpers::detective_mode_is_enabled();

		?>
		<label>
			<input <?php checked( $detective_mode_enabled ); ?> type="checkbox" value="1" name="simple_history_detective_mode_enabled" />
			<?php esc_html_e( 'Enable detective mode', 'simple-history' ); ?>
		</label>
		
		<p class="description">
			<?php
			echo wp_kses(
				__( 'When enabled, Detective Mode captures in-depth data for each event, including the current <code>$_GET</code>, <code>$_POST</code> values, the current filter name, and much more.', 'simple-history' ),
				[
					'code' => [],
				]
			);
			?>
		</p>

		<p class="description">
			<?php esc_html_e( 'While particularly useful for developers and administrators seeking to understand complex interactions or resolve issues, please note that enabling this feature may increase the volume of logged data.', 'simple-history' ); ?>
		</p>

		<p class="description">
			<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/support/detective-mode/', 'docs_detective_help' ) ); ?>" target="_blank" class="sh-ExternalLink">
			<?php esc_html_e( 'Read more about detective mode', 'simple-history' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Modify the context to add debug information.
	 *
	 * @param array                                 $context Context array.
	 * @param string                                $level Log level.
	 * @param string                                $message Log message.
	 * @param \Simple_History\Loggers\Simple_Logger $logger Logger instance.
	 */
	public function append_debug_info_to_context( $context, $level, $message, $logger ) {
		global $wp_current_filter;

		$context_key_prefix  = 'detective_mode_';
		$detective_mode_data = [];

		// Keys from $_SERVER to add to context.
		$arr_server_keys_to_add = [
			'HTTP_HOST',
			'REQUEST_URI',
			'REQUEST_METHOD',
			'CONTENT_TYPE',
			'SCRIPT_FILENAME',
			'SCRIPT_NAME',
			'PHP_SELF',
			'HTTP_ORIGIN',
			'CONTENT_TYPE',
			'HTTP_USER_AGENT',
			'REMOTE_ADDR',
			'REQUEST_TIME',
		];

		foreach ( $arr_server_keys_to_add as $key ) {
			if ( isset( $_SERVER[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$detective_mode_data[ 'server_' . strtolower( $key ) ] = wp_unslash( $_SERVER[ $key ] );
			}
		}

		// Copy of posted data, because we may remove sensitive data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get_data = $_GET;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = $_POST;

		$get_data  = $this->mask_sensitive_data( $get_data );
		$post_data = $this->mask_sensitive_data( $post_data );

		$detective_mode_data += [
			'get'             => $get_data,
			'post'            => $post_data,
			'files'           => $_FILES, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'current_filter'  => implode( ', ', $wp_current_filter ?? [] ),
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- This is a function that is used for debugging.
			'debug_backtrace' => wp_debug_backtrace_summary( null, 0, true ),
			'is_admin'        => is_admin(),
			'doing_ajax'      => wp_doing_ajax(),
			'doing_cron'      => wp_doing_cron(),
			'wp_cli'          => defined( 'WP_CLI' ) && WP_CLI,
			'is_multisite'    => is_multisite(),
			'php_sapi_name'   => php_sapi_name(),
		];

		// Command line arguments. Used by for example WP-CLI.
		if ( isset( $GLOBALS['argv'] ) ) {
			$detective_mode_data['command_line_arguments'] = implode( ' ', $GLOBALS['argv'] );
		}

		// Add all detective mode data to context, with a prefix.
		foreach ( $detective_mode_data as $key => $value ) {
			$context[ $context_key_prefix . $key ] = $value;
		}

		return $context;
	}

	/**
	 * Remove sensitive data from post data, like passwords.
	 *
	 * @param array $data Data to remove sensitive data from, probably GET or POST data.
	 * @return array Data with sensitive data removed.
	 */
	protected function mask_sensitive_data( $data ) {
		// Mask fields that begin with these strings.
		// So for example:
		// - "pass" will mask "password" and "password_2".
		// - "confirm_pass" will mask "confirm_password" but also "confirm_password_2".
		$fields_to_mask = [
			'pwd',
			'pass',
			'confirm_pass',
			'new_application_pass',
			'user_pwd',
			'user_password',
			'user_pass',
		];

		foreach ( $fields_to_mask as $field ) {
			$data = $this->mask_field_that_begin_with( $data, $field );
		}

		return $data;
	}

	/**
	 * Mask fields that begin with a certain string.
	 *
	 * @param array  $data Data to mask, probably GET or POST data.
	 * @param string $field_name_to_mask String to mask. Lowercase.
	 * @return array Data with sensitive data masked.
	 */
	protected function mask_field_that_begin_with( $data, $field_name_to_mask ) {
		foreach ( $data as $key => $value ) {
			$data_key_lowercase = strtolower( $key );
			if ( str_starts_with( $data_key_lowercase, $field_name_to_mask ) ) {
				$data[ $key ] = '<removed by Simple History>';
			}
		}

		return $data;
	}
}
