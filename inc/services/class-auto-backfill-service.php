<?php

namespace Simple_History\Services;

use Simple_History\Existing_Data_Importer;
use Simple_History\Helpers;

/**
 * Handles automatic backfill of historical data on first install.
 *
 * This service runs a one-time backfill of existing WordPress data
 * when the plugin is first installed. It creates log entries for
 * posts and pages that existed before Simple History was installed,
 * helping users see their site's history from day one.
 *
 * The backfill runs via a scheduled cron event to avoid impacting
 * the initial plugin activation experience.
 */
class Auto_Backfill_Service extends Service {
	/**
	 * Cron hook name for auto backfill.
	 */
	const CRON_HOOK = 'simple_history/auto_backfill';

	/**
	 * Option name for storing backfill status.
	 */
	const STATUS_OPTION = 'simple_history_auto_backfill_status';

	/**
	 * Default limit per post type for auto-backfill.
	 */
	const DEFAULT_LIMIT = 100;

	/**
	 * Fallback post types if get_post_types() is not available.
	 * In practice, get_auto_backfill_post_types() dynamically fetches
	 * all public post types plus attachments.
	 */
	const DEFAULT_POST_TYPES = [ 'post', 'page' ];

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Register cron hook for auto backfill.
		add_action( self::CRON_HOOK, [ $this, 'run_auto_backfill' ] );
	}

	/**
	 * Schedule the auto backfill cron event.
	 *
	 * Called from Setup_Database when plugin is first installed.
	 * Schedules a one-time event to run 60 seconds after install.
	 */
	public static function schedule_auto_backfill() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/**
	 * Run the automatic backfill process.
	 *
	 * This is called by the cron event. It checks if backfill has
	 * already been completed, runs the backfill if not, and stores
	 * the results in an option for display in the admin.
	 */
	public function run_auto_backfill() {
		// Check if already completed.
		$status = get_option( self::STATUS_OPTION );
		if ( $status && isset( $status['completed'] ) && $status['completed'] ) {
			return;
		}

		// Get configurable options via filters.
		$limit      = $this->get_auto_backfill_limit();
		$post_types = $this->get_auto_backfill_post_types();
		$days_back  = Helpers::get_clear_history_interval();

		// Create importer and run backfill.
		$importer = new Existing_Data_Importer( $this->simple_history );
		$results  = $importer->import_all(
			[
				'post_types'   => $post_types,
				'import_users' => true,
				'limit'        => $limit,
				'days_back'    => $days_back,
			]
		);

		// Store status with results.
		$status = [
			'completed'           => true,
			'completed_at'        => current_time( 'mysql', true ),
			'days_back'           => $days_back,
			'posts_imported'      => $results['posts_imported'] ?? 0,
			'users_imported'      => $results['users_imported'] ?? 0,
			'post_events_created' => $results['post_events_created'] ?? 0,
			'user_events_created' => $results['user_events_created'] ?? 0,
			'posts_skipped'       => ( $results['posts_skipped_imported'] ?? 0 ) + ( $results['posts_skipped_logged'] ?? 0 ),
			'users_skipped'       => ( $results['users_skipped_imported'] ?? 0 ) + ( $results['users_skipped_logged'] ?? 0 ),
			'errors'              => $results['errors'] ?? [],
		];

		update_option( self::STATUS_OPTION, $status, false );

		// Fire action for SimpleHistory_Logger to log completion.
		$status['type'] = 'auto';
		do_action( 'simple_history/backfill/completed', $status );
	}

	/**
	 * Get the limit for auto-backfill per post type.
	 *
	 * @return int Number of items to import per type.
	 */
	private function get_auto_backfill_limit() {
		/**
		 * Filter the limit for auto-backfill per post type.
		 *
		 * @param int $limit Default limit (100).
		 */
		return (int) apply_filters( 'simple_history/auto_backfill/limit', self::DEFAULT_LIMIT );
	}

	/**
	 * Get the post types to auto-backfill.
	 *
	 * By default, includes all public post types plus attachments.
	 * This gives new users a rich first impression with posts, pages,
	 * custom post types, and media all visible in the log.
	 *
	 * @return array Array of post type names.
	 */
	private function get_auto_backfill_post_types() {
		// Get all public post types.
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		// Add attachment (not public by default but valuable for visual logs).
		$post_types['attachment'] = 'attachment';

		// Convert to simple array of names.
		$post_types = array_values( $post_types );

		/**
		 * Filter the post types to auto-backfill.
		 *
		 * @param array $post_types Post types to backfill (default: all public + attachment).
		 */
		return (array) apply_filters( 'simple_history/auto_backfill/post_types', $post_types );
	}

	/**
	 * Get the auto-backfill status.
	 *
	 * @return array|false Status array or false if not run yet.
	 */
	public static function get_status() {
		return get_option( self::STATUS_OPTION );
	}

	/**
	 * Check if auto-backfill has been completed.
	 *
	 * @return bool True if completed, false otherwise.
	 */
	public static function is_completed() {
		$status = self::get_status();
		return $status && isset( $status['completed'] ) && $status['completed'];
	}

	/**
	 * Reset the auto-backfill status (useful for testing).
	 */
	public static function reset_status() {
		delete_option( self::STATUS_OPTION );
	}
}
