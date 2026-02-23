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
		// Skip AJAX requests — admin_notices never fires during AJAX,
		// so processing here would consume the pending state without showing anything.
		if ( wp_doing_ajax() ) {
			return;
		}

		$welcome_state = get_option( self::OPTION_NAME );

		// Nothing to do if option doesn't exist or is already seen.
		if ( $welcome_state !== 'pending' ) {
			return;
		}

		// Only show the notice if the user can view history and WP supports it.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability from filterable helper.
		if ( ! function_exists( 'wp_admin_notice' ) || ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		// Mark as seen so the notice only appears once.
		update_option( self::OPTION_NAME, 'seen', true );

		add_action( 'admin_notices', array( $this, 'show_welcome_notice' ) );
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
			esc_html__( 'Simple History is now tracking everything that happens on your site.', 'simple-history' ),
			sprintf(
				/* translators: %1$s: opening link tag, %2$s: closing link tag */
				esc_html__( 'Who logged in, what was changed, and when — it\'s all in your activity log. %1$sTake a look →%2$s', 'simple-history' ),
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
