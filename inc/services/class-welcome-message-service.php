<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Service for showing a welcome admin notice after first plugin install.
 *
 * Displays a brief, dismissible notice pointing users to the event log page.
 * The notice is shown once on the first admin page load after activation,
 * then automatically dismissed on the next page load.
 */
class Welcome_Message_Service extends Service {
	/** Option name for tracking welcome message state. */
	const OPTION_NAME = 'simple_history_welcome_message_seen';

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
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

		// Only show for users who can view the history page.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability from filterable helper.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		$welcome_state = get_option( self::OPTION_NAME );

		// No state set means first install hasn't happened via our hook,
		// or user upgraded from an older version. Either way, nothing to show.
		if ( $welcome_state === false ) {
			return;
		}

		// State 'seen' means the notice was already shown and dismissed.
		if ( $welcome_state === 'seen' ) {
			return;
		}

		// Only show when state is 'pending'.
		if ( $welcome_state !== 'pending' ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'show_welcome_notice' ) );

		// Mark as seen so the notice disappears after navigating away.
		update_option( self::OPTION_NAME, 'seen' );
	}

	/**
	 * Output the welcome notice HTML.
	 */
	public function show_welcome_notice() {
		// Bail if function does not exist, i.e. WordPress < 6.4.
		if ( ! function_exists( 'wp_admin_notice' ) ) {
			return;
		}

		$history_url = Helpers::get_history_admin_url();

		$message = sprintf(
			'<p><strong>%1$s</strong></p><p>%2$s</p>',
			esc_html__( 'Thank you for installing Simple History!', 'simple-history' ),
			sprintf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag */
				esc_html__( 'Your site activity is now being logged. %1$sView your activity log%2$s to see what\'s happening on your site.', 'simple-history' ),
				'<a href="' . esc_url( $history_url ) . '">',
				'</a>'
			)
		);

		wp_admin_notice(
			$message,
			array(
				'paragraph_wrap' => false,
				'type'           => 'success',
				'dismissible'    => true,
			)
		);
	}

	/**
	 * Set the welcome message flag to 'pending'.
	 *
	 * Called during first install (database setup) to trigger
	 * the welcome notice on the next admin page load.
	 */
	public static function set_pending() {
		update_option( self::OPTION_NAME, 'pending', true );
	}
}
