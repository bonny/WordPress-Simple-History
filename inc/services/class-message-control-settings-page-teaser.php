<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings page teaser for Message Control feature.
 *
 * Shows a preview of the premium Message Control settings when the premium add-on is not active.
 * The preview shows a list of loggers that can be enabled/disabled.
 *
 * When premium is active, this teaser is replaced by the real Message Control settings page.
 *
 * @since 5.6.0
 */
class Message_Control_Settings_Page_Teaser extends Service {
	/**
	 * Menu slug for the message control settings page.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'general_settings_subtab_message_control';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Add message control settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		// Skip if premium is active (premium has its own message control page).
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		$menu_title = __( 'Message Control', 'simple-history' );

		( new Menu_Page() )
			->set_page_title( __( 'Message Control', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'render_settings_page' ] )
			->set_order( 20 ) // Before Alerts (35), matches premium order.
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Render the message control settings page teaser.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sh-Page-content sh-MessageControlTeaser-wrap">
			<?php $this->render_section_intro(); ?>
			<?php $this->render_preview_banner(); ?>

			<div class="sh-MessageControlTeaser" inert aria-label="<?php esc_attr_e( 'Premium feature preview - not interactive', 'simple-history' ); ?>">
				<?php $this->render_loggers_preview(); ?>
			</div>

			<?php $this->render_upgrade_cta(); ?>
		</div>
		<?php
	}

	/**
	 * Render the section intro.
	 */
	private function render_section_intro() {
		?>
		<div class="sh-SettingsPage-settingsSection-wrap">
			<h2>
				<span class="sh-SettingsPage-settingsSection-title">
					<span class="sh-SettingsPage-settingsSection-icon sh-Icon--history_toggle_off"></span>
					<?php esc_html_e( 'Message Control', 'simple-history' ); ?>
				</span>
			</h2>
			<div class="sh-SettingsSectionIntroduction">
				<p><?php esc_html_e( 'Control which events are logged by enabling or disabling individual loggers. Reduce noise and focus on what matters most to you.', 'simple-history' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the preview mode banner.
	 */
	private function render_preview_banner() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'message_control_preview_banner' );
		?>
		<div class="sh-AlertsTeaser-banner">
			<span class="sh-AlertsTeaser-banner-icon dashicons dashicons-filter" aria-hidden="true"></span>
			<div class="sh-AlertsTeaser-banner-content">
				<span class="sh-AlertsTeaser-banner-title">
					<?php esc_html_e( 'Control what gets logged', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</span>
				<span><?php esc_html_e( 'Enable or disable loggers to focus on the events that matter most. Reduce database size and keep your log clean.', 'simple-history' ); ?></span>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Upgrade to Premium', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the loggers preview table.
	 */
	private function render_loggers_preview() {
		$sample_loggers = $this->get_sample_loggers();
		?>
		<div class="sh-MessageControlTeaser-loggers">
			<div class="sh-MessageControlTeaser-stats">
				<p>
					<strong><?php esc_html_e( '12 enabled', 'simple-history' ); ?></strong> <?php esc_html_e( 'loggers.', 'simple-history' ); ?>
					<strong><?php esc_html_e( '2 disabled', 'simple-history' ); ?></strong> <?php esc_html_e( 'loggers.', 'simple-history' ); ?>
				</p>
			</div>

			<table class="wp-list-table widefat fixed striped loggers">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Logger', 'simple-history' ); ?></th>
						<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
					<?php foreach ( $sample_loggers as $logger ) { ?>
						<tr class="<?php echo $logger['enabled'] ? 'enabled' : 'disabled'; ?>">
							<td class="name column-name has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Logger', 'simple-history' ); ?>">
								<span class="sh-Icon--<?php echo esc_attr( $logger['icon'] ); ?>"></span>
								<strong><?php echo esc_html( $logger['name'] ); ?></strong>
								<p class="description"><?php echo esc_html( $logger['description'] ); ?></p>
								<div class="row-actions">
									<?php if ( $logger['enabled'] ) { ?>
										<span class="disable">
											<a href="#"><?php esc_html_e( 'Disable', 'simple-history' ); ?></a>
										</span>
									<?php } else { ?>
										<span class="enable">
											<a href="#"><?php esc_html_e( 'Enable', 'simple-history' ); ?></a>
										</span>
									<?php } ?>
								</div>
							</td>
							<td class="status column-status" data-colname="<?php esc_attr_e( 'Status', 'simple-history' ); ?>">
								<?php if ( $logger['enabled'] ) { ?>
									<span class="sh-StatusIcon sh-StatusIcon--ok">✓</span>
									<?php esc_html_e( 'Enabled', 'simple-history' ); ?>
								<?php } else { ?>
									<span class="sh-StatusIcon sh-StatusIcon--unused">−</span>
									<?php esc_html_e( 'Disabled', 'simple-history' ); ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the upgrade CTA.
	 */
	private function render_upgrade_cta() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'message_control_settings_teaser' );
		?>
		<div class="sh-AlertsTeaser-cta">
			<div class="sh-AlertsTeaser-cta-content">
				<h3>
					<?php esc_html_e( 'Unlock Message Control', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</h3>
				<p>
					<?php esc_html_e( 'Enable or disable individual loggers to control exactly what events are recorded in your activity log.', 'simple-history' ); ?>
				</p>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Unlock Message Control', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Get sample loggers for preview.
	 *
	 * @return array<array{name: string, description: string, icon: string, enabled: bool}>
	 */
	private function get_sample_loggers(): array {
		return [
			[
				'name'        => __( 'User Logger', 'simple-history' ),
				'description' => __( 'Logs user logins, logouts, and failed logins', 'simple-history' ),
				'icon'        => 'person',
				'enabled'     => true,
			],
			[
				'name'        => __( 'Post Logger', 'simple-history' ),
				'description' => __( 'Logs changes to posts and pages', 'simple-history' ),
				'icon'        => 'article',
				'enabled'     => true,
			],
			[
				'name'        => __( 'Plugin Logger', 'simple-history' ),
				'description' => __( 'Logs plugin installs, updates, activations, and deletions', 'simple-history' ),
				'icon'        => 'extension',
				'enabled'     => true,
			],
			[
				'name'        => __( 'Theme Logger', 'simple-history' ),
				'description' => __( 'Logs theme changes and customizer edits', 'simple-history' ),
				'icon'        => 'palette',
				'enabled'     => true,
			],
			[
				'name'        => __( 'Media Logger', 'simple-history' ),
				'description' => __( 'Logs media uploads, edits, and deletions', 'simple-history' ),
				'icon'        => 'image',
				'enabled'     => false,
			],
			[
				'name'        => __( 'Options Logger', 'simple-history' ),
				'description' => __( 'Logs changes to WordPress options and settings', 'simple-history' ),
				'icon'        => 'settings',
				'enabled'     => true,
			],
			[
				'name'        => __( 'Comments Logger', 'simple-history' ),
				'description' => __( 'Logs new comments, edits, and spam/trash actions', 'simple-history' ),
				'icon'        => 'chat_bubble',
				'enabled'     => false,
			],
		];
	}
}
