<?php

namespace Simple_History\Services;

use Simple_History\Dropins\Import_Dropin;
use Simple_History\Dropins\Tools_Menu_Dropin;
use Simple_History\Existing_Data_Importer;
use Simple_History\Helpers;
use Simple_History\Services\Auto_Backfill_Service;
use Simple_History\Services\Service;

/**
 * Handles import form submissions via WordPress admin-post hook.
 *
 * This class is responsible for processing import requests submitted
 * from the Import Tools page. It follows WordPress best practices
 * by using the admin-post.php routing system to separate business logic
 * from UI rendering.
 */
class Import_Handler extends Service {
	/**
	 * Action name for the admin post hook.
	 */
	const ACTION_NAME = 'simple_history_import_existing_data';

	/**
	 * Action name for deleting imported data.
	 */
	const DELETE_ACTION_NAME = 'simple_history_delete_imported_data';

	/**
	 * Action name for re-running auto backfill.
	 */
	const RERUN_ACTION_NAME = 'simple_history_rerun_auto_backfill';

	/**
	 * Option name for storing manual backfill status.
	 */
	const MANUAL_STATUS_OPTION = 'simple_history_manual_backfill_status';

	/**
	 * Register the service.
	 */
	public function loaded() {
		// Hook into admin-post to handle import form submissions.
		add_action( 'admin_post_' . self::ACTION_NAME, [ $this, 'handle' ] );

		// Hook into admin-post to handle delete requests.
		add_action( 'admin_post_' . self::DELETE_ACTION_NAME, [ $this, 'handle_delete' ] );

		// Hook into admin-post to handle re-run auto backfill requests.
		add_action( 'admin_post_' . self::RERUN_ACTION_NAME, [ $this, 'handle_rerun' ] );
	}

