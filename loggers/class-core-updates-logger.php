<?php

namespace Simple_History\Loggers;

/**
 * Logs WordPress core updates
 */
class Core_Updates_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleCoreUpdatesLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Core Updates Logger', 'simple-history' ),
			'description' => __( 'Logs the update of WordPress (manual and automatic updates)', 'simple-history' ),
			'capability'  => 'update_core',
			'messages'    => array(
				'core_updated'                             => __( 'Updated WordPress to {new_version} from {prev_version}', 'simple-history' ),
				'core_auto_updated'                        => __( 'WordPress auto-updated to {new_version} from {prev_version}', 'simple-history' ),
				'core_update_failed'                       => __( 'Failed to update WordPress', 'simple-history' ),
				'core_db_version_updated'                  => __( 'WordPress database version updated to {new_version} from {prev_version}', 'simple-history' ),
				'core_major_auto_updates_setting_enabled'  => __( 'Enabled automatic updates for all new versions of WordPress', 'simple-history' ),
				'core_major_auto_updates_setting_disabled' => __( 'Switched to automatic updates for maintenance and security releases of WordPress only', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'WordPress Core', 'User logger: search', 'simple-history' ),
					'options' => array(
						_x( 'WordPress core updates', 'User logger: search', 'simple-history' ) => array(
							'core_updated',
							'core_auto_updated',
							'core_update_failed',
						),
					),
				),
			),
		];
	}
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( '_core_updated_successfully', array( $this, 'on_core_updated' ) );
		add_action( 'update_feedback', array( $this, 'on_update_feedback' ) );
		add_action( 'load-update-core.php', array( $this, 'on_load_update_core_handle_auto_update_core_major' ) );

		// Hook for detecting automatic core update failures.
		// This filter is called when WordPress is about to send an email about an automatic update.
		// We get the actual WP_Error result object with full error details.
		// NOTE: This only works for AUTOMATIC updates. Manual update failures cannot be reliably
		// detected due to WordPress Core_Upgrader architecture (result not stored in accessible location).
		add_filter( 'auto_core_update_send_email', array( $this, 'on_auto_core_update_send_email' ), 10, 4 );

		// TODO: check if this works after refactoring and autoloading and stuff
		// Can't log db updates at the moment, because loaded() is not called yet when the action fires.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// add_action( 'wp_upgrade', array( $this, "on_wp_upgrade" ), 10, 2 );
	}

	/**
	 * Fired when loading admin page /wp-admin/update-core.php.
	 *
	 * This site is automatically kept up to date with each new version of WordPress.
	 * Switch to automatic updates for maintenance and security releases only.
	 * http://wordpress-stable-docker-mariadb.test:8282/wp-admin/update-core.php?action=core-major-auto-updates-settings&value=disable&_wpnonce=ad1ff0569c
	 *
	 * This site is automatically kept up to date with maintenance and security releases of WordPress only.
	 * Enable automatic updates for all new versions of WordPress.
	 * http://wordpress-stable-docker-mariadb.test:8282/wp-admin/update-core.php?action=core-major-auto-updates-settings&value=enable&_wpnonce=ad1ff0569c
	 */
	public function on_load_update_core_handle_auto_update_core_major() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = $_GET['value'] ?? '';

		if ( ! in_array( $value, [ 'enable', 'disable' ], true ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'core-major-auto-updates-nonce' ) ) {
			return;
		}

		switch ( $value ) {
			case 'enable':
				$this->info_message( 'core_major_auto_updates_setting_enabled' );
				break;
			case 'disable':
				$this->info_message( 'core_major_auto_updates_setting_disabled' );
				break;
		}
	}

	/**
	 * Fires after a site is fully upgraded.
	 * The database, that is.
	 *
	 * @param int $wp_db_version         The new $wp_db_version.
	 * @param int $wp_current_db_version The old (current) $wp_db_version.
	 */
	public function on_wp_upgrade( $wp_db_version, $wp_current_db_version ) {
		$this->debug_message(
			'core_db_version_updated',
			[
				'new_version'  => $wp_db_version,
				'prev_version' => $wp_current_db_version,
			]
		);
	}

	/**
	 * We need to store the WordPress version we are updating from.
	 * 'update_feedback' is a suitable filter.
	 */
	public function on_update_feedback() {

		if ( empty( $GLOBALS['wp_version'] ) || isset( $GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ] ) ) {
			return;
		}

		$GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ] = $GLOBALS['wp_version'];
	}

	/**
	 * Called when WordPress is updated
	 *
	 * @param string $new_wp_version The new WordPress version.
	 */
	public function on_core_updated( $new_wp_version ) {
		$old_wp_version = empty( $GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ] ) ? $GLOBALS['wp_version'] : $GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ];

		$auto_update = true;
		if ( $GLOBALS['pagenow'] === 'update-core.php' ) {
			$auto_update = false;
		}

		$message = $auto_update ? 'core_auto_updated' : 'core_updated';

		$this->notice_message(
			$message,
			[
				'prev_version' => $old_wp_version,
				'new_version'  => $new_wp_version,
			]
		);
	}

	/**
	 * Get action links for a log row.
	 *
	 * For major-version updates (e.g. 6.9 → 7.0), surface up to two links:
	 * - wp-admin/about.php — only when the currently installed WP version's
	 *   X.Y still matches the event's new_version. The about.php page always
	 *   shows the *current* version's content, so for historical events on
	 *   older versions the local link would be misleading and gets dropped.
	 * - wordpress.org release notes — version-specific URL built from
	 *   new_version (matches the pattern WP core itself uses on about.php).
	 *   Always shown on major-bump events because it's historically accurate.
	 *
	 * Skipped for:
	 * - minor/patch releases (e.g. 7.0.1 → 7.0.2) where there is no major
	 *   release to describe
	 * - update setting changes and failures (no destination to send the
	 *   user to)
	 *
	 * @param object $row Log row object.
	 * @return array Array of action link arrays.
	 */
	public function get_action_links( $row ) {
		if ( ! current_user_can( 'read' ) ) {
			return [];
		}

		$context     = $row->context ?? [];
		$message_key = $context['_message_key'] ?? '';

		if ( ! in_array( $message_key, [ 'core_updated', 'core_auto_updated' ], true ) ) {
			return [];
		}

		$prev_version = $context['prev_version'] ?? '';
		$new_version  = $context['new_version'] ?? '';

		if ( ! $this->is_major_version_update( $prev_version, $new_version ) ) {
			return [];
		}

		$action_links = [];

		$event_xy   = $this->extract_major_version( $new_version );
		$current_xy = $this->extract_major_version( get_bloginfo( 'version' ) );

		if ( $event_xy !== '' && $event_xy === $current_xy ) {
			$action_links[] = [
				'url'    => admin_url( 'about.php' ),
				'label'  => __( 'About this version', 'simple-history' ),
				'action' => 'view',
			];
		}

		if ( $event_xy !== '' ) {
			$action_links[] = [
				'url'    => sprintf(
					'https://wordpress.org/documentation/wordpress-version/version-%s/',
					sanitize_title( $event_xy )
				),
				'label'  => sprintf(
					/* translators: %s: WordPress major version, e.g. "7.0". */
					__( 'WordPress %s release notes', 'simple-history' ),
					$event_xy
				),
				'action' => 'view',
			];
		}

		return $action_links;
	}

	/**
	 * Detect whether a version bump is a major WordPress release.
	 *
	 * Compares the X.Y portion of the two versions (ignoring any pre-release
	 * suffix like -RC1 or -beta1). A change to X or Y is a major release;
	 * a change only to the patch component (Z) is a minor/security release.
	 *
	 * Examples:
	 * - 6.9   → 7.0    → true  (X changed)
	 * - 6.5.3 → 6.6    → true  (Y changed)
	 * - 6.5   → 6.5.1  → false (only Z changed)
	 * - 7.0-RC4 → 7.0-RC5 → false (same X.Y)
	 *
	 * @param string $prev_version Previous version string.
	 * @param string $new_version  New version string.
	 * @return bool True when the X or Y component differs.
	 */
	private function is_major_version_update( $prev_version, $new_version ) {
		$prev_xy = $this->extract_major_version( $prev_version );
		$new_xy  = $this->extract_major_version( $new_version );

		if ( $prev_xy === '' || $new_xy === '' ) {
			return false;
		}

		return $prev_xy !== $new_xy;
	}

	/**
	 * Extract the X.Y major version from a version string.
	 *
	 * Strips any pre-release suffix (-RC1, -beta1, -alpha2, etc.) before
	 * splitting on dot. Returns "" when the input doesn't have at least
	 * two numeric components.
	 *
	 * Examples:
	 * - "6.9"       → "6.9"
	 * - "6.5.3"     → "6.5"
	 * - "7.0-RC5"   → "7.0"
	 *
	 * @param string $version Version string.
	 * @return string X.Y or empty string when not derivable.
	 */
	private function extract_major_version( $version ) {
		if ( empty( $version ) ) {
			return '';
		}

		$main  = preg_replace( '/-.+$/', '', (string) $version );
		$parts = explode( '.', $main );

		if ( count( $parts ) < 2 ) {
			return '';
		}

		return $parts[0] . '.' . $parts[1];
	}

	/**
	 * Log automatic core update failures.
	 *
	 * This filter is called when WordPress is about to send an email notification
	 * about an automatic core update. We use it to log update failures because
	 * it provides reliable access to the WP_Error result object.
	 *
	 * This is the RELIABLE method for logging automatic core update failures.
	 * Unlike upgrader_process_complete, this filter receives the actual error result
	 * as a parameter, so we can access all error details.
	 *
	 * @since WordPress 3.7.0
	 *
	 * @param bool   $send        Whether to send the email. We don't modify this.
	 * @param string $type        The type of email: 'success', 'fail', 'manual', or 'critical'.
	 * @param object $core_update The update offer that was attempted.
	 * @param mixed  $result      The result for the core update. Can be WP_Error.
	 * @return bool Whether to send the email (unchanged).
	 */
	public function on_auto_core_update_send_email( $send, $type, $core_update, $result ) {
		// Only log failures (not success).
		if ( ! in_array( $type, [ 'fail', 'critical' ], true ) ) {
			return $send;
		}

		// Ensure we have a WP_Error result.
		if ( ! is_wp_error( $result ) ) {
			return $send;
		}

		// Build context for logging.
		$context = [
			'error_code'    => $result->get_error_code(),
			'error_message' => $result->get_error_message(),
			'auto_update'   => true, // This filter only fires for automatic updates.
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- This explains possible values.
			'failure_type'  => $type, // Is 'fail' or 'critical'.
		];

		// Add error data if available.
		$error_data = $result->get_error_data();
		if ( ! empty( $error_data ) ) {
			$context['error_data'] = $error_data;
		}

		// Add attempted version.
		if ( ! empty( $core_update->current ) ) {
			$context['new_version'] = $core_update->current;
		}

		// Get the old WordPress version if available.
		if ( ! empty( $GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ] ) ) {
			$context['prev_version'] = $GLOBALS[ 'simple_history_' . $this->get_slug() . '_wp_version' ];
		}

		// Detect if this is a rollback scenario (WordPress core has its own rollback mechanism).
		// Core rollback is triggered by specific error codes.
		$error_codes          = $result->get_error_codes();
		$rollback_error_codes = [
			'rollback_was_required',
			'__copy_dir',
			'disk_full',
			'do_rollback',
		];

		// Check if any error code indicates a rollback.
		if ( ! empty( array_intersect( $error_codes, $rollback_error_codes ) ) ) {
			$context['rollback_occurred'] = true;
		}

		// For 'critical' failures, WordPress has already determined this was a critical issue
		// (occurred after we started copying core files).
		if ( $type === 'critical' ) {
			$context['critical_failure'] = true;
		}

		// Log the failure.
		$this->warning_message(
			'core_update_failed',
			$context
		);

		// Don't modify the email sending behavior.
		return $send;
	}
}
