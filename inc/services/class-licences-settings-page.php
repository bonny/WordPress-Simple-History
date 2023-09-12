<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

class Licences_Settings_Page extends Service {
	private const SETTINGS_SECTION_ID = 'simple_history_settings_section_tab_licenses';
	private const SETTINGS_PAGE_SLUG = 'simple_history_settings_menu_slug_tab_licenses';
	private const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_tab_licenses';
	private const OPTION_NAME_LICENSE_KEY = 'shp_license_key';
	private const OPTION_LICENSE_MESSAGE = 'example_plugin_license_message';

	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'add_settings_tab' ) );
		add_action( 'admin_menu', array( $this, 'register_and_add_settings' ) );

		add_action( 'update_option_' . self::OPTION_NAME_LICENSE_KEY, array( $this, 'handle_license_activation' ), 10, 3 );
	}

	/**
	 * Activate license.
	 *
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * @param array $old_key_value The old option value = old license key value.
	 * @param array $new_key_value The new option value = new license key value.
	 * @param string $option The option name.
	 */
	public function handle_license_activation( $old_key_value, $new_key_value, $option ) {
		// Activate license if new key is set and is not same as old key.
		if ( isset( $new_key_value ) && ! empty( $new_key_value ) && $old_key_value !== $new_key_value ) {
			$this->activate_license( $new_key_value );
		}

		// Deactivate old license when new key is added that is empty.
		if ( isset( $new_key_value ) && empty( $new_key_value ) && ! empty( $old_key_value ) ) {
			$license_message = json_decode( $this->get_license_message() );
			if ( isset( $license_message->data->instance->id ) ) {
				$this->deactivate_license( $old_key_value, $license_message->data->instance->id );
			}
		}
	}

	public function activate_license( $license_key ) {
		$activation_url = add_query_arg(
			array(
				'license_key' => $license_key,
				'instance_name' => home_url(),
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/activate'
		);

		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout' => 10,
			)
		);

		// sh_d('activate license', $license_key, $activation_url, $response);

		if (
			is_wp_error( $response )
			|| ( 200 !== wp_remote_retrieve_response_code( $response ) && 400 !== wp_remote_retrieve_response_code( $response ) )
			|| empty( wp_remote_retrieve_body( $response ) )
		) {
			return;
		}

		update_option( 'example_plugin_license_message', wp_remote_retrieve_body( $response ) );
	}

	public function deactivate_license( $license_key, $instance_id ) {
		$activation_url = add_query_arg(
			array(
				'license_key' => $license_key,
				'instance_id' => $instance_id,
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/deactivate'
		);

		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout' => 10,
			)
		);

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			delete_option( 'example_plugin_license_message' );
		}
	}

	/**
	 * Get user entered license key.
	 *
	 * @return string|false Key if set or false if not set.
	 */
	public static function get_license_key() {
		return get_option( self::OPTION_NAME_LICENSE_KEY, false );
	}

	/**
	 * Get license message.
	 * This is the message returned from the license server.
	 *
	 * @return string|false Message if set or false if not set.
	 */
	public static function get_license_message() {
		return get_option( self::OPTION_LICENSE_MESSAGE, false );
	}

	/**
	 * Add license settings tab,
	 * as a subtab to main settings tab.
	 */
	public function add_settings_tab() {
		$this->simple_history->register_settings_tab(
			[
				'parent_slug' => 'settings',
				'slug' => 'general_settings_subtab_licenses',
				'name' => __( 'Licences', 'simple-history' ),
				'order' => 5,
				'function' => [ $this, 'settings_output_licenses' ],
			]
		);
	}

	public function register_and_add_settings() {
		// Register setting options.
		register_setting(
			self::SETTINGS_OPTION_GROUP,
			self::OPTION_NAME_LICENSE_KEY,
			[
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Add licence settings section.
		add_settings_section(
			self::SETTINGS_SECTION_ID,
			Helpers::get_settings_section_title_output(
				__( 'License information', 'simple-history' ),
				'workspace_premium'
			),
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add a field/table row, for existing users setting.
		add_settings_field(
			self::OPTION_NAME_LICENSE_KEY,
			Helpers::get_settings_field_title_output( __( 'License Key', 'simple-history' ), 'key' ),
			[ $this, 'license_key_field_output' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID
		);

		// Add a field/table row, for managing licenses/sites.
		add_settings_field(
			'manage_licences',
			Helpers::get_settings_field_title_output( __( 'Plugins & Licences', 'simple-history' ), 'web' ),
			[ $this, 'activated_sites_settings_output' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID
		);
	}

	public function settings_section_output() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Enter your license key(s) to activate and retrieve updates for your PLUS plugins.', 'simple-history' ); ?></p>
			<p>
				<?php
				$link_url = 'https://simple-history.com/plus';
				$link_text = 'simple-history.com/plus';

				echo wp_kses(
					sprintf(
						/* translators: 1: link to plus plugins page, 2: link text */
						__(
							'Don\'t have any plus plugin yet? Visit <a href="%1$s" class="sh-ExternalLink" target="_blank">%2$s</a> for more information.',
							'simple-history'
						),
						esc_url( $link_url ),
						esc_html( $link_text )
					),
					[
						'a' => [
							'href' => [],
							'class' => [],
							'target' => [],
						],
					]
				)
				?>
			</p>
		</div>
		<?php
	}

	public function license_key_field_output() {
		$license_key = $this->get_license_key();
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME_LICENSE_KEY ); ?>" value="<?php echo esc_attr( $license_key ); ?>" />
		<?php

		$license_message = json_decode( $this->get_license_message() );
		#sh_d('$license_message', $license_message);
		$message = false;

		if ( isset( $license_message->data->activated ) ) {
			if ( $license_message->data->activated ) {
				$message = "💪 License is active. You have {$license_message->data->license_key->activation_usage}/{$license_message->data->license_key->activation_limit} instances activated.";
			} else {
				// phpcs:ignore WordPress.PHP.DisallowShortTernary.Found
				$message = $license_message->error ?: 'License for this site is not active. Click the button below to activate.';
			}
		}

		if ( isset( $license_key ) && ! empty( $license_key ) && $message ) {
			echo "<p class='description'>" . esc_html( $message ) . '</p>';
		}

		/*
		"This license key has reached the activation limit."
		står kvar även om man tar bort sajten i lemon squeesys admin.
		*/
	}

	public function activated_sites_settings_output() {
		?>

			<p>
				Visit the
				<a href="https://app.lemonsqueezy.com/my-orders/" class="sh-ExternalLink" target="_blank">
					<?php esc_html_e( 'My orders', 'simple-history' ); ?>
				</a>
				page at the Lemon Squeezy website to view and manage your licences and sites.
			</p>
			
			<p>There you can also download the plugins you have bought.</p>
		<?php
	}

	/**
	 * Output for the tab.
	 */
	public function settings_output_licenses() {
		// Output setting sections.
		?>
		<div class="wrap sh-Page-content">
			<!-- <h2>Licences</h2>
			<p>Simple History Plus is a premium plugin. You need a licence key to use it.</p>
			<p>Enter your licence key below to activate Simple History Plus.</p> -->

			<form method="post" action="options.php">
				<?php
				// Prints out all settings sections added to a particular settings page.
				do_settings_sections( self::SETTINGS_PAGE_SLUG );

				// Output nonce, action, and option_page fields.
				settings_fields( self::SETTINGS_OPTION_GROUP );

				submit_button();
				?>
		</div>
		<?php
	}
}