	/**
	 * Handle the import request.
	 *
	 * Validates the request, processes the import, and redirects back
	 * to the Import Tools page with results as URL parameters.
	 */
	public function handle() {
		// Verify nonce.
		if ( ! isset( $_POST['simple_history_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_history_import_nonce'] ) ), self::ACTION_NAME ) ) {
			wp_die( esc_html__( 'Security check failed', 'simple-history' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'simple-history' ) );
		}

		// Check if manual import is enabled (premium feature).
		/**
		 * Filter to enable manual backfill functionality.
		 *
		 * By default, manual backfill is disabled in the free version.
		 * Premium add-on enables this by returning true.
		 *
		 * @param bool $can_run Whether manual import can be run. Default false.
		 */
		$can_run_manual_import = apply_filters( 'simple_history/backfill/can_run_manual_import', false );

		if ( ! $can_run_manual_import ) {
			wp_die( esc_html__( 'Manual backfill requires Simple History Premium.', 'simple-history' ) );
		}

		// Get import options from form.
		$import_post_types = isset( $_POST['import_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['import_post_types'] ) ) : [];
		$import_users      = isset( $_POST['import_users'] ) && sanitize_text_field( wp_unslash( $_POST['import_users'] ) ) === '1';

		// No item limit - import all matching data.
		$import_limit = -1;

		// Check if "All time" is selected.
		$date_range_type = isset( $_POST['date_range_type'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range_type'] ) ) : 'specific';

		if ( $date_range_type === 'all_time' ) {
			// All time - no date limit.
			$days_back = -1;
		} else {
			// Get date range from value + unit inputs.
			$date_range_value = isset( $_POST['date_range_value'] ) ? intval( $_POST['date_range_value'] ) : 0;
			$date_range_unit  = isset( $_POST['date_range_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range_unit'] ) ) : 'days';

			// Convert to days.
			if ( $date_range_value <= 0 ) {
				// Use default from retention setting.
				$days_back = null;
			} else {
				switch ( $date_range_unit ) {
					case 'months':
						$days_back = $date_range_value * 30;
						break;
					case 'years':
						$days_back = $date_range_value * 365;
						break;
					case 'days':
					default:
						$days_back = $date_range_value;
						break;
				}
			}
		}

		// Create importer instance.
		$importer = new Existing_Data_Importer( $this->simple_history );

		// Run import.
		$results = $importer->import_all(
			[
				'post_types'   => $import_post_types,
				'import_users' => $import_users,
				'limit'        => $import_limit,
				'days_back'    => $days_back,
			]
		);

		// Save manual backfill status for persistent display.
		// Store the original date range settings for accurate display.
		$date_range_value = isset( $_POST['date_range_value'] ) ? intval( $_POST['date_range_value'] ) : 0;
		$date_range_unit  = isset( $_POST['date_range_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range_unit'] ) ) : 'days';

		$manual_status = [
			'completed'              => true,
			'completed_at'           => current_time( 'mysql', true ),
			'posts_imported'         => isset( $results['posts_imported'] ) ? intval( $results['posts_imported'] ) : 0,
			'users_imported'         => isset( $results['users_imported'] ) ? intval( $results['users_imported'] ) : 0,
			'post_events_created'    => isset( $results['post_events_created'] ) ? intval( $results['post_events_created'] ) : 0,
			'user_events_created'    => isset( $results['user_events_created'] ) ? intval( $results['user_events_created'] ) : 0,
			'posts_skipped_imported' => isset( $results['posts_skipped_imported'] ) ? intval( $results['posts_skipped_imported'] ) : 0,
			'posts_skipped_logged'   => isset( $results['posts_skipped_logged'] ) ? intval( $results['posts_skipped_logged'] ) : 0,
			'users_skipped_imported' => isset( $results['users_skipped_imported'] ) ? intval( $results['users_skipped_imported'] ) : 0,
			'users_skipped_logged'   => isset( $results['users_skipped_logged'] ) ? intval( $results['users_skipped_logged'] ) : 0,
			'days_back'              => $days_back ?? Helpers::get_clear_history_interval(),
			'date_range_type'        => $date_range_type,
			'date_range_value'       => $date_range_value,
			'date_range_unit'        => $date_range_unit,
		];
		update_option( self::MANUAL_STATUS_OPTION, $manual_status );

		// Fire action for SimpleHistory_Logger to log completion.
		$manual_status['type'] = 'manual';
		do_action( 'simple_history/backfill/completed', $manual_status );

		// Redirect back to the page with results as URL parameters.
		// Use the proper tab structure for the Tools menu.
		$redirect_url = add_query_arg(
			[
				'page'                   => Tools_Menu_Dropin::MENU_SLUG,
				'selected-tab'           => Tools_Menu_Dropin::TOOLS_TAB_SLUG,
				'selected-sub-tab'       => Import_Dropin::MENU_SLUG,
				'import-completed'       => '1',
				'posts-imported'         => isset( $results['posts_imported'] ) ? intval( $results['posts_imported'] ) : 0,
				'users-imported'         => isset( $results['users_imported'] ) ? intval( $results['users_imported'] ) : 0,
				'post-events-created'    => isset( $results['post_events_created'] ) ? intval( $results['post_events_created'] ) : 0,
				'user-events-created'    => isset( $results['user_events_created'] ) ? intval( $results['user_events_created'] ) : 0,
				'posts-skipped-imported' => isset( $results['posts_skipped_imported'] ) ? intval( $results['posts_skipped_imported'] ) : 0,
				'posts-skipped-logged'   => isset( $results['posts_skipped_logged'] ) ? intval( $results['posts_skipped_logged'] ) : 0,
				'users-skipped-imported' => isset( $results['users_skipped_imported'] ) ? intval( $results['users_skipped_imported'] ) : 0,
				'users-skipped-logged'   => isset( $results['users_skipped_logged'] ) ? intval( $results['users_skipped_logged'] ) : 0,
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle the delete imported data request.
	 *
	 * Validates the request, deletes all imported events, and redirects back
	 * to the Import Tools page with results as URL parameters.
	 *
	 * Note: Delete is only available when dev mode is enabled.
	 */
	public function handle_delete() {
		// Verify nonce.
		if ( ! isset( $_POST['simple_history_delete_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_history_delete_nonce'] ) ), self::DELETE_ACTION_NAME ) ) {
			wp_die( esc_html__( 'Security check failed', 'simple-history' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'simple-history' ) );
		}

		// Check if delete is allowed (dev mode only).
		if ( ! Helpers::dev_mode_is_enabled() ) {
			wp_die( esc_html__( 'Delete backfilled data requires dev mode to be enabled.', 'simple-history' ) );
		}

		// Create importer instance.
		$importer = new Existing_Data_Importer( $this->simple_history );

		// Delete all imported events.
		$results = $importer->delete_all_imported();

		// Update the auto-backfill status to record deletion.
		$status = get_option( Auto_Backfill_Service::STATUS_OPTION );
		if ( $status ) {
			$status['deleted_at']     = current_time( 'mysql', true );
			$status['events_deleted'] = $results['events_deleted'];
			update_option( Auto_Backfill_Service::STATUS_OPTION, $status );
		}

		// Redirect back to the page with results as URL parameters.
		// Use the proper tab structure for the Tools menu.
		$redirect_url = add_query_arg(
			[
				'page'             => Tools_Menu_Dropin::MENU_SLUG,
				'selected-tab'     => Tools_Menu_Dropin::TOOLS_TAB_SLUG,
				'selected-sub-tab' => Import_Dropin::MENU_SLUG,
				'delete-completed' => '1',
				'events-deleted'   => isset( $results['events_deleted'] ) ? intval( $results['events_deleted'] ) : 0,
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle the re-run auto backfill request.
	 *
	 * Resets the auto-backfill status and schedules the cron event to run again.
	 *
	 * Note: Re-run is only available when dev mode is enabled.
	 */
	public function handle_rerun() {
		// Verify nonce.
		if ( ! isset( $_POST['simple_history_rerun_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_history_rerun_nonce'] ) ), self::RERUN_ACTION_NAME ) ) {
			wp_die( esc_html__( 'Security check failed', 'simple-history' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action', 'simple-history' ) );
		}

		// Check if re-run is allowed (dev mode only).
		if ( ! Helpers::dev_mode_is_enabled() ) {
			wp_die( esc_html__( 'Re-running auto backfill requires dev mode to be enabled.', 'simple-history' ) );
		}

		// Reset the auto-backfill status.
		Auto_Backfill_Service::reset_status();

		// Schedule the auto-backfill to run.
		Auto_Backfill_Service::schedule_auto_backfill();

		// Redirect back to the page with success message.
		$redirect_url = add_query_arg(
			[
				'page'             => Tools_Menu_Dropin::MENU_SLUG,
				'selected-tab'     => Tools_Menu_Dropin::TOOLS_TAB_SLUG,
				'selected-sub-tab' => Import_Dropin::MENU_SLUG,
				'rerun-scheduled'  => '1',
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
