<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;
use Simple_History\Services\Channels_Settings_Page;

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
			'name_via'    => _x( 'Using plugin Simple History', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'description' => __( 'Logs changes made on the Simple History settings page.', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'modified_settings'               => _x( 'Modified settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'regenerated_rss_feed_secret'     => _x( 'Regenerated RSS feed secret', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'cleared_log'                     => _x( 'Cleared the log for Simple History ({num_rows_deleted} rows were removed)', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'purged_events'                   => _x( 'Removed {num_rows} events that were older than {days} days', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'auto_backfill_completed'         => _x( 'Populated (backfilled) your history with {posts_imported} posts and {users_imported} users from the last {days_back} days', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'manual_backfill_completed'       => _x( 'Manual backfill created {post_events} post events and {user_events} user events', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'channel_auto_disabled'           => _x( 'Log forwarding channel "{channel_name}" was auto-disabled after {failure_count} consecutive failures', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'log_forwarding_settings_updated' => _x( 'Updated Log Forwarding settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
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
		add_action( 'simple_history/db/purge_done', [ $this, 'on_purge_done' ], 10, 2 );
		add_action( 'simple_history/backfill/completed', [ $this, 'on_backfill_completed' ] );
		add_action( 'simple_history/channel/auto_disabled', [ $this, 'on_channel_auto_disabled' ], 10, 2 );
	}

	/**
	 * Log when the purge is done.
	 *
	 * @param int $days Number of days to keep.
	 * @param int $total_rows Total number of rows deleted across all batches.
	 * @return void
	 */
	public function on_purge_done( $days, $total_rows ) {
		// Don't log if no events were purged.
		if ( $total_rows === 0 ) {
			return;
		}

		$this->info_message(
			'purged_events',
			[
				'days'     => $days,
				'num_rows' => $total_rows,
			]
		);
	}

	/**
	 * Log when backfill is completed.
	 *
	 * @param array $status Backfill status containing type, post_events_created, user_events_created, etc.
	 * @return void
	 */
	public function on_backfill_completed( $status ) {
		// Bail if no type set.
		if ( empty( $status['type'] ) ) {
			return;
		}

		$post_events  = $status['post_events_created'] ?? 0;
		$user_events  = $status['user_events_created'] ?? 0;
		$total_events = $post_events + $user_events;

		// Don't log if no events were created.
		if ( $total_events === 0 ) {
			return;
		}

		// Determine message key based on type.
		$message_key = $status['type'] === 'auto'
			? 'auto_backfill_completed'
			: 'manual_backfill_completed';

		$this->info_message(
			$message_key,
			[
				'post_events'    => $post_events,
				'user_events'    => $user_events,
				'posts_imported' => $status['posts_imported'] ?? 0,
				'users_imported' => $status['users_imported'] ?? 0,
				'days_back'      => $status['days_back'] ?? 0,
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
	 * Log when a channel is auto-disabled due to repeated failures.
	 *
	 * @param object $channel       The channel instance that was auto-disabled.
	 * @param int    $failure_count The number of consecutive failures.
	 * @return void
	 */
	public function on_channel_auto_disabled( $channel, $failure_count ) {
		$context = [
			'channel_name'  => $channel->get_name(),
			'channel_slug'  => $channel->get_slug(),
			'failure_count' => $failure_count,
		];

		// Get the last error message if available.
		if ( method_exists( $channel, 'get_setting' ) ) {
			$last_error = $channel->get_setting( 'last_error', [] );
			if ( ! empty( $last_error['message'] ) ) {
				$context['error_message'] = $last_error['message'];
			}
		}

		$this->warning_message( 'channel_auto_disabled', $context );
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
		$option_page = sanitize_text_field( wp_unslash( $_POST['option_page'] ) );

		// Log changes to general settings.
		if ( $option_page === $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP ) {
			// Save all changes.
			add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );

			// Finally, before redirecting back to Simple History options page, log the changes.
			add_filter( 'wp_redirect', [ $this, 'commit_log_on_wp_redirect' ], 10, 2 );
		} elseif ( $option_page === Channels_Settings_Page::SETTINGS_OPTION_GROUP ) {
			// Log changes to Log Forwarding settings.
			add_filter( 'wp_redirect', [ $this, 'log_forwarding_settings_saved' ], 10, 2 );
		}
	}

	/**
	 * Log when Log Forwarding settings are saved.
	 *
	 * @param string $location URL to redirect to.
	 * @param int    $status HTTP status code.
	 * @return string
	 */
	public function log_forwarding_settings_saved( $location, $status ) {
		$this->info_message( 'log_forwarding_settings_updated' );

		return $location;
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
			$context[ "{$option}_new" ]  = $change['new_value'];
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
			// If they already have the plugin, show message with link to settings page.

			if ( ! Helpers::show_promo_boxes() ) {
				$message = sprintf(
					/* translators: 1 is a link to webpage with info about how to modify number of days to keep the log */
					__( '<a href="%1$s">Set number of days the log is kept.</a>', 'simple-history' ),
					esc_url( Helpers::get_settings_page_url() )
				);
			} else {
				$message = sprintf(
				/* translators: 1 is a link to webpage with info about how to modify number of days to keep the log */
					__( '<a href="%1$s" target="_blank" class="sh-ExternalLink">Get Premium to set number of days the log is kept.</a>', 'simple-history' ),
					esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_logger_purged' ) )
				);
			}

			return '<p>' . wp_kses(
				$message,
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'class'  => [],
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
					new Event_Details_Item(
						[ 'detective_mode_enabled' ],
						__( 'Detective Mode enabled', 'simple-history' ),
					),
				]
			)
			->set_title( __( 'Changed items', 'simple-history' ) );

		return $event_details_group;
	}
}
