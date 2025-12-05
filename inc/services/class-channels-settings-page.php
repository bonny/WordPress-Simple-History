<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Channels\Channels_Manager;
use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;
use Simple_History\Services\Setup_Settings_Page;

/**
 * Settings page for integrations, where users can configure
 * log forwarding and external integrations.
 *
 * @since 4.4.0
 */
class Channels_Settings_Page extends Service {
	/**
	 * The channels manager instance.
	 *
	 * @var Channels_Manager|null
	 */
	private ?Channels_Manager $channels_manager = null;

	private const SETTINGS_SECTION_ID   = 'simple_history_settings_section_tab_integrations';
	private const SETTINGS_PAGE_SLUG    = 'simple_history_settings_menu_slug_tab_integrations';
	private const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_tab_integrations';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Get the channels manager from the channels service.
		$channels_service = $this->simple_history->get_service( Channels_Service::class );

		if ( ! $channels_service instanceof Channels_Service ) {
			return;
		}

		$this->channels_manager = $channels_service->get_channels_manager();

		if ( ! $this->channels_manager ) {
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
			->set_callback( [ $this, 'settings_output_channels' ] )
			->set_order( 40 ) // After general settings but before licenses.
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Register settings and add settings fields.
	 */
	public function register_and_add_settings() {
		// Add main integrations settings section with intro text.
		Helpers::add_settings_section(
			self::SETTINGS_SECTION_ID,
			[ __( 'Log Forwarding & Integrations', 'simple-history' ), 'extension' ],
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add a settings section for each channel.
		foreach ( $this->channels_manager->get_channels() as $channel ) {
			$this->add_channel_settings_section( $channel );
		}
	}

	/**
	 * Add a settings section for a specific channel.
	 *
	 * @param \Simple_History\Channels\Interfaces\Channel_Interface $channel The channel.
	 */
	private function add_channel_settings_section( $channel ) {
		$settings_fields = $channel->get_settings_fields();

		if ( empty( $settings_fields ) ) {
			return;
		}

		$channel_slug = $channel->get_slug();
		$option_name  = $channel->get_settings_option_name();

		// Register the settings option for this channel.
		register_setting(
			self::SETTINGS_OPTION_GROUP,
			$option_name,
			[
				'sanitize_callback' => [ $this, 'sanitize_channel_settings' ],
			]
		);

		// Add a settings section for this channel (wrapped in sh-SettingsCard by helper).
		Helpers::add_settings_section(
			'simple_history_channel_' . $channel_slug,
			$channel->get_name(),
			[ $this, 'render_channel_section' ],
			self::SETTINGS_PAGE_SLUG
		);
	}

	/**
	 * Output for the main settings section intro.
	 */
	public function settings_section_output() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Enable integrations to automatically forward events to external systems like log files, Slack, email, and more.', 'simple-history' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render a channel's settings section content.
	 *
	 * @param array $args Section arguments containing 'id', 'title', 'callback'.
	 */
	public function render_channel_section( $args ) {
		// Extract channel slug from section ID.
		$channel_slug = str_replace( 'simple_history_channel_', '', $args['id'] );
		$channel      = $this->channels_manager->get_channel( $channel_slug );

		if ( ! $channel ) {
			return;
		}

		$settings_fields = $channel->get_settings_fields();
		$option_name     = $channel->get_settings_option_name();
		?>
		<p class="sh-Channel-description"><?php echo esc_html( $channel->get_description() ); ?></p>

		<?php
		// Show channel-specific info before fields if available.
		$info_before_html = $channel->get_settings_info_before_fields_html();
		if ( ! empty( $info_before_html ) ) {
			echo wp_kses_post( $info_before_html );
		}
		?>

		<?php
		foreach ( $settings_fields as $field ) {
			/** @var string $field_name */
			$field_name = $field['name'];
			/** @var mixed $field_value */
			$field_value = $channel->get_setting( $field_name, $field['default'] ?? '' );
			?>
			<div class="sh-Channel-field">
				<?php $this->render_field( $field, $field_value, $option_name ); ?>
			</div>
			<?php
		}
		?>

		<?php
		// Show channel-specific info after fields if available.
		$info_after_html = $channel->get_settings_info_after_fields_html();
		if ( ! empty( $info_after_html ) ) {
			echo wp_kses_post( $info_after_html );
		}
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
		$field_id   = $option_name . '_' . $field['name'];

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
				<?php
				if ( ! empty( $field['title'] ) ) {
					?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
					<?php
				}
				?>
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
				<?php
				if ( ! empty( $field['title'] ) ) {
					?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
					<?php
				}
				?>
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
				<?php
				if ( ! empty( $field['title'] ) ) {
					?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
					<?php
				}
				?>
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
				<?php
				if ( ! empty( $field['title'] ) ) {
					?>
					<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field['title'] ); ?></label>
					<?php
				}
				?>
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
	 * Sanitize channel settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_channel_settings( $input ) {
		// This is a basic sanitization. Each channel should handle its own validation
		// through the save_settings method.
		return is_array( $input ) ? $input : [];
	}

	/**
	 * Output for the integrations settings page.
	 */
	public function settings_output_channels() {
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