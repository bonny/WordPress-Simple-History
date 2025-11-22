<?php

namespace Simple_History\Services;

use Simple_History\Dropins\Import_Dropin;
use Simple_History\Dropins\Tools_Menu_Dropin;
use Simple_History\Existing_Data_Importer;
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
	 * Register the service.
	 */
	public function loaded() {
		// Hook into admin-post to handle import form submissions.
		add_action( 'admin_post_' . self::ACTION_NAME, [ $this, 'handle' ] );

		// Hook into admin-post to handle delete requests.
		add_action( 'admin_post_' . self::DELETE_ACTION_NAME, [ $this, 'handle_delete' ] );
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

		// Get import options from form.
		$import_post_types = isset( $_POST['import_post_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['import_post_types'] ) ) : [];
		$import_users      = isset( $_POST['import_users'] ) && sanitize_text_field( wp_unslash( $_POST['import_users'] ) ) === '1';

		// Check if import limit is enabled.
		$enable_limit = isset( $_POST['enable_import_limit'] ) && sanitize_text_field( wp_unslash( $_POST['enable_import_limit'] ) ) === '1';

		if ( $enable_limit ) {
			$import_limit = isset( $_POST['import_limit'] ) ? intval( $_POST['import_limit'] ) : 100;
			// Validate limit.
			$import_limit = max( 1, min( 10000, $import_limit ) );
		} else {
			// No limit - import all data.
			$import_limit = -1;
		}

		// Create importer instance.
		$importer = new Existing_Data_Importer( $this->simple_history );

		// Run import.
		$results = $importer->import_all(
			[
				'post_types'   => $import_post_types,
				'import_users' => $import_users,
				'limit'        => $import_limit,
			]
		);

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

		// Create importer instance.
		$importer = new Existing_Data_Importer( $this->simple_history );

		// Delete all imported events.
		$results = $importer->delete_all_imported();

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
}
