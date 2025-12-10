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

	/**
	 * Called when dropin is loaded.
	 */
	public function loaded() {
		// Priority 3 to show after Black Week sale (priority 1) but before stats (priority 5).
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html' ], 3 );
		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_dismiss_promo' ] );
	}

	/**
	 * Enqueue scripts for the email promo card.
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_show_promo() ) {
			return;
		}

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
				'nonce'   => wp_create_nonce( self::AJAX_ACTION ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
			]
		);
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
	public function on_sidebar_html() {
		if ( ! $this->should_show_promo() ) {
			return;
		}

		// Settings page URL with anchor to email report settings section.
		$settings_url = Helpers::get_settings_page_url() . '#simple_history_email_report_section';

		?>
		<div class="postbox sh-EmailPromoCard sh-PremiumFeaturesPostbox" id="simple-history-email-promo-card" style="--box-bg-color: var(--sh-color-cream);">
			<div class="inside">
				<div class="sh-EmailPromoCard-badge">
					<span class="sh-Badge sh-Badge--new" style="background-color: transparent; color: var(--sh-color-blue);"><?php esc_html_e( 'New!', 'simple-history' ); ?></span>
				</div>

				<a href="<?php echo esc_url( $settings_url ); ?>" class="sh-EmailPromoCard-badgeImageLink">
					<img class="sh-EmailPromoCard-badgeImage" src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'assets/images/email-reports-badge.svg' ); ?>" alt="">
				</a>

				<p class="sh-EmailPromoCard-text sh-EmailPromoCard-text--intro">
					<strong><?php esc_html_e( 'Know what\'s happening — without logging in.', 'simple-history' ); ?></strong>
				</p>

				<p class="sh-EmailPromoCard-text">
					<?php esc_html_e( 'Get a weekly digest with login stats, content changes, and plugin activity.', 'simple-history' ); ?>
				</p>

				<div class="sh-EmailPromoCard-actions">
					<a href="<?php echo esc_url( $settings_url ); ?>" class="sh-PremiumFeaturesPostbox-button sh-EmailPromoCard-cta" data-dismiss-on-click="true">
						<?php esc_html_e( 'Get Weekly Digest', 'simple-history' ); ?>
					</a>

					<button type="button" class="sh-EmailPromoCard-dismiss button-link">
						<?php esc_html_e( 'Maybe later', 'simple-history' ); ?>
					</button>
				</div>
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
			wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'simple-history' ) ], 403 );
		}

		// Store dismissal timestamp in user meta (ISO8601 format for human readability).
		$user_id   = get_current_user_id();
		$dismissed = update_user_meta( $user_id, self::DISMISSED_USER_META_KEY, gmdate( 'c' ) );

		if ( $dismissed ) {
			wp_send_json_success( [ 'message' => __( 'Promo dismissed successfully', 'simple-history' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to dismiss promo', 'simple-history' ) ] );
		}
	}
}
