<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Service for showing a welcome admin notice after first plugin install.
 *
 * Displays a brief, dismissible notice pointing users to the event log page.
 * The notice is shown once on the first admin page load after activation,
 * then automatically dismissed on the next page load.
 *
 * The history page itself is skipped, since the notice links there.
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

		// The notice exists to point users to the history page, so on that page it is
		// a dead end — "Take a look →" would link to the screen they are already on.
		// Bail without marking it seen, so the single showing is saved for a screen
		// where the link actually takes the user somewhere.
		if ( $this->is_on_history_page() ) {
			return;
		}

		// Mark as seen so the notice only appears once.
		update_option( self::OPTION_NAME, 'seen', true );

		add_action( 'admin_notices', array( $this, 'show_welcome_notice' ) );
	}

	/**
	 * Check if the current request is the Simple History log page.
	 *
	 * The page can live under admin.php, tools.php or index.php depending on the
	 * menu location setting, so the `page` query arg is what identifies it.
	 *
	 * Helpers::is_on_our_own_pages() is deliberately not used here: it also matches
	 * the settings and tools pages, where the link is still useful, and it falls back
	 * to get_current_screen(), which WordPress only sets up after `admin_init`.
	 *
	 * @return bool
	 */
	private function is_on_history_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which admin page is being viewed.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		return $page === Simple_History::MENU_PAGE_SLUG;
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
