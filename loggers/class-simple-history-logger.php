<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs changes made on the Simple History settings page.
 */
class Simple_History_Logger extends Logger {
	/** @var string Logger slug */
	protected $slug = 'SimpleHistoryLogger';

	/** @var array<int,array<string,string>> Found changes */
	private $arr_found_changes = [];

	/**
	 * Get info about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => _x( 'Simple History Logger', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'name_via'   => _x( 'Using plugin Simple History', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'description' => __( 'Logs changes made on the Simple History settings page.', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'modified_settings' => _x( 'Modified settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'regenerated_rss_feed_secret' => _x( 'Regenerated RSS feed secret', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'cleared_log' => _x( 'Cleared the log for Simple History ({num_rows_deleted} rows were removed)', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'purged_events' => _x( 'Removed {num_rows} events that were older than {days} days', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			),
		];
	}

	/**
	 * Called when service is loaded.
	 *
	 * @return void
	 */
	public function loaded() {
		add_action( 'load-options.php', [ $this, 'on_load_options_page' ] );
		add_action( 'simple_history/rss_feed/secret_updated', [ $this, 'on_rss_feed_secret_updated' ] );
		add_action( 'simple_history/settings/log_cleared', [ $this, 'on_log_cleared' ] );
		add_action( 'simple_history/db/events_purged', [ $this, 'on_events_purged' ], 10, 2 );
	}

	/**
	 * Log when events are purged.
	 *
	 * @param int $days Number of days to keep.
	 * @param int $num_rows_deleted Number of rows deleted.
	 * @return void
	 */
	public function on_events_purged( $days, $num_rows_deleted ) {
		$this->info_message(
			'purged_events',
			[
				'days' => $days,
				'num_rows' => $num_rows_deleted,
			]
		);
	}

	/**
	 * Log when the log is cleared.
	 *
	 * @param int $num_rows_deleted Number of rows deleted.
	 * @return void
	 */
	public function on_log_cleared( $num_rows_deleted ) {
		$this->info_message(
			'cleared_log',
			[
				'num_rows_deleted' => $num_rows_deleted,
			]
		);
	}

	/**
	 * When Simple History settings is saved a POST request is made to
	 * options.php. We hook into that request and log the changes.
	 *
	 * @return void
	 */
	public function on_load_options_page() {
		// Bail if option_page does not exist in $_POST variable.
		// This happens when visiting /wp-admin/options.php directly.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['option_page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( $_POST['option_page'] === $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP ) {
			// Save all changes.
			add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );

			// Finally, before redirecting back to Simple History options page, log the changes.
			add_filter( 'wp_redirect', [ $this, 'commit_log_on_wp_redirect' ], 10, 2 );
		}
	}

	/**
	 * Log when the RSS feed secret is updated.
	 *
	 * @return void
	 */
	public function on_rss_feed_secret_updated() {
		$this->info_message( 'regenerated_rss_feed_secret' );
	}

	/**
	 * Log found changes made on the Simple History settings page.
	 *
	 * @param string $location URL to redirect to.
	 * @param int    $status HTTP status code.
	 * @return string
	 */
	public function commit_log_on_wp_redirect( $location, $status ) {
		if ( count( $this->arr_found_changes ) === 0 ) {
			return $location;
		}

		$context = [];

		foreach ( $this->arr_found_changes as $change ) {
			$option = $change['option'];

			// Remove 'simple_history_' from beginning of string.
			$option = preg_replace( '/^simple_history_/', '', $option );

			$context[ "{$option}_prev" ] = $change['old_value'];
			$context[ "{$option}_new" ] = $change['new_value'];
		}

		$this->info_message( 'modified_settings', $context );

		return $location;
	}

	/**
	 * Store all changed options in one array.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public function on_updated_option( $option, $old_value, $new_value ) {
		$this->arr_found_changes[] = [
			'option'    => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
		];
	}

	/**
	 * Get the log row details for this logger.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {

		$message_key = $row->context_message_key;

		if ( $message_key === 'purged_events' ) {
			// For message "Removed 24318 events that were older than 60 days"
			// add a text with a link with information on how to modify this.
			$message = sprintf(
				/* translators: 1 is a link to webpage with info about how to modify number of days to keep the log */
				__( 'The number of days the log is kept can be changed using a filter or an add-on. <a href="%1$s" target="_blank" class="sh-ExternalLink">More info.</a>', 'simple-history' ),
				esc_url( 'https://simple-history.com/support/change-number-of-days-to-keep-log/?utm_source=wpadmin' )
			);

			return '<p>' . wp_kses(
				$message,
				[
					'a' => [
						'href' => [],
						'target' => [],
						'class' => [],
					],
				]
			) . '</p>';
		}

		$event_details_group = ( new Event_Details_Group() )
			->add_items(
				[
					new Event_Details_Item(
						[ 'show_on_dashboard' ],
						__( 'Show on dashboard', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'show_as_page' ],
						__( 'Show as a page', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'pager_size' ],
						__( 'Items on page', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'pager_size_dashboard' ],
						__( 'Items on dashboard', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'enable_rss_feed' ],
						__( 'RSS feed enabled', 'simple-history' ),
					),
				]
			)
			->set_title( __( 'Changed items', 'simple-history' ) );

		return $event_details_group;
	}
}
