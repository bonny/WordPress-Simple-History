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

	private const SETTINGS_PAGE_SLUG   = 'simple_history_settings_menu_slug_log_forwarding';
	public const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_log_forwarding';

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

		// Build menu title with Beta badge.
		$menu_title = __( 'Log Forwarding', 'simple-history' )
			. ' <span class="sh-Badge sh-Badge--new">' . esc_html__( 'Beta', 'simple-history' ) . '</span>';

		( new Menu_Page() )
			->set_page_title( __( 'Log Forwarding', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( 'general_settings_subtab_log_forwarding' )
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
		$premium_badge = '<span class="sh-Badge sh-Badge--premium">' . esc_html__( 'Premium', 'simple-history' ) . '</span>';

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
			[ __( 'Remote Database', 'simple-history' ), null, '', $premium_badge ],
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
			<p><?php esc_html_e( 'Stream events to syslog for centralized monitoring and SIEM integration.', 'simple-history' ); ?></p>
		</div>

		<div class="sh-PremiumTeaser-disabledForm" inert>
			<table class="form-table" role="presentation">
				<tbody>
					<!-- Status -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Status', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" />
								<?php esc_html_e( 'Enable Syslog forwarding', 'simple-history' ); ?>
							</label>
						</td>
					</tr>

					<!-- Mode (radio buttons) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Mode', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<fieldset class="sh-RadioOptions sh-RadioOptions--disabled">
								<label class="sh-RadioOption">
									<input type="radio" checked />
									<?php esc_html_e( 'Local syslog', 'simple-history' ); ?>
									<span class="sh-RadioOptionDescription description">
										<?php esc_html_e( 'Writes to this server\'s system log', 'simple-history' ); ?>
									</span>
								</label>
								<label class="sh-RadioOption">
									<input type="radio" />
									<?php esc_html_e( 'Remote syslog (UDP)', 'simple-history' ); ?>
									<span class="sh-RadioOptionDescription description">
										<?php esc_html_e( 'Send to a central log server – fastest, no delivery confirmation', 'simple-history' ); ?>
									</span>
								</label>
								<label class="sh-RadioOption">
									<input type="radio" />
									<?php esc_html_e( 'Remote syslog (TCP)', 'simple-history' ); ?>
									<span class="sh-RadioOptionDescription description">
										<?php esc_html_e( 'Send to a central log server – reliable, confirms delivery', 'simple-history' ); ?>
									</span>
								</label>
							</fieldset>
						</td>
					</tr>

					<!-- Server (combined: host, port, timeout) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Server', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<div class="sh-InlineFields">
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Address', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" placeholder="syslog.example.com" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Port', 'simple-history' ); ?></span>
									<input type="number" class="small-text" value="514" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Timeout', 'simple-history' ); ?></span>
									<span class="sh-InlineFieldInputWithSuffix">
										<input type="number" class="small-text" value="5" />
										<span class="sh-InlineFieldSuffix"><?php esc_html_e( 'sec', 'simple-history' ); ?></span>
									</span>
								</label>
							</div>
							<p class="description">
								<?php esc_html_e( 'Required for TCP and UDP transport modes.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Advanced (combined: facility, identity) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Advanced', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<div class="sh-InlineFields">
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Facility', 'simple-history' ); ?></span>
									<select>
										<option selected>user - User-level messages</option>
									</select>
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Identity', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" value="SimpleHistory" />
								</label>
							</div>
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
			<p><?php esc_html_e( 'Store events in a separate MySQL/MariaDB database for tamper-proof auditing and compliance.', 'simple-history' ); ?></p>
		</div>

		<div class="sh-PremiumTeaser-disabledForm" inert>
			<table class="form-table" role="presentation">
				<tbody>
					<!-- Enabled checkbox -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Status', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<label>
								<input type="checkbox" />
								<?php esc_html_e( 'Enable External Database forwarding', 'simple-history' ); ?>
							</label>
						</td>
					</tr>

					<!-- Server (combined: host, port, timeout) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Server', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<div class="sh-InlineFields">
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Address', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" placeholder="example.com" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Port', 'simple-history' ); ?></span>
									<input type="number" class="small-text" value="3306" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Timeout', 'simple-history' ); ?></span>
									<span class="sh-InlineFieldInputWithSuffix">
										<input type="number" class="small-text" value="5" />
										<span class="sh-InlineFieldSuffix"><?php esc_html_e( 'sec', 'simple-history' ); ?></span>
									</span>
								</label>
							</div>
							<p class="description">
								<?php esc_html_e( 'Host, port, and connection timeout for the external server.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Auth (combined: username, password) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Auth', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<div class="sh-InlineFields">
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Username', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Password', 'simple-history' ); ?></span>
									<input type="password" class="regular-text" />
								</label>
							</div>
							<p class="description">
								<?php esc_html_e( 'Requires INSERT and CREATE TABLE privileges.', 'simple-history' ); ?>
							</p>
						</td>
					</tr>

					<!-- Database (combined: name, table) -->
					<tr>
						<th scope="row">
							<?php echo wp_kses_post( Helpers::get_settings_field_title_output( __( 'Database', 'simple-history' ) ) ); ?>
						</th>
						<td>
							<div class="sh-InlineFields">
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Name', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" placeholder="audit_logs" />
								</label>
								<label class="sh-InlineField">
									<span class="sh-InlineFieldLabel"><?php esc_html_e( 'Table', 'simple-history' ); ?></span>
									<input type="text" class="regular-text" value="simple_history_events" />
								</label>
							</div>
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
			<p><?php esc_html_e( 'Store events outside the WordPress database for backup, monitoring, or compliance.', 'simple-history' ); ?></p>
		</div>
		<?php
		$this->render_beta_notice();
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

	/**
	 * Render new feature notice banner at the top of the settings page.
	 */
	private function render_beta_notice() {
		?>
		<div class="sh-BetaNotice">
			<p>
				<strong><?php esc_html_e( 'Beta feature – your feedback matters!', 'simple-history' ); ?></strong>
				<?php
				printf(
					/* translators: %s: email address link */
					esc_html__( 'Let us know what\'s working and what could be better at %s.', 'simple-history' ),
					'<a href="mailto:contact@simple-history.com">contact@simple-history.com</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
