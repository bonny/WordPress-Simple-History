<?php

namespace Simple_History\Services;

/**
 * Class that setups logging using WP hooks.
 */
class Stealth_Mode extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'init', [ $this, 'initialize' ], 10 );
	}

	/**
	 * Init Stealh Mode.
	 * Fired from the 'init' hook, with prio 10.
	 */
	public function initialize() {
		// If Stealth Mode is not enabled then there is no need to do anything.
		if ( ! self::is_stealth_mode_enabled() ) {
			return;
		}

		// If Stealth Mode is enabled, but the current user is allowed to see the GUI, then return early.
		if ( self::is_gui_visible_to_user() ) {
			return;
		}

		// If we get here then user is not allowed to see GUI.
		$this->add_hide_gui_hooks();
	}

	/**
	 * Add hooks and filters to hide GUI.
	 */
	protected function add_hide_gui_hooks() {
		// Hide dashboard widget.
		add_filter( 'simple_history/show_on_dashboard', '__return_false' );

		// Hide admin menu bar.
		add_filter( 'simple_history/show_in_admin_bar', '__return_false' );

		// Hide main menu page.
		add_filter( 'simple_history/show_admin_menu_page', '__return_false' );

		// Hide in network menu (The menu list below "My websites" in the admin bar).
		add_filter( 'simple_history/add_admin_bar_menu_item', '__return_false' );
		add_filter( 'simple_history/add_admin_bar_network_menu_item', '__return_false' );

		// Hide in plugins listing.
		add_filter( 'all_plugins', [ $this, 'filter_all_plugins' ] );

		// Hide from the "Go to Simple History" link that is shown after plugin updates.
		add_filter( 'simple_history/show_action_link', '__return_false' );

		// Remove pages from Menu_Manager.
		add_filter( 'simple_history/menu_manager/get_pages', '__return_empty_array' );
	}

	/**
	 * Hide Simple History and related plugins that use Simple History,
	 * to conceal the fact that Simple History is installed.
	 *
	 * @param array $plugins List of plugins.
	 * @return array Filtered list of plugins.
	 */
	public function filter_all_plugins( $plugins ) {
		unset(
			$plugins['simple-history/index.php'],
			$plugins['developer-loggers-for-simple-history/developer_loggers.php']
		);

		// Also exclude plugins that require Simple History.
		// I.e all plugins that have a RequiresPlugins header that includes Simple History.
		$plugins = array_filter(
			$plugins,
			function ( $plugin ) {
				$requires_plugins = self::sanitize_dependency_slugs( $plugin['RequiresPlugins'] );
				return ! in_array( 'simple-history', $requires_plugins, true );
			}
		);

		return $plugins;
	}

	/**
	 * Get allowed email addresses from constant and filter.
	 *
	 * @return array Array of allowed email addresses.
	 */
	public static function get_allowed_email_addresses() {
		// Get allowed emails from constant into an array.
		$allowed_emails = defined( 'SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS' )
			? explode( ',', \SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS )
			: [];

		// Clean entries and remove empty values.
		$allowed_emails = array_filter( array_map( 'trim', $allowed_emails ) );

		/**
		 * Filters the list of allowed emails for Stealth Mode.
		 *
		 * Developers can use this filter to add, modify, or remove allowed email addresses
		 * for accessing the Simple History GUI while Stealth Mode is enabled.
		 *
		 * @since 1.0.0
		 * @param array $allowed_emails List of allowed emails or wildcard domains.
		 */
		return apply_filters( 'simple_history/stealth_mode_allowed_emails', $allowed_emails );
	}

	/**
	 * Check if the current user's email is allowed based on Stealth Mode settings.
	 *
	 * Supports exact email matches and wildcard domain matches (e.g., "@example.com").
	 *
	 * @param string $user_email The user's email to check.
	 * @return bool True if the user's email is allowed, false otherwise.
	 */
	public static function is_user_email_allowed_in_stealth_mode( $user_email ) {
		$allowed_emails = self::get_allowed_email_addresses();

		// Check for exact email match.
		if ( in_array( $user_email, $allowed_emails, true ) ) {
			return true;
		}

		// Check for wildcard domain match.
		foreach ( $allowed_emails as $allowed_email ) {
			if ( strpos( $allowed_email, '@' ) === 0 ) {
				// Extract domain part.
				$domain = substr( $allowed_email, 1 );
				if ( str_ends_with( $user_email, "@{$domain}" ) ) {
					return true;
				}
			}
		}

		// No match found.
		return false;
	}

	/**
	 * Check if the GUI is visible to the current user in Stealth Mode.
	 *
	 * @return bool True if the GUI is visible to the user, false otherwise.
	 */
	public static function is_gui_visible_to_user() {
		// Full Stealth Mode completely hides the GUI.
		if ( self::is_full_stealth_mode_enabled() ) {
			return false;
		}

		// If Stealth Mode is not enabled, allow access by default.
		if ( ! self::is_stealth_mode_enabled() ) {
			return true;
		}

		// Get the current user's email.
		$current_user = wp_get_current_user();
		$user_email   = $current_user->user_email;

		// Delegate the email check to the helper function.
		return self::is_user_email_allowed_in_stealth_mode( $user_email );
	}

	/**
	 * Check if Stealth Mode is fully enabled (complete GUI lockout).
	 *
	 * @return bool True if full Stealth Mode is enabled, false otherwise.
	 */
	public static function is_full_stealth_mode_enabled() {
		$enabled_via_constant = defined( 'SIMPLE_HISTORY_STEALTH_MODE_ENABLE' ) && \SIMPLE_HISTORY_STEALTH_MODE_ENABLE === true;

		/**
		 * Filter to enable/disable full stealth mode.
		 *
		 * @since 1.0.0
		 * @param bool $enabled_via_constant Current stealth mode status.
		 */
		return apply_filters( 'simple_history/full_stealth_mode_enabled', $enabled_via_constant );
	}

	/**
	 * Check if Stealth Mode is enabled via emails or full lockout.
	 *
	 * @return bool True if any Stealth Mode is enabled, false otherwise.
	 */
	public static function is_stealth_mode_enabled() {
		// Full Stealth Mode takes precedence.
		if ( self::is_full_stealth_mode_enabled() ) {
			return true;
		}

		// Check if email-based Stealth Mode is enabled.
		$emails_from_constant = [];

		if ( defined( 'SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS' ) ) {
			$emails_from_constant = explode( ',', \SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS );
			$emails_from_constant = array_filter( array_map( 'trim', $emails_from_constant ) );
		}

		$emails_from_filter = apply_filters( 'simple_history/stealth_mode_allowed_emails', $emails_from_constant );

		return ! empty( $emails_from_constant ) || ! empty( $emails_from_filter );
	}

	/**
	 * Sanitizes slugs.
	 * Same function as in WP Core (but that one is protected).
	 *
	 * @since 6.5.0
	 *
	 * @param string $slugs A comma-separated string of plugin dependency slugs.
	 * @return array An array of sanitized plugin dependency slugs.
	 */
	protected static function sanitize_dependency_slugs( $slugs ) {
		$sanitized_slugs = array();
		$slugs           = explode( ',', $slugs );

		foreach ( $slugs as $slug ) {
			$slug = trim( $slug );

			/**
			 * Filters a plugin dependency's slug before matching to
			 * the WordPress.org slug format.
			 *
			 * Can be used to switch between free and premium plugin slugs, for example.
			 *
			 * @since 6.5.0
			 *
			 * @param string $slug The slug.
			 */
			$slug = apply_filters( 'wp_plugin_dependencies_slug', $slug ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

			// Match to WordPress.org slug format.
			if ( preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/mu', $slug ) ) {
				$sanitized_slugs[] = $slug;
			}
		}
		$sanitized_slugs = array_unique( $sanitized_slugs );
		sort( $sanitized_slugs );

		return $sanitized_slugs;
	}
}
