<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Services\AddOns_Licences;
use Simple_History\AddOn_Plugin;
use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;
use Simple_History\Services\Setup_Settings_Page;

/**
 * Settings page for licences, where users can enter their license keys
 * for installed add-ons.
 */
class Licences_Settings_Page extends Service {
	/** @var AddOns_Licences $licences_service */
	private $licences_service;

	private const SETTINGS_SECTION_ID     = 'simple_history_settings_section_tab_licenses';
	private const SETTINGS_PAGE_SLUG      = 'simple_history_settings_menu_slug_tab_licenses';
	private const SETTINGS_OPTION_GROUP   = 'simple_history_settings_group_tab_licenses';
	private const OPTION_NAME_LICENSE_KEY = 'shp_license_key';
	private const OPTION_LICENSE_MESSAGE  = 'example_plugin_license_message';

	/** @inheritdoc */
	public function loaded() {
		$licences_service = $this->simple_history->get_service( AddOns_Licences::class );

		// Bail if licences service not found.
		if ( ! $licences_service || ! $licences_service instanceof AddOns_Licences ) {
			return;
		}

		$this->licences_service = $licences_service;

		// Add settings tab.
		// Run on prio 20 so it runs after add ons have done their loaded actions.
		add_action(
			'init',
			[ $this, 'on_init_add_settings' ],
			20
		);

		// Add menu page.
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Add settings tab after plugins has loaded.
	 */
	public function on_init_add_settings() {
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
	public function add_settings_menu_tab() {
		// Add settings page using new Menu Manager and Menu Page classes.
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exists (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		( new Menu_Page() )
			->set_page_title( __( 'Licences', 'simple-history' ) )
			->set_menu_title( __( 'Licences', 'simple-history' ) )
			->set_menu_slug( 'general_settings_subtab_licenses' )
			->set_callback( [ $this, 'settings_output_licenses' ] )
			->set_order( 50 ) // After general settings and premium settings.
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Register settings and add settings fields.
	 */
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
			Helpers::get_settings_field_title_output( __( 'Sites and Billing', 'simple-history' ), 'web' ),
			[ $this, 'activated_sites_settings_output' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID
		);
	}

	/**
	 * Output for the settings section.
	 */
	public function settings_section_output() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Enter your license key(s) to activate and retrieve updates for your add-on plugins.', 'simple-history' ); ?></p>

			<p>
				<?php
				$link_url  = Helpers::get_tracking_url( 'https://simple-history.com/add-ons', 'premium_licences_addons' );
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
							'href'   => [],
							'class'  => [],
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
	 *
	 * The user only enter the license key on the main site?
	 * Is it because it's one the main site that the license key is used to update the plugin?
	 * If license was entered on each subsite, which key would the main site actually use to update the plugin?
	 *
	 * Problem: A user has only the WooCommerce plugin and the Simple History WooCommerce logger addon installed on a subsite,
	 * how do they enter the key?
	 */
	public function license_keys_field_output() {

		// If no add-ons.
		if ( ! $this->licences_service->has_add_ons() ) {
			?>
			<p><?php esc_html_e( 'No add-ons activated.', 'simple-history' ); ?></p>
			
			<p>
				<?php esc_html_e( 'If you have bought an add-on then install and activate the add-on and then return here to enter the license key.', 'simple-history' ); ?>
			</p>

			<p>
				<?php
				$install_help_url  = Helpers::get_tracking_url( 'https://simple-history.com/support/add-ons/how-to-install/', 'docs_licences_install' );
				$install_help_text = __( 'How to install an add-on', 'simple-history' );
				echo wp_kses(
					sprintf(
						'<a href="%1$s" class="sh-ExternalLink" target="_blank">%2$s</a>',
						esc_url( $install_help_url ),
						esc_html( $install_help_text )
					),
					[
						'a' => [
							'href'   => [],
							'class'  => [],
							'target' => [],
						],
					]
				);
				?>
			</p>
			<?php

			return;
		}

		if ( is_main_site() ) {
			foreach ( $this->licences_service->get_addon_plugins() as $one_plus_plugin ) {
				$this->output_licence_key_fields_for_plugin( $one_plus_plugin );
			}
		} else {
			printf(
				'<p>%s</p>',
				esc_html__( 'On multisite installations you enter the licence keys on the main site.', 'simple-history' )
			);
		}
	}

	/**
	 * Output fields to enter licence key and to activate, deactiave, and show info, for one plus plugin.
	 *
	 * @param AddOn_Plugin $plus_plugin One plus plugin.
	 */
	private function output_licence_key_fields_for_plugin( $plus_plugin ) {
		$license_key   = $plus_plugin->get_license_key();
		$form_post_url = Menu_Manager::get_admin_url_by_slug( 'general_settings_subtab_licenses' );

		// Check for posted form for this plugin.
		$form_success_message = null;
		$form_error_message   = null;
		$nonce_valid          = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'sh-plugin-keys' ) !== false;

		if ( $nonce_valid && isset( $_POST['plugin_slug'] ) && $_POST['plugin_slug'] === $plus_plugin->slug ) {
			$action_activate   = boolval( $_POST['activate'] ?? false );
			$action_deactivate = boolval( $_POST['deactivate'] ?? false );
			$new_licence_key   = trim( sanitize_text_field( wp_unslash( $_POST['licence_key'] ?? '' ) ) );

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
			} elseif ( $action_deactivate ) {
				$deactivate_result = $plus_plugin->deactivate_license();
				if ( $deactivate_result === true ) {
					$form_success_message = 'License deactivated. 👋';
				} else {
					$form_error_message = 'Could not deactivate license.';
				}
			}
		}

		// Get key and message again, because they may have changed.
		$licence_message = $plus_plugin->get_license_message();
		$license_key     = $plus_plugin->get_license_key();

		?>
		<div class="sh-LicencesPage-plugin">
			<form method="post" action="<?php echo esc_url( $form_post_url ); ?>">
				<?php wp_nonce_field( 'sh-plugin-keys' ); ?>
				<input type="hidden" name="plugin_slug" value="<?php echo esc_attr( $plus_plugin->slug ); ?>" />

				<p class="sh-LicencesPage-plugin-name">
					<?php echo esc_html( $plus_plugin->name ); ?>
				</p>

				<p class="sh-LicencesPage-plugin-version">
					<?php echo 'Version ' . esc_html( $plus_plugin->version ); ?>
				</p>

				<p>
					<input 
						type="text" class="regular-text" name="licence_key" 
						value="<?php echo esc_attr( $license_key ); ?>" 
						placeholder="<?php esc_attr_e( 'Enter license key...', 'simple-history' ); ?>"
					/>
				</p>
	
				<?php
				// Show deactivate key button if key is activated.
				if ( $licence_message['key_activated'] === true ) {
					?>
					<p class="sh-LicencesPage-plugin-active">
						<?php
						echo wp_kses(
							__( 'License key is <strong>active</strong>. ', 'simple-history' ),
							[
								'strong' => [],
							]
						);
						?>
					</p>
					<?php
				}
				?>

				<p>
					<span class="sh-mr-1">
						<?php submit_button( 'Activate', 'secondary', 'activate', false ); ?>
					</span>

					<span class="sh-mr-1">
						<?php submit_button( 'Deactivate', 'secondary', 'deactivate', false ); ?>
					</span>
				</p>
				
				<?php
				// Show Activate button if no key is set already.
				if ( $licence_message['key_activated'] !== true || empty( $license_key ) ) {
					?>
					<p><?php esc_html_e( 'No license found.', 'simple-history' ); ?></p>
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

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					?>
					<details style="margin-top: 1em;">
						<summary>Licence message (debug)</summary>
						<pre>
						<?php 
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							echo esc_html( print_r( $licence_message, true ) ); 
						?>
						</pre>
						<br />Licence key: <code><?php echo esc_html( $license_key ); ?></code>
					</details>
					<?php
				}
				?>

			</form>
		</div>
		<?php
	}

	/**
	 * Output for the tab.
	 */
	public function activated_sites_settings_output() {
		$link_my_orders_start = '<a href="https://app.lemonsqueezy.com/my-orders/" class="sh-ExternalLink" target="_blank">';
		$link_my_orders_end   = '</a>';

		$link_billing_start = '<a href="https://simple-history.lemonsqueezy.com/billing" class="sh-ExternalLink" target="_blank">';
		$link_billing_end   = '</a>';

		?>
		<p>
			<?php
			printf(
				/* translators: 1: link start tag, 2: link end tag */
				esc_html__( 'Manage licences and download your add-ons at the %1$sMy orders%2$s page.', 'simple-history' ),
				$link_my_orders_start, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$link_my_orders_end // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			?>
		</p>

		<p>
			<?php
			printf(
				/* translators: 1: link start tag, 2: link end tag */
				esc_html__( 'Manage subscriptions and billing for your add-ons at the %1$sCustomer portal%2$s.', 'simple-history' ),
				$link_billing_start, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$link_billing_end // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			?>
		</p>
		<?php
	}

	/**
	 * Output for the tab.
	 */
	public function settings_output_licenses() {
		// Output setting sections.
		?>
		<div class="wrap sh-Page-content">
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
