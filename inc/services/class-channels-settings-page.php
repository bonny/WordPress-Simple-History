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
			->set_page_title( __( 'Log Forwarding', 'simple-history' ) )
			->set_menu_title( __( 'Log Forwarding', 'simple-history' ) )
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
		// Use WordPress core function directly without card wrapper for the intro section.
		add_settings_section(
			'simple_history_settings_section_tab_integrations',
			Helpers::get_settings_section_title_output( __( 'Log Forwarding', 'simple-history' ), 'extension' ),
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add a settings section for each channel.
		foreach ( $this->channels_manager->get_channels() as $channel ) {
			$this->add_channel_settings_section( $channel );
		}

		// Add premium channel teasers when premium is not active.
		$this->add_premium_channel_teasers();
	}

	/**
	 * Add teaser sections for premium-only channels.
	 *
	 * Shows promotional cards for premium channels when the premium add-on is not active.
	 */
	private function add_premium_channel_teasers() {
		// Skip if premium is active (the real channels will be shown instead).
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		// Premium badge HTML for section titles.
		$premium_badge = '<span class="sh-PremiumFeatureBadge">' . esc_html__( 'Premium', 'simple-history' ) . '</span>';

		// Add Syslog channel teaser.
		// Title format: [title, icon-slug, html-id, suffix].
		Helpers::add_settings_section(
			'simple_history_channel_syslog_teaser',
			[ __( 'Syslog', 'simple-history' ), null, '', $premium_badge ],
			[ $this, 'render_syslog_teaser' ],
			self::SETTINGS_PAGE_SLUG
		);

		// Add External Database channel teaser.
		// Title format: [title, icon-slug, html-id, suffix].
		Helpers::add_settings_section(
			'simple_history_channel_external_database_teaser',
			[ __( 'External Database', 'simple-history' ), null, '', $premium_badge ],
			[ $this, 'render_external_database_teaser' ],
			self::SETTINGS_PAGE_SLUG
		);
	}

	/**
	 * Render the Syslog channel teaser content.
	 *
	 * Shows a disabled form that mimics the real Syslog channel settings,
	 * creating FOMO and demonstrating the value of the premium feature.
	 */
	public function render_syslog_teaser() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Automatically forward all events to system syslog or remote rsyslog servers for centralized logging, SIEM integration, or compliance requirements.', 'simple-history' ); ?></p>
		</div>

		<div class="sh-PremiumTeaser-disabledForm">
			<table class="form-table" role="presentation">
				<tbody>
					<!-- Enabled checkbox -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Enabled', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" disabled />
								<?php esc_html_e( 'Enable Syslog forwarding', 'simple-history' ); ?>
							</label>
						</td>
					</tr>

					<!-- Mode -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Mode', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<select disabled>
								<option selected><?php esc_html_e( 'Local syslog (PHP syslog function)', 'simple-history' ); ?></option>
								<option><?php esc_html_e( 'Remote syslog via UDP', 'simple-history' ); ?></option>
								<option><?php esc_html_e( 'Remote syslog via TCP', 'simple-history' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Local syslog writes to the system log. Remote syslog sends to a rsyslog server.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Facility -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Facility', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<select disabled>
								<option selected>user - User-level messages</option>
								<option>local0 - Local use 0</option>
								<option>daemon - System daemons</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Syslog facility determines how the system categorizes the log messages.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Identity -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Identity', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" value="SimpleHistory" disabled />
							<p class="description">
								<?php esc_html_e( 'Application name shown in syslog entries.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Remote Host -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Remote Host', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" placeholder="syslog.example.com" disabled />
							<p class="description">
								<?php esc_html_e( 'Hostname or IP address of remote rsyslog server. Only used for remote modes.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Remote Port -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Remote Port', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="number" class="small-text" value="514" disabled />
							<p class="description">
								<?php esc_html_e( 'Default: 514', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Connection Timeout -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Connection Timeout', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="number" class="small-text" value="5" disabled />
							<?php esc_html_e( 'seconds', 'simple-history' ); ?>
							<p class="description">
								<?php esc_html_e( 'Timeout for remote connections.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php
		echo wp_kses_post(
			Helpers::get_premium_feature_teaser(
				__( 'Unlock Syslog Integration', 'simple-history' ),
				[
					__( 'Local syslog via PHP syslog() function', 'simple-history' ),
					__( 'Remote rsyslog via UDP or TCP', 'simple-history' ),
					__( 'RFC 5424 format for SIEM integration', 'simple-history' ),
					__( 'Test connection button to verify setup', 'simple-history' ),
					__( 'Auto-disable on repeated failures', 'simple-history' ),
				],
				'syslog_channel_teaser',
				__( 'Unlock Syslog with Premium', 'simple-history' )
			)
		);
	}

	/**
	 * Render the External Database channel teaser content.
	 *
	 * Shows a disabled form that mimics the real External Database channel settings,
	 * creating FOMO and demonstrating the value of the premium feature.
	 */
	public function render_external_database_teaser() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Forward events to an external MySQL/MariaDB database for compliance, security audits, and off-site backup. Keep your audit logs separate from WordPress for enhanced security.', 'simple-history' ); ?></p>
		</div>

		<div class="sh-PremiumTeaser-disabledForm">
			<table class="form-table" role="presentation">
				<tbody>
					<!-- Enabled checkbox -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Enabled', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" disabled />
								<?php esc_html_e( 'Enable External Database forwarding', 'simple-history' ); ?>
							</label>
						</td>
					</tr>

					<!-- Database Host -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database Host', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" placeholder="localhost or 192.168.1.100" disabled />
							<p class="description">
								<?php esc_html_e( 'Hostname or IP address of the external MySQL/MariaDB server.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Database Port -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database Port', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="number" class="small-text" value="3306" disabled />
							<p class="description">
								<?php esc_html_e( 'Default: 3306', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Database Name -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database Name', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" placeholder="audit_logs" disabled />
							<p class="description">
								<?php esc_html_e( 'Name of the database to store events in.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Database User -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database User', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" disabled />
							<p class="description">
								<?php esc_html_e( 'Database username with INSERT and CREATE TABLE privileges.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Database Password -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database Password', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="password" class="regular-text" disabled />
							<p class="description">
								<?php esc_html_e( 'Database password. Will be encrypted before storage.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Table Name -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Table Name', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<input type="text" class="regular-text" value="simple_history_events" disabled />
							<p class="description">
								<?php esc_html_e( 'Table will be created automatically if it does not exist.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php
		echo wp_kses_post(
			Helpers::get_premium_feature_teaser(
				__( 'Unlock External Database Integration', 'simple-history' ),
				[
					__( 'Store audit logs in a separate MySQL/MariaDB database', 'simple-history' ),
					__( 'Off-site backup for compliance (SOC 2, GDPR, HIPAA)', 'simple-history' ),
					__( 'Encrypted password storage for security', 'simple-history' ),
					__( 'Automatic table creation with optimized indexes', 'simple-history' ),
					__( 'Test connection button to verify setup', 'simple-history' ),
				],
				'external_database_channel_teaser',
				__( 'Unlock External Database with Premium', 'simple-history' )
			)
		);
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
		<div class="sh-SettingsSectionIntroduction">
			<p><?php esc_html_e( 'Send a copy of all events to log files or syslog servers.', 'simple-history' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the intro for a channel's settings section.
	 *
	 * @param \Simple_History\Channels\Interfaces\Channel_Interface $channel The channel.
	 */
	private function render_channel_section_intro( $channel ) {
		if ( ! empty( $channel->get_description() ) ) {
			?>
			<div class="sh-SettingsSectionIntroduction">
				<p><?php echo esc_html( $channel->get_description() ); ?></p>
			</div>
			<?php
		}
		$channel->settings_output_intro();
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
