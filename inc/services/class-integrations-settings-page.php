<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Integrations\Integrations_Manager;
use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;
use Simple_History\Services\Setup_Settings_Page;

/**
 * Settings page for integrations, where users can configure
 * log forwarding and external integrations.
 *
 * @since 4.4.0
 */
class Integrations_Settings_Page extends Service {
	/**
	 * The integrations manager instance.
	 *
	 * @var Integrations_Manager|null
	 */
	private ?Integrations_Manager $integrations_manager = null;

	private const SETTINGS_SECTION_ID = 'simple_history_settings_section_tab_integrations';
	private const SETTINGS_PAGE_SLUG = 'simple_history_settings_menu_slug_tab_integrations';
	private const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_tab_integrations';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Get the integrations manager from the integrations service.
		$integrations_service = $this->simple_history->get_service( Integrations_Service::class );

		if ( ! $integrations_service instanceof Integrations_Service ) {
			return;
		}

		$this->integrations_manager = $integrations_service->get_integrations_manager();

		if ( ! $this->integrations_manager ) {
			return;
		}

		// Add settings page after init.
		add_action( 'init', [ $this, 'on_init_add_settings' ], 20 );

		// Add menu page.
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Add settings after plugins have loaded.
	 */
	public function on_init_add_settings() {
		add_action( 'admin_menu', [ $this, 'register_and_add_settings' ] );
	}

	/**
	 * Add integrations settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		( new Menu_Page() )
			->set_page_title( __( 'Integrations', 'simple-history' ) )
			->set_menu_title( __( 'Integrations', 'simple-history' ) )
			->set_menu_slug( 'general_settings_subtab_integrations' )
			->set_callback( [ $this, 'settings_output_integrations' ] )
			->set_order( 40 ) // After general settings but before licenses.
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Register settings and add settings fields.
	 */
	public function register_and_add_settings() {
		// Add integrations settings section.
		Helpers::add_settings_section(
			self::SETTINGS_SECTION_ID,
			[ __( 'Log Forwarding & Integrations', 'simple-history' ), 'extension' ],
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add settings fields for each integration.
		foreach ( $this->integrations_manager->get_integrations() as $integration ) {
			$this->add_integration_settings_fields( $integration );
		}
	}

	/**
	 * Add settings fields for a specific integration.
	 *
	 * @param \Simple_History\Integrations\Interfaces\Integration_Interface $integration The integration.
	 */
	private function add_integration_settings_fields( $integration ) {
		$integration_slug = $integration->get_slug();
		$settings_fields = $integration->get_settings_fields();

		if ( empty( $settings_fields ) ) {
			return;
		}

		// Register the settings option for this integration.
		$option_name = 'simple_history_integration_' . $integration_slug;
		register_setting(
			self::SETTINGS_OPTION_GROUP,
			$option_name,
			[
				'sanitize_callback' => [ $this, 'sanitize_integration_settings' ],
			]
		);

		// Add a field group for this integration.
		add_settings_field(
			'integration_' . $integration_slug,
			Helpers::get_settings_field_title_output( $integration->get_name(), 'extension' ),
			[ $this, 'render_integration_settings' ],
			self::SETTINGS_PAGE_SLUG,
			self::SETTINGS_SECTION_ID,
			[
				'integration' => $integration,
			]
		);
	}

	/**
	 * Output for the settings section.
	 */
	public function settings_section_output() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Configure where Simple History sends your logs and events. Enable integrations to automatically forward events to external systems like files, Slack, email, and more.', 'simple-history' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render settings fields for a specific integration.
	 *
	 * @param array $args Arguments containing the integration instance.
	 */
	public function render_integration_settings( $args ) {
		$integration = $args['integration'];
		$settings = $integration->get_settings();
		$settings_fields = $integration->get_settings_fields();
		$option_name = 'simple_history_integration_' . $integration->get_slug();

		?>
		<div class="sh-Integration-settings">
			<p class="sh-Integration-description"><?php echo esc_html( $integration->get_description() ); ?></p>

			<?php
			// Show integration-specific info before fields if available.
			$info_before_html = $integration->get_settings_info_before_fields_html();
			if ( ! empty( $info_before_html ) ) {
				echo wp_kses_post( $info_before_html );
			}
			?>

			<?php
			foreach ( $settings_fields as $field ) {
				?>
				<div class="sh-Integration-field">
					<?php $this->render_field( $field, $settings[ $field['name'] ] ?? '', $option_name ); ?>
				</div>
				<?php
			}
			?>

			<?php
			// Show integration-specific info after fields if available.
			$info_after_html = $integration->get_settings_info_after_fields_html();
			if ( ! empty( $info_after_html ) ) {
				echo wp_kses_post( $info_after_html );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array  $field Field configuration.
	 * @param mixed  $value Current field value.
	 * @param string $option_name Option name prefix.
	 */
	private function render_field( $field, $value, $option_name ) {
		$field_name = $option_name . '[' . $field['name'] . ']';
		$field_id = $option_name . '_' . $field['name'];

		switch ( $field['type'] ) {
			case 'checkbox':
				?>
				<label for="<?php echo esc_attr( $field_id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="1" <?php checked( $value, true ); ?> />
					<?php echo esc_html( $field['description'] ?? '' ); ?>
				</label>
				<?php
				break;

			case 'text':
			case 'url':
			case 'email':
				?>
				<?php if ( ! empty( $field['title'] ) ) : ?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
				<?php endif; ?>
				<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>" />
				<?php
				if ( ! empty( $field['description'] ) ) {
					?>
					<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php
				}
				?>
				<?php
				break;

			case 'number':
				?>
				<?php if ( ! empty( $field['title'] ) ) : ?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
				<?php endif; ?>
				<input type="number" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $field['min'] ?? '' ); ?>" max="<?php echo esc_attr( $field['max'] ?? '' ); ?>" />
				<?php
				if ( ! empty( $field['description'] ) ) {
					?>
					<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php
				}
				?>
				<?php
				break;

			case 'select':
				?>
				<?php if ( ! empty( $field['title'] ) ) : ?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
				<?php endif; ?>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
					<?php
					foreach ( $field['options'] as $option_value => $option_label ) {
						?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
						<?php
					}
					?>
				</select>
				<?php
				if ( ! empty( $field['description'] ) ) {
					?>
					<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php
				}
				?>
				<?php
				break;

			case 'textarea':
				?>
				<?php if ( ! empty( $field['title'] ) ) : ?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
				<?php endif; ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				if ( ! empty( $field['description'] ) ) {
					?>
					<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php
				}
				?>
				<?php
				break;
		}
	}

	/**
	 * Sanitize integration settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_integration_settings( $input ) {
		// This is a basic sanitization. Each integration should handle its own validation
		// through the save_settings method.
		return is_array( $input ) ? $input : [];
	}

	/**
	 * Output for the integrations settings page.
	 */
	public function settings_output_integrations() {
		?>
		<div class="wrap sh-Page-content">
			<form method="post" action="options.php">
				<?php
				// Prints out all settings sections added to a particular settings page.
				do_settings_sections( self::SETTINGS_PAGE_SLUG );

				// Output nonce, action, and option_page fields.
				settings_fields( self::SETTINGS_OPTION_GROUP );

				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}