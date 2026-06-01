<?php
/**
 * Privacy & Data settings page service for Simple History.
 *
 * @package Simple_History
 */

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings tab for privacy and data-handling controls.
 *
 * In core this surfaces the always-on WordPress privacy integration. It is the
 * shared container that premium privacy features register additional
 * subsections into.
 *
 * @since 5.29.0
 */
class Privacy_Settings_Page extends Service {
	private const SETTINGS_PAGE_SLUG = 'simple_history_settings_menu_slug_privacy';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_settings_menu_tab' ), 15 );
		add_action( 'admin_menu', array( $this, 'register_and_add_settings' ) );
	}

	/**
	 * Add the "Privacy & Data" tab as a subtab of the main settings page.
	 */
	public function add_settings_menu_tab() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if the parent settings page does not exist (Stealth Mode, etc.).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		( new Menu_Page() )
			->set_page_title( __( 'Privacy & Data', 'simple-history' ) )
			->set_menu_title( __( 'Privacy & Data', 'simple-history' ) )
			->set_menu_slug( 'general_settings_subtab_privacy' )
			->set_callback( array( $this, 'settings_output' ) )
			->set_order( 45 )
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Register the Compliance settings section.
	 */
	public function register_and_add_settings() {
		Helpers::add_settings_section(
			'simple_history_settings_section_privacy_compliance',
			__( 'Compliance', 'simple-history' ),
			array( $this, 'render_compliance_section' ),
			self::SETTINGS_PAGE_SLUG
		);
	}

	/**
	 * Render the Compliance section intro. The erasure line only appears when
	 * experimental features are enabled, so the claim matches what is wired up.
	 */
	public function render_compliance_section() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p>
				<?php esc_html_e( 'Simple History is registered with WordPress\'s personal-data export tool (Tools → Export Personal Data). When you process a request there, the activity the person performed is included automatically.', 'simple-history' ); ?>
			</p>
			<?php if ( Helpers::experimental_features_is_enabled() ) { ?>
				<p>
					<?php esc_html_e( '🧪 Experimental: exports also include activity about the person performed by others (for example an admin editing their profile, or failed logins targeting their account), with other people\'s names and emails redacted.', 'simple-history' ); ?>
				</p>
				<p>
					<?php esc_html_e( '🧪 Experimental: Simple History is also registered with the erasure tool — running an erasure request anonymizes personal data in matching activity-log entries while preserving the audit record.', 'simple-history' ); ?>
				</p>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Output the settings page wrapper.
	 */
	public function settings_output() {
		?>
		<div class="wrap sh-Page-content">
			<?php do_settings_sections( self::SETTINGS_PAGE_SLUG ); ?>
		</div>
		<?php
	}
}
