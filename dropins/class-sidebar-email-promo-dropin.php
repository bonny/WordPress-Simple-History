<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin that adds a promotional card to the sidebar promoting the weekly email summary feature.
 *
 * The card is shown to users who:
 * - Have the 'manage_options' capability (typically admins)
 * - Haven't enabled email reports yet
 * - Haven't dismissed the promotional card
 * - Both free and premium users (to raise awareness of the feature)
 *
 * HOW TO FORCE THE PROMO CARD TO SHOW AGAIN (for testing):
 *
 * If you have dismissed the card and want to see it again, or want to test the feature,
 * you need to:
 *
 * 1. Disable email reports (if enabled)
 * 2. Remove the dismissal user meta
 *
 * Using WP-CLI:
 *
 * ```bash
 * # Disable email reports
 * wp option update simple_history_email_report_enabled false
 *
 * # Remove the dismissal user meta (replace <user-id> with your user ID, e.g., 1)
 * wp user meta delete <user-id> simple_history_email_promo_dismissed
 * ```
 *
 * Example for user ID 1:
 * ```bash
 * wp option update simple_history_email_report_enabled false
 * wp user meta delete 1 simple_history_email_promo_dismissed
 * ```
 *
 * Using Docker Compose (if you're using the local development environment):
 * ```bash
 * docker compose run --rm wpcli_mariadb option update simple_history_email_report_enabled false
 * docker compose run --rm wpcli_mariadb user meta delete 1 simple_history_email_promo_dismissed
 * ```
 */
class Sidebar_Email_Promo_Dropin extends Dropin {
	/** @var string User meta key for storing dismissal timestamp */
	const DISMISSED_USER_META_KEY = 'simple_history_email_promo_dismissed';

	/** @var string AJAX action for dismissing the promo card */
	const AJAX_ACTION = 'simple_history_dismiss_email_promo';

	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'gui_page_sidebar_html' ], 1 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_dismiss_promo' ] );
	}

	/**
	 * Check if the promotional card should be shown.
	 *
	 * @return bool True if card should be shown, false otherwise.
	 */
	private function should_show_promo() {
		// Only show to users with manage_options capability (typically admins).
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Don't show if email reports are already enabled.
		$email_reports_enabled = get_option( 'simple_history_email_report_enabled', false );
		if ( $email_reports_enabled ) {
			return false;
		}

		// Don't show if user has dismissed the promo.
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISSED_USER_META_KEY, true );
		if ( $dismissed ) {
			return false;
		}

		return true;
	}

	/**
	 * Output the promotional card HTML in the sidebar.
	 */
	public function gui_page_sidebar_html() {
		if ( ! $this->should_show_promo() ) {
			return;
		}

		// Enqueue JavaScript for dismissal functionality.
		wp_enqueue_script(
			'simple-history-email-promo',
			SIMPLE_HISTORY_DIR_URL . 'js/email-promo.js',
			[ 'jquery' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-email-promo',
			'simpleHistoryEmailPromo',
			[
				'nonce' => wp_create_nonce( self::AJAX_ACTION ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action' => self::AJAX_ACTION,
			]
		);

		// Settings page URL with anchor to email report settings.
		$settings_url = admin_url( 'admin.php?page=simple_history_settings_page&selected-tab=general_settings_subtab_general&selected-sub-tab=general_settings_subtab_settings_general#simple_history_email_report_settings' );

		?>
		<div class="sh-EmailPromoCard" id="simple-history-email-promo-card">
			<div class="sh-EmailPromoCard-badge">NEW FEATURE</div>

			<div class="sh-EmailPromoCard-icon">
				<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
					<!-- Email envelope -->
					<rect x="15" y="25" width="50" height="35" rx="3" fill="#E8F4FF" stroke="#3582C4" stroke-width="2"/>
					<path d="M15 28 L40 43 L65 28" stroke="#3582C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>

					<!-- Circular badge with "Weekly" text -->
					<circle cx="58" cy="22" r="14" fill="#3582C4"/>
					<text x="58" y="20" font-family="Arial, sans-serif" font-size="7" font-weight="bold" fill="white" text-anchor="middle">Weekly</text>
					<text x="58" y="27" font-family="Arial, sans-serif" font-size="6" fill="white" text-anchor="middle">Summary</text>
				</svg>
			</div>

			<h3 class="sh-EmailPromoCard-title">Don't miss out on what's happened on your site.</h3>
			<p class="sh-EmailPromoCard-description">Get Simple History Weekly Summary in your inbox!</p>

			<div class="sh-EmailPromoCard-actions">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="sh-EmailPromoCard-cta" data-dismiss-on-click="true">
					Subscribe now
				</a>
				<button type="button" class="sh-EmailPromoCard-dismiss">
					No thanks, not interested
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for dismissing the promotional card.
	 */
	public function ajax_dismiss_promo() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::AJAX_ACTION ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 403 );
		}

		// Store dismissal timestamp in user meta.
		$user_id = get_current_user_id();
		$dismissed = update_user_meta( $user_id, self::DISMISSED_USER_META_KEY, time() );

		if ( $dismissed ) {
			wp_send_json_success( [ 'message' => 'Promo dismissed successfully' ] );
		} else {
			wp_send_json_error( [ 'message' => 'Failed to dismiss promo' ] );
		}
	}
}
