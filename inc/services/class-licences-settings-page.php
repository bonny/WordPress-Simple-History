<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Services\Plus_Licences;

class Licences_Settings_Page extends Service {
	/** @var Plus_Licences $licences_service */
	private $licences_service;

	private const SETTINGS_SECTION_ID = 'simple_history_settings_section_tab_licenses';
	private const SETTINGS_PAGE_SLUG = 'simple_history_settings_menu_slug_tab_licenses';
	private const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_tab_licenses';
	private const OPTION_NAME_LICENSE_KEY = 'shp_license_key';
	private const OPTION_LICENSE_MESSAGE = 'example_plugin_license_message';

	public function loaded() {
		// Only load if dev mode is enabled, for now,
		// since Plus Plugins are not ready yet.
		// if ( false === Helpers::dev_mode_is_enabled() ) {
		// 	return;
		// }

		$this->licences_service = $this->simple_history->get_service( Plus_Licences::class );

		add_action( 'after_setup_theme', array( $this, 'add_settings_tab' ) );
		add_action( 'admin_menu', array( $this, 'register_and_add_settings' ) );
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
		Helpers::add_settings_section(
			self::SETTINGS_SECTION_ID,
			[ __( 'License information for add-ons', 'simple-history' ), 'workspace_premium' ],
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add row for licence keys.
		add_settings_field(
			self::OPTION_NAME_LICENSE_KEY,
			Helpers::get_settings_field_title_output( __( 'License Keys', 'simple-history' ), 'key' ),
			[ $this, 'license_keys_field_output' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID,
		);

		// Add row for managing licenses/sites.
		add_settings_field(
			'manage_licences',
			Helpers::get_settings_field_title_output( __( 'Customer Portal', 'simple-history' ), 'web' ),
			[ $this, 'activated_sites_settings_output' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID
		);
	}

	public function settings_section_output() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Enter your license key(s) to activate and retrieve updates for your add-on plugins.', 'simple-history' ); ?></p>
			<p>
				<?php
				$link_url = 'https://simple-history.com/add-ons';
				$link_text = 'simple-history.com/add-ons';

				echo wp_kses(
					sprintf(
						/* translators: 1: link to plus plugins page, 2: link text */
						__(
							'Don\'t have any add-ons yet? Visit <a href="%1$s" class="sh-ExternalLink" target="_blank">%2$s</a> to see available add-ons.',
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

	/**
	 * Output fields to enter licence key for each plus plugin.
	 */
	public function license_keys_field_output() {
		foreach ( $this->licences_service->get_plus_plugins() as $one_plus_plugin ) {
			$this->output_licence_key_fields_for_plugin( $one_plus_plugin );
		}

		/*
		"This license key has reached the activation limit."
		står kvar även om man tar bort sajten i lemon squeesys admin.
		*/
	}

	/**
	 * Output fields to enter licence key and to activate, deactiave, and show info, for one plus plugin.
	 *
	 * @param Plus_Plugin $plus_plugin One plus plugin.
	 */
	private function output_licence_key_fields_for_plugin( $plus_plugin ) {
		$license_key = $plus_plugin->get_license_key();

		$form_post_url = add_query_arg(
			[
				'selected-sub-tab' => 'general_settings_subtab_licenses',
			],
			menu_page_url( $this->simple_history::SETTINGS_MENU_SLUG, 0 )
		);

		// Check for posted form for this plugin
		$form_success_message = null;
		$form_error_message = null;
		$nonce_valid = wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'sh-plugin-keys' ) !== false;
		if ( $nonce_valid && isset( $_POST['plugin_slug'] ) && $_POST['plugin_slug'] === $plus_plugin->slug ) {
			$action_activate = boolval( $_POST['activate'] ?? false );
			$action_deactivate = boolval( $_POST['deactivate'] ?? false );

			$new_licence_key = trim( sanitize_text_field( wp_unslash( $_POST['licence_key'] ?? '' ) ) );

			if ( $action_activate ) {
				$activation_result = $plus_plugin->activate_license( $new_licence_key );
				if ( $activation_result['success'] === true ) {
					$form_success_message = 'License activated! 🎉';
				} else {
					$form_error_message = sprintf(
						'Could not activate license. 😢 Error info: <code>%s</code>',
						esc_html( $activation_result['message'] )
					);
				}

				// $licence_message = $plus_plugin->get_license_message( $license_key );
			} elseif ( $action_deactivate ) {
				$deactivate_result = $plus_plugin->deactivate_license();
				if ( $deactivate_result === true ) {
					$form_success_message = 'License deactivated. 👋';
				} else {
					$form_error_message = 'Could not deactivate license.';
				}
			}
		}

		// Get key and message again, because it they have changed.
		$licence_message = $plus_plugin->get_license_message();
		$license_key = $plus_plugin->get_license_key();

		?>
		<div style="margin-bottom: 2em;">
			<form method="post" action="<?php echo esc_url( $form_post_url ); ?>">
				<?php wp_nonce_field( 'sh-plugin-keys' ); ?>
				<input type="hidden" name="plugin_slug" value="<?php echo esc_attr( $plus_plugin->slug ); ?>" />

				<p style="font-weight: bold; font-size: 1.25em;font-weight: 400;">
					<?php echo esc_html( $plus_plugin->name ); ?>
				</p>

				<p class="description">
					<?php echo 'Version ' . esc_html( $plus_plugin->version ); ?>
				</p>

				<p>
					<input <?php wp_readonly( $licence_message['key_activated'] && ! empty( $license_key ) ); ?> type="text" class="regular-text" name="licence_key" value="<?php echo esc_attr( $license_key ); ?>" />
				</p>

				<?php
				// Show Activate button if no key is set already.
				if ( $licence_message['key_activated'] !== true || empty( $license_key ) ) {
					?>
					<p><?php esc_html_e( 'No license found.', 'simple-history' ); ?></p>
					<p>	
						<span class="sh-mr-1">
							<?php submit_button( 'Activate', 'secondary', 'activate', false ); ?>
						</span>
					</p>
					<?php
				}

				// Show deactivate key button if key is activated.
				if ( $licence_message['key_activated'] === true ) {
					?>
					<p class="description">
						<?php
						echo wp_kses(
							__( 'License key is <strong>active</strong>. ', 'simple-history' ),
							[
								'strong' => [],
							]
						);
						?>
					</p>

					<p>
						<span class="sh-mr-1">
							<?php submit_button( 'Deactivate', 'secondary', 'deactivate', false ); ?>
						</span>
					</p>
					<?php
				}

				if ( $form_success_message ) {
					printf(
						'<div class="notice notice-large notice-alt notice-success"><p>%s</p></div>',
						wp_kses(
							$form_success_message,
							[
								'code' => [],
							]
						)
					);
				}

				if ( $form_error_message ) {
					printf(
						'<div class="notice notice-large notice-alt notice-error"><p>%s</p></div>',
						wp_kses(
							$form_error_message,
							[
								'code' => [],
							]
						)
					);
				}

				// sh_d( 'debug:', '$license_message', $licence_message );
				?>
			</form>
		</div>
		<?php
	}

	public function activated_sites_settings_output() {
		?>
		<p>
			Visit the
			<a href="https://simple-history.lemonsqueezy.com/billing" class="sh-ExternalLink" target="_blank">
				<?php esc_html_e( 'Customer Portal', 'simple-history' ); ?>
			</a>
			 to view and manage your licences, sites, and billing for your add-ons.
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
			<?php
			// Prints out all settings sections added to a particular settings page.
			do_settings_sections( self::SETTINGS_PAGE_SLUG );

			// Output nonce, action, and option_page fields.
			settings_fields( self::SETTINGS_OPTION_GROUP );
			?>
		</div>
		<?php
	}
}
