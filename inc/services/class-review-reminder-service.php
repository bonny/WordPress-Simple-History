<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Service for showing review reminder notice.
 *
 * To show message again after clicking "Maybe later" button, we need to remove the user meta.
 *
 * Can be done using WP-CLI:
 *
 * `$ docker compose run --rm wpcli_mariadb user meta delete <user_id> simple_history_review_notice_dismissed`
 */
class Review_Reminder_Service extends Service {
	/** Minimum number of logged items before showing notice */
	const MINIMUM_LOGGED_ITEMS = 1000;

	/** Action name for dismissing notice */
	const DISMISS_NOTICE_ACTION = 'simple_history_dismiss_review_notice';

	/** Nonce name for dismissing notice */
	const DISMISS_NOTICE_NONCE = 'simple_history_dismiss_review_notice_nonce';

	/** User meta key for storing dismissal state */
	const USER_META_KEY = 'simple_history_review_notice_dismissed';

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Hook into WordPress admin_init since we only need this in the admin area.
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the service after WordPress is fully loaded.
	 */
	public function init() {
		// Only proceed if wp_admin_notice function exists (WordPress 6.4+).
		if ( ! function_exists( 'wp_admin_notice' ) ) {
			return;
		}

		// Only proceed if user can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Don't show on dashboard.
		global $pagenow;
		if ( $pagenow === 'index.php' ) {
			return;
		}

		// Hide if premium add-on-is active.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'maybe_show_review_notice' ) );
		add_action( 'wp_ajax_' . self::DISMISS_NOTICE_ACTION, array( $this, 'handle_ajax_dismiss_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Check if we should show the review notice and show it if conditions are met.
	 */
	public function maybe_show_review_notice() {
		// Only show on Simple History pages.
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		// Don't show if already dismissed by this user.
		if ( get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) ) {
			return;
		}

		// Don't show if not enough events logged.
		$total_events = Helpers::get_total_logged_events_count();

		if ( $total_events < self::MINIMUM_LOGGED_ITEMS ) {
			return;
		}

		$this->output_notice( $total_events );
	}

	/**
	 * Output the review notice HTML.
	 *
	 * @param int $total_events Total number of logged events.
	 */
	private function output_notice( $total_events ) {
		$message = sprintf(
			/* translators: %s: number of logged events */
			esc_html__( 'Thank you for using Simple History! You\'ve logged over %s events - that\'s awesome! If you find this plugin useful, would you mind taking a moment to rate it on WordPress.org? It really helps to keep the plugin growing and improving.', 'simple-history' ),
			esc_html( number_format_i18n( $total_events ) )
		);

		$message = sprintf(
			'<p>%s</p>',
			$message
		);

		$rate_text          = esc_html__( "Sure, you're worth it", 'simple-history' );
		$maybe_later_text   = esc_html__( 'Maybe Later', 'simple-history' );
		$already_rated_text = esc_html__( 'I already did!', 'simple-history' );

		$actions = sprintf(
			'<p>
				<a 
					href="https://wordpress.org/support/plugin/simple-history/reviews/#new-post" 
					class="button simple-history-review-notice-cta-button" 
					target="_blank" 
					rel="noopener noreferrer">
					%1$s
				</a>
				&nbsp;
				<button type="button" class="button button-link simple-history-review-notice-dismiss-button">
					%2$s
				</button>
				&nbsp;
				<button type="button" class="button button-link simple-history-review-notice-dismiss-button">
					%3$s
				</button>
			</p>',
			$rate_text,
			$maybe_later_text,
			$already_rated_text
		);

		// Bail if function does not exist, ie. WordPress < 6.4.
		if ( ! function_exists( 'wp_admin_notice' ) ) {
			return;
		}

		wp_admin_notice(
			wp_kses_post( $message . $actions ),
			array(
				'paragraph_wrap' => false,
				'type'           => 'info',
				'dismissible'    => true,
				'class'          => 'simple-history-review-notice',
			)
		);
	}

	/**
	 * Handle AJAX request to dismiss the notice.
	 */
	public function handle_ajax_dismiss_notice() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), self::DISMISS_NOTICE_NONCE ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Verify user can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		// Store dismissal in user meta.
		update_user_meta( get_current_user_id(), self::USER_META_KEY, true );

		wp_send_json_success();
	}

	/**
	 * Enqueue scripts and styles for the notice.
	 */
	public function enqueue_scripts() {
		// Only enqueue if notice should be shown.
		if ( get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$total_events = Helpers::get_total_logged_events_count();
		if ( $total_events < self::MINIMUM_LOGGED_ITEMS ) {
			return;
		}

		wp_enqueue_script(
			'simple-history-review-notice',
			plugins_url( 'js/review-notice.js', dirname( __DIR__ ) ),
			array( 'jquery' ),
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-review-notice',
			'simpleHistoryReviewNotice',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::DISMISS_NOTICE_ACTION,
				'nonce'   => wp_create_nonce( self::DISMISS_NOTICE_NONCE ),
			)
		);
	}
}
