<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Debug
 * Dropin Description: Add some extra info to each logged context when SIMPLE_HISTORY_LOG_DEBUG is set and true, or when Detective mode is enabled.
 */
class Detective_Mode_Dropin extends Dropin {
	/**
	 * Replacement written in place of a value whose key looks sensitive.
	 */
	const MASKED_VALUE = Helpers::MASKED_VALUE;

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

		<details class="description">
			<summary>
				<?php esc_html_e( 'Read more about detective mode', 'simple-history' ); ?>
			</summary>

			<p>
				<?php esc_html_e( 'While particularly useful for developers and administrators seeking to understand complex interactions or resolve issues, please note that enabling this feature may increase the volume of logged data.', 'simple-history' ); ?>
			</p>

			<p>
				<?php esc_html_e( 'Heads up: Since request data is captured, sensitive information could end up in the log. Fields whose name looks like a password, token, secret or key are masked automatically, but that is a best guess and cannot catch every name — we recommend keeping Detective Mode enabled only while actively troubleshooting.', 'simple-history' ); ?>
			</p>

			<p>
				<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/support/detective-mode/', 'docs_detective_help' ) ); ?>" target="_blank" class="sh-ExternalLink">
				<?php esc_html_e( 'Read more on simple-history.com', 'simple-history' ); ?>
				</a>
			</p>
		</details>
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

		// Resolved once and threaded through every masking call below, so the
		// filter fires once per event rather than once per value inspected.
		$sensitive_field_names = Helpers::get_sensitive_field_names();

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
			if ( ! isset( $_SERVER[ $key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = wp_unslash( $_SERVER[ $key ] );

			// REQUEST_URI carries the query string, so it repeats every $_GET
			// value verbatim — masking $_GET alone would leave the same secret
			// sitting in plain sight one context key over.
			if ( $key === 'REQUEST_URI' ) {
				$value = Helpers::mask_sensitive_query_string( $value, $sensitive_field_names );
			}

			$detective_mode_data[ 'server_' . strtolower( $key ) ] = $value;
		}

		// Copy of posted data, because we may remove sensitive data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get_data = $_GET;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = $_POST;

		$get_data  = $this->mask_fields( $get_data, $sensitive_field_names );
		$post_data = $this->mask_fields( $post_data, $sensitive_field_names );

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
		// Masked because commands routinely carry credentials as flags, e.g.
		// `wp user update bob --user_pass=secret`.
		if ( isset( $GLOBALS['argv'] ) ) {
			$detective_mode_data['command_line_arguments'] = implode(
				' ',
				array_map(
					function ( $argument ) use ( $sensitive_field_names ) {
						return $this->mask_sensitive_cli_argument( $argument, $sensitive_field_names );
					},
					$GLOBALS['argv']
				)
			);
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
		// Substring match, so "pass" covers "old_password" and
		// "confirm_password_2" and not just names that start with it.
		return $this->mask_fields( $data, Helpers::get_sensitive_field_names() );
	}

	/**
	 * Recursively mask values whose key matches any of the given needles.
	 *
	 * The needle list is passed in rather than fetched per key so the whole
	 * structure is walked once, instead of once per needle.
	 *
	 * @param array         $data    Data to mask, probably GET or POST data.
	 * @param array<string> $needles Lowercase substrings.
	 * @return array Data with sensitive values masked.
	 */
	protected function mask_fields( $data, array $needles ) {
		foreach ( $data as $key => $value ) {
			$key_lowercase = strtolower( (string) $key );

			foreach ( $needles as $needle ) {
				if ( str_contains( $key_lowercase, $needle ) ) {
					$data[ $key ] = self::MASKED_VALUE;

					continue 2;
				}
			}

			// Recurse into nested arrays. Request bodies are commonly nested —
			// $_POST['user']['password'] is a password no matter how deep the
			// key sits, and a top level only walk would store it in full.
			if ( ! is_array( $value ) ) {
				continue;
			}

			$data[ $key ] = $this->mask_fields( $value, $needles );
		}

		return $data;
	}

	/**
	 * Mask the value of a single command line argument.
	 *
	 * Handles the "--name=value" form used by WP-CLI. Bare values are left
	 * alone: without a name there is nothing to judge them by.
	 *
	 * @param mixed              $argument One entry from $GLOBALS['argv'].
	 * @param array<string>|null $needles  Lowercase substrings. Resolved from the filter when null.
	 * @return string Argument with a sensitive value replaced.
	 */
	protected function mask_sensitive_cli_argument( $argument, $needles = null ) {
		$argument = (string) $argument;

		$separator = strpos( $argument, '=' );

		if ( $separator === false ) {
			return $argument;
		}

		$name = ltrim( substr( $argument, 0, $separator ), '-' );

		return Helpers::is_sensitive_field_name( $name, $needles )
			? substr( $argument, 0, $separator ) . '=' . self::MASKED_VALUE
			: $argument;
	}
}
