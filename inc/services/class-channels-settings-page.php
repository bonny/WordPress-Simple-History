<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Channels\Channels_Manager;
use Simple_History\Menu_Page;

/**
 * Settings page for channels, where users can configure
 * log forwarding to external systems.
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
			'simple_history_settings_section_tab_integrations',
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
		$channel_slug = $channel->get_slug();
		$option_name  = $channel->get_settings_option_name();
		$section_id   = 'simple_history_channel_' . $channel_slug;

		// Register the settings option for this channel.
		register_setting(
			self::SETTINGS_OPTION_GROUP,
			$option_name,
			[
				'sanitize_callback' => [ $channel, 'sanitize_settings' ],
			]
		);

		// Add a settings section for this channel (wrapped in sh-SettingsCard by helper).
		Helpers::add_settings_section(
			$section_id,
			$channel->get_name(),
			function () use ( $channel ) {
				$this->render_channel_section_intro( $channel );
			},
			self::SETTINGS_PAGE_SLUG,
			[
				'callback_last' => [ $channel, 'settings_output_after_fields' ],
			]
		);

		// Let the channel add its own settings fields.
		$channel->add_settings_fields( self::SETTINGS_PAGE_SLUG, $section_id );
	}

	/**
	 * Output for the main settings section intro.
	 */
	public function settings_section_output() {
		?>
		<p><?php esc_html_e( 'Enable integrations to automatically forward events to external systems like log files, Slack, email, and more.', 'simple-history' ); ?></p>
		<?php
	}

	/**
	 * Render the intro for a channel's settings section.
	 *
	 * @param \Simple_History\Channels\Interfaces\Channel_Interface $channel The channel.
	 */
	private function render_channel_section_intro( $channel ) {
		?>
		<p class="description"><?php echo esc_html( $channel->get_description() ); ?></p>
		<?php
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
