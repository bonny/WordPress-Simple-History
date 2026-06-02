<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Services\AddOns_Licences;

/**
 * Shows a reminder card on Simple History admin pages
 * when one or more add-ons are installed but have no license key entered.
 *
 * Without a license key, the user does not receive updates — which has been
 * a recurring support topic. The card surfaces this state and links straight
 * to the license entry field.
 *
 * The card is dismissible per-user. Once dismissed (explicitly via the close
 * button, or implicitly by visiting the License settings tab), it no longer
 * appears for that add-on for that user. Installing a new add-on without a
 * license re-triggers the card for that add-on.
 *
 * To reset dismissal for testing:
 * `$ docker compose run --rm wpcli_mariadb user meta delete <user_id> simple_history_license_reminder_dismissed_addons`
 */
class License_Reminder_Service extends Service {
	/** Action name for dismissing the card via AJAX. */
	const DISMISS_ACTION = 'simple_history_dismiss_license_reminder';

	/** Nonce name for dismissing the card. */
	const DISMISS_NONCE = 'simple_history_dismiss_license_reminder_nonce';

	/** User meta key storing the array of dismissed add-on slugs. */
	const USER_META_KEY = 'simple_history_license_reminder_dismissed_addons';

	/** @inheritdoc */
	public function loaded() {
		// Priority 10 so we render above the History Insights box (priority 30).
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'maybe_output_card' ], 10 );
		add_action( 'admin_init', [ $this, 'maybe_implicit_dismiss_on_licenses_tab' ] );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'handle_ajax_dismiss' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * If the current user is viewing the Licenses settings sub-tab, mark every
	 * currently-unlicensed add-on as dismissed for them. They have found the
	 * License tab on their own, so the discovery goal is satisfied even if they
	 * haven't entered a key yet.
	 *
	 * Runs on admin_init so it fires on the Settings page (the sidebar-render
	 * hook only fires on the event log page).
	 */
	public function maybe_implicit_dismiss_on_licenses_tab() {
		// Scope to the Simple History settings page. admin_init fires on every
		// admin request, so without this guard a stale URL on any wp-admin page
		// that carries selected-sub-tab=general_settings_subtab_licenses would
		// silently dismiss the reminder.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page !== 'simple_history_settings_page' ) {
			return;
		}

		if ( ! $this->is_on_licenses_settings_tab() ) {
			return;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return;
		}

		// Respect the same suppression filter as maybe_output_card() — sites
		// that opt out of the card should not accumulate dismissal state either.
		if ( ! apply_filters( 'simple_history/license_reminder/should_show', true ) ) {
			return;
		}

		$addons_without_license = $this->get_addons_missing_license();

		if ( empty( $addons_without_license ) ) {
			return;
		}

		$this->mark_addons_dismissed( $addons_without_license );
	}

	/**
	 * Render the reminder card if the user has add-ons missing license keys
	 * and has not already dismissed those add-ons.
	 */
	public function maybe_output_card() {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return;
		}

		/**
		 * Filter whether the license reminder card should be shown at all.
		 *
		 * Use this to suppress the card site-wide, e.g. for managed/Composer
		 * installs where licensing happens centrally:
		 *
		 *     add_filter( 'simple_history/license_reminder/should_show', '__return_false' );
		 *
		 * @param bool $should_show Whether to render the card. Default true.
		 */
		if ( ! apply_filters( 'simple_history/license_reminder/should_show', true ) ) {
			return;
		}

		$addons_without_license = $this->get_addons_missing_license();

		if ( empty( $addons_without_license ) ) {
			return;
		}

		$dismissed_slugs = $this->get_dismissed_slugs();

		$pending_addons = array_values(
			array_filter(
				$addons_without_license,
				static function ( $addon ) use ( $dismissed_slugs ) {
					return ! in_array( $addon->slug, $dismissed_slugs, true );
				}
			)
		);

		if ( empty( $pending_addons ) ) {
			return;
		}

		$this->render_card( $pending_addons );
	}

	/**
	 * Are we currently on the Licences settings sub-tab?
	 *
	 * @return bool
	 */
	private function is_on_licenses_settings_tab() {
		return Menu_Manager::get_current_sub_tab_slug() === 'general_settings_subtab_licenses';
	}

	/**
	 * Get add-ons that are registered but have no license key entered.
	 *
	 * @return array<\Simple_History\AddOn_Plugin>
	 */
	private function get_addons_missing_license() {
		/** @var AddOns_Licences|null $licences_service */
		$licences_service = $this->simple_history->get_service( AddOns_Licences::class );

		if ( ! $licences_service instanceof AddOns_Licences ) {
			return [];
		}

		if ( ! $licences_service->has_add_ons() ) {
			return [];
		}

		$addons_without_license = [];

		foreach ( $licences_service->get_addon_plugins() as $addon ) {
			$key = $addon->get_license_key();

			if ( ! empty( $key ) ) {
				continue;
			}

			$addons_without_license[] = $addon;
		}

		return $addons_without_license;
	}

	/**
	 * Get the current user's dismissed add-on slugs.
	 *
	 * @return string[]
	 */
	private function get_dismissed_slugs() {
		$stored = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );

		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Add the given add-ons' slugs to the current user's dismissed list.
	 *
	 * Diff-guarded: only writes to user meta if the set actually changed,
	 * so repeated visits to the License tab don't keep writing.
	 *
	 * @param array<\Simple_History\AddOn_Plugin> $addons Add-ons to mark dismissed.
	 */
	private function mark_addons_dismissed( array $addons ) {
		$current = $this->get_dismissed_slugs();

		$incoming_slugs = array_map(
			static function ( $addon ) {
				return $addon->slug;
			},
			$addons
		);

		$merged = array_values( array_unique( array_merge( $current, $incoming_slugs ) ) );

		$current_sorted = $current;
		$merged_sorted  = $merged;
		sort( $current_sorted );
		sort( $merged_sorted );

		if ( $current_sorted === $merged_sorted ) {
			return;
		}

		update_user_meta( get_current_user_id(), self::USER_META_KEY, $merged );
	}

	/**
	 * Handle the AJAX request that dismisses the card.
	 */
	public function handle_ajax_dismiss() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), self::DISMISS_NONCE ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$addons_without_license = $this->get_addons_missing_license();

		if ( ! empty( $addons_without_license ) ) {
			$this->mark_addons_dismissed( $addons_without_license );
		}

		wp_send_json_success();
	}

	/**
	 * Enqueue the JS that wires the dismiss button to the AJAX endpoint.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return;
		}

		// Only on Simple History admin pages — the card is rendered there only.
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		wp_enqueue_script(
			'simple-history-license-reminder',
			plugins_url( 'js/license-reminder.js', dirname( __DIR__ ) ),
			[ 'jquery' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-license-reminder',
			'simpleHistoryLicenseReminder',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::DISMISS_ACTION,
				'nonce'   => wp_create_nonce( self::DISMISS_NONCE ),
			]
		);
	}

	/**
	 * Output the card HTML.
	 *
	 * @param array<\Simple_History\AddOn_Plugin> $addons_without_license Add-ons missing their license key.
	 */
	private function render_card( $addons_without_license ) {
		$licenses_url = Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_licenses' );

		if ( count( $addons_without_license ) === 1 ) {
			$title = sprintf(
				/* translators: %s: add-on name, e.g. "Simple History Premium" */
				__( 'Add your %s license key', 'simple-history' ),
				$addons_without_license[0]->name
			);

			$description = __( 'Enter your license key to enable automatic updates.', 'simple-history' );
		} else {
			$title       = __( 'Add your add-on license keys', 'simple-history' );
			$description = __( 'You have add-ons that need license keys. Enter them to enable automatic updates.', 'simple-history' );
		}

		$button_label  = __( 'Add license key', 'simple-history' );
		$dismiss_label = __( 'Dismiss', 'simple-history' );
		?>
		<div class="postbox sh-LicenseReminder" role="region" aria-label="<?php esc_attr_e( 'License key required', 'simple-history' ); ?>">
			<div class="inside">
				<h3 class="sh-LicenseReminder-title">
					<span class="dashicons dashicons-admin-network sh-LicenseReminder-icon" aria-hidden="true"></span>
					<?php echo esc_html( $title ); ?>
				</h3>
				<p class="sh-LicenseReminder-text"><?php echo esc_html( $description ); ?></p>
				<p class="sh-LicenseReminder-actions">
					<a href="<?php echo esc_url( $licenses_url ); ?>" class="button button-primary">
						<?php echo esc_html( $button_label ); ?>
					</a>
				</p>
				<p class="sh-LicenseReminder-dismissWrap">
					<button type="button" class="sh-LicenseReminder-dismiss">
						<?php echo esc_html( $dismiss_label ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}
}
