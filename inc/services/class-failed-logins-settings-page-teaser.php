<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings page teaser for Failed Login Attempts feature.
 *
 * Shows a preview of the premium Failed Login Attempts settings when the premium add-on is not active.
 * The preview shows settings for controlling how failed logins are logged.
 *
 * When premium is active, this teaser is replaced by the real Failed Login Attempts settings page.
 *
 * @since 5.6.0
 */
class Failed_Logins_Settings_Page_Teaser extends Service {
	/**
	 * Menu slug for the failed logins settings page.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'general_settings_subtab_failed_logins';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Add failed logins settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		// Skip if premium is active (premium has its own failed logins page).
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		$menu_title = __( 'Failed login attempts', 'simple-history' );

		( new Menu_Page() )
			->set_page_title( __( 'Failed login attempts', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'render_settings_page' ] )
			->set_order( 25 ) // After Message Control (20), matches premium order.
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Render the failed logins settings page teaser.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sh-Page-content sh-FailedLoginsTeaser-wrap">
			<?php $this->render_section_intro(); ?>
			<?php $this->render_preview_banner(); ?>

			<div class="sh-FailedLoginsTeaser" inert aria-label="<?php esc_attr_e( 'Premium feature preview - not interactive', 'simple-history' ); ?>">
				<?php $this->render_settings_preview(); ?>
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
					<span class="sh-SettingsPage-settingsSection-icon sh-Icon--lock_clock"></span>
					<?php esc_html_e( 'Failed Login Attempts', 'simple-history' ); ?>
				</span>
			</h2>
			<div class="sh-SettingsSectionIntroduction">
				<p><?php esc_html_e( 'Control how failed login attempts are logged. Reduce log noise from brute force attacks while still catching important security events.', 'simple-history' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the preview mode banner.
	 */
	private function render_preview_banner() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'failed_logins_preview_banner' );
		?>
		<div class="sh-AlertsTeaser-banner">
			<span class="sh-AlertsTeaser-banner-icon dashicons dashicons-shield" aria-hidden="true"></span>
			<div class="sh-AlertsTeaser-banner-content">
				<span class="sh-AlertsTeaser-banner-title">
					<?php esc_html_e( 'Smart login attempt logging', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</span>
				<span><?php esc_html_e( 'Stop your log from being flooded by brute force attacks. Configure intelligent limits for failed login logging.', 'simple-history' ); ?></span>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Upgrade to Premium', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings preview.
	 */
	private function render_settings_preview() {
		?>
		<div class="sh-FailedLoginsTeaser-settings">
			<form>
				<table class="form-table" role="presentation">
					<tbody>
						<!-- Existing Users Setting -->
						<tr>
							<th scope="row">
								<span class="sh-SettingsPage-settingsSection-title">
									<span class="sh-SettingsPage-settingsSection-icon sh-Icon--account_circle"></span>
									<?php esc_html_e( 'Existing users', 'simple-history' ); ?>
								</span>
							</th>
							<td>
								<p><?php esc_html_e( 'Failed login attempts to existing users', 'simple-history' ); ?></p>
								<p>
									<label>
										<input type="radio" name="existing_users" value="log_all" checked disabled />
										<?php esc_html_e( 'Log all attempts (default behavior)', 'simple-history' ); ?>
									</label>
									<br />
									<label>
										<input type="radio" name="existing_users" value="log_nothing" disabled />
										<?php esc_html_e( "Don't log", 'simple-history' ); ?>
									</label>
									<br />
									<label>
										<input type="radio" name="existing_users" value="log_n_failed_attempts" disabled />
										<?php esc_html_e( 'Stop logging after 5 consecutive failed attempts', 'simple-history' ); ?>
									</label>
								</p>
							</td>
						</tr>

						<!-- Non-existing Users Setting -->
						<tr>
							<th scope="row">
								<span class="sh-SettingsPage-settingsSection-title">
									<span class="sh-SettingsPage-settingsSection-icon sh-Icon--no_accounts"></span>
									<?php esc_html_e( 'Non-existing users', 'simple-history' ); ?>
								</span>
							</th>
							<td>
								<p><?php esc_html_e( 'Failed login attempts to non-existing users', 'simple-history' ); ?></p>
								<p>
									<label>
										<input type="radio" name="unknown_users" value="log_all" disabled />
										<?php esc_html_e( 'Log all attempts (default behavior)', 'simple-history' ); ?>
									</label>
									<br />
									<label>
										<input type="radio" name="unknown_users" value="log_nothing" disabled />
										<?php esc_html_e( "Don't log", 'simple-history' ); ?>
									</label>
									<br />
									<label>
										<input type="radio" name="unknown_users" value="log_n_failed_attempts" checked disabled />
										<?php esc_html_e( 'Stop logging after 5 consecutive failed attempts', 'simple-history' ); ?>
									</label>
								</p>
							</td>
						</tr>

						<!-- Combine Attempts Setting -->
						<tr>
							<th scope="row">
								<span class="sh-SettingsPage-settingsSection-title">
									<span class="sh-SettingsPage-settingsSection-icon sh-Icon--merge_type"></span>
									<?php esc_html_e( 'Combine attempts', 'simple-history' ); ?>
								</span>
							</th>
							<td>
								<p><?php esc_html_e( 'If both existing users and non-existing users are set to stop logging after 5 consecutive failed attempts, enabling this setting means that the count will be combined for both existing and non-existing users.', 'simple-history' ); ?></p>
								<p>
									<label>
										<input type="checkbox" name="combine_attempts" value="1" checked disabled />
										<?php esc_html_e( 'Combine consecutive failed attempts', 'simple-history' ); ?>
									</label>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" disabled><?php esc_html_e( 'Save Changes', 'simple-history' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the upgrade CTA.
	 */
	private function render_upgrade_cta() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'failed_logins_settings_teaser' );
		?>
		<div class="sh-AlertsTeaser-cta">
			<div class="sh-AlertsTeaser-cta-content">
				<h3>
					<?php esc_html_e( 'Unlock Failed Login Controls', 'simple-history' ); ?>
					<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				</h3>
				<p>
					<?php esc_html_e( 'Prevent log flooding from brute force attacks with smart controls for failed login attempt logging.', 'simple-history' ); ?>
				</p>
				<a href="<?php echo esc_url( $premium_url ); ?>"><?php esc_html_e( 'Unlock Failed Login Controls', 'simple-history' ); ?> →</a>
			</div>
		</div>
		<?php
	}
}
