<?php

namespace Simple_History\Services;

/**
 * Tracks when an Action Scheduler action is executing, so events logged by
 * its callback can be attributed to WordPress instead of to whoever is
 * logged in.
 *
 * Action Scheduler (bundled with WooCommerce and many other plugins) runs
 * queued actions from WP-Cron, and also from an async admin-ajax loopback
 * that it starts at the end of an admin page load. That loopback forwards the
 * browser's cookies and does not define DOING_CRON, so without this tracker
 * a scheduled action looks like the browsing administrator did it.
 *
 * The one context that is a person's action is "Admin List Table": an
 * administrator clicking "Run" on Tools → Scheduled Actions. Those events keep
 * the user.
 */
class Action_Scheduler_Tracker extends Service {
	/**
	 * Context Action Scheduler passes when an admin runs an action by hand.
	 */
	const ADMIN_LIST_TABLE_CONTEXT = 'Admin List Table';

	/**
	 * Execution context of the action running right now, or null when none is.
	 *
	 * Static because loggers ask about the request, not about a service instance.
	 *
	 * @var string|null
	 */
	private static $running_context = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Priority 0 so the flag is set before other plugins' callbacks on the
		// same hooks log anything.
		add_action( 'action_scheduler_before_execute', [ $this, 'on_before_execute' ], 0, 2 );

		// Every way process_action() can finish. Older Action Scheduler versions
		// lack some of these hooks; the next before_execute or the end of the
		// queue run resets the flag in that case.
		add_action( 'action_scheduler_after_execute', [ $this, 'on_execute_finished' ], 0, 0 );
		add_action( 'action_scheduler_failed_execution', [ $this, 'on_execute_finished' ], 0, 0 );
		add_action( 'action_scheduler_failed_validation', [ $this, 'on_execute_finished' ], 0, 0 );
		add_action( 'action_scheduler_execution_ignored', [ $this, 'on_execute_finished' ], 0, 0 );
		add_action( 'action_scheduler_canceled_corrupted_action', [ $this, 'on_execute_finished' ], 0, 0 );
		add_action( 'action_scheduler_after_process_queue', [ $this, 'on_execute_finished' ], 0, 0 );
	}

	/**
	 * Remember that an action has started executing.
	 *
	 * @param int    $action_id Action ID. Unused.
	 * @param string $context   Execution context, e.g. "WP Cron", "Async Request", "Admin List Table".
	 */
	public function on_before_execute( $action_id, $context = '' ) {
		self::$running_context = is_string( $context ) ? $context : '';
	}

	/**
	 * Forget the running action.
	 */
	public function on_execute_finished() {
		self::$running_context = null;
	}

	/**
	 * Whether a scheduled action is executing that no person started.
	 *
	 * @return bool
	 */
	public static function is_running_scheduled_action() {
		if ( self::$running_context === null ) {
			return false;
		}

		return self::$running_context !== self::ADMIN_LIST_TABLE_CONTEXT;
	}
}
