<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs changes made on the Simple History settings page.
 *
 * To begin with the following changes will be logged:
 *
 * - RSS feed enable and secret update
 * - Show history on dashboard, as a page
 * - Number of items to show on page, dashboard
 * - Clear log
 */
class Simple_History_Logger extends Logger {
	protected $slug = 'SimpleHistoryLogger';

	/** @var array<int,array<string,string>> Found changes */
	private $arr_found_changes = [];

	public function get_info() {
		return [
			'name'        => _x( 'Simple History Logger', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'description' => __( 'Logs changes made on the Simple History settings page.', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'modified_settings' => _x( 'Modified settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			),
		];
	}

	public function loaded() {
		add_action( 'load-options.php', [ $this, 'on_load_options_page' ] );
	}

	/**
	 * When Simple History settings is saved a POST request is made to
	 * options.php. We hook into that request and log the changes.
	 */
	public function on_load_options_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $_POST['option_page'] === $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP ) {
			// Save all changes.
			add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );

			// Finally, before redirecting back to Simple History options page, log the changes.
			add_filter( 'wp_redirect', [ $this, 'commit_log_on_wp_redirect' ], 10, 2 );
		}
	}

	/**
	 * Log found changes made on the Simple History settings page.
	 *
	 * @param string $location
	 * @param int $status
	 * @return string
	 */
	public function commit_log_on_wp_redirect( $location, $status ) {
		if ( count( $this->arr_found_changes ) === 0 ) {
			return $location;
		}

		// Here make new_value be not NULL but 0. Need sanitize for save functions.
		var_dump($this->arr_found_changes);exit;

		$context = [];

		foreach ( $this->arr_found_changes as $change ) {
			$option = $change['option'];

			// Remove 'simple_history_' from beginning of string using preg_replace.
			$option = preg_replace( '/^simple_history_/', '', $option );

			$context[ "{$option}_prev" ] = $change['old_value'];
			$context[ "{$option}_new" ] = $change['new_value'];
		}

		$this->info_message( 'modified_settings', $context );

		return $location;
	}

	/*
		$action = 'update';

		[simple_history_settings_group] => Array
		(
			[0] => simple_history_show_on_dashboard
			[1] => simple_history_show_as_page
			[2] => simple_history_pager_size
			[3] => simple_history_pager_size_dashboard
			[4] => simple_history_enable_rss_feed
		)
	*/

	// echo "admin_action_{$action}";exit; // admin_action_update
	// echo "load-{$pagenow}"; // load-options.php

	public function on_updated_option( $option, $old_value, $new_value ) {
		$this->arr_found_changes[] = [
			'option'    => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
		];
	}
}
