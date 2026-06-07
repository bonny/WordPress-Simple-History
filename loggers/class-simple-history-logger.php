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

	/** @var array<string,string>|null Cached map of tracked option name => label. */
	private $tracked_settings = null;

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
				'channel_auto_disabled'           => _x( 'Auto-disabled log forwarding channel "{channel_name}" after {failure_count} consecutive failures', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'log_forwarding_settings_updated' => _x( 'Updated Log Forwarding settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Simple History', 'Simple History logger: search', 'simple-history' ),
					'label_all' => _x( 'All Simple History activity', 'Simple History logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Settings changes', 'Simple History logger: search', 'simple-history' ) => array(
							'modified_settings',
							'regenerated_rss_feed_secret',
							'log_forwarding_settings_updated',
						),
						_x( 'Log maintenance', 'Simple History logger: search', 'simple-history' ) => array(
							'cleared_log',
							'purged_events',
						),
						_x( 'Backfill operations', 'Simple History logger: search', 'simple-history' ) => array(
							'auto_backfill_completed',
							'manual_backfill_completed',
						),
						_x( 'Channel events', 'Simple History logger: search', 'simple-history' ) => array(
							'channel_auto_disabled',
						),
					),
				),
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
	 * Get the map of option keys that should be logged when changed.
	 *
	 * Keyed by full option name, value is a human-readable label.
	 * Add-ons contribute their own keys via the
	 * `simple_history/settings/tracked_options` filter.
	 *
	 * @param bool $force_rebuild Rebuild the cached map (used in tests).
	 * @return array<string,string>
	 */
	public function get_tracked_settings( $force_rebuild = false ) {
		if ( $this->tracked_settings !== null && ! $force_rebuild ) {
			return $this->tracked_settings;
		}

		$core_settings = [
			'simple_history_show_on_dashboard'      => __( 'Show on dashboard', 'simple-history' ),
			'simple_history_show_as_page'           => __( 'Show as a page', 'simple-history' ),
			'simple_history_pager_size'             => __( 'Items on page', 'simple-history' ),
			'simple_history_pager_size_dashboard'   => __( 'Items on dashboard', 'simple-history' ),
			'simple_history_enable_rss_feed'        => __( 'RSS feed enabled', 'simple-history' ),
			'simple_history_detective_mode_enabled' => __( 'Detective Mode enabled', 'simple-history' ),
			'simple_history_menu_page_location'     => __( 'Menu page location', 'simple-history' ),
			'simple_history_show_in_admin_bar'      => __( 'Show in admin bar', 'simple-history' ),
		];

		/**
		 * Filter the map of option keys that Simple History logs when changed.
		 *
		 * Add-ons use this to have their own settings logged as
		 * "Modified settings" via the Simple History logger.
		 *
		 * @param array<string,string> $settings Map of option name => human label.
		 */
		$this->tracked_settings = apply_filters( 'simple_history/settings/tracked_options', $core_settings );

		return $this->tracked_settings;
	}

	/**
	 * Get the list of tracked option names whose values must not be stored
	 * in the log (e.g. secrets/API keys). Their change is logged, but the
	 * value is replaced with a placeholder.
	 *
	 * @return array<int,string>
	 */
	public function get_redacted_settings() {
		/**
		 * Filter the list of tracked option names whose values are redacted in the log.
		 *
		 * @param array<int,string> $option_names List of option names to redact.
		 */
		return apply_filters( 'simple_history/settings/redacted_options', [] );
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
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		// TODO(issue-232): the hardcoded item labels below are superseded by the
		// tracked-options map in Task 3's generic renderer; remove the duplication then.
		$message_key = $row->context_message_key;

		if ( $message_key === 'purged_events' ) {
			// Add a text with a link with information on how to modify retention.
			if ( Helpers::is_premium_add_on_active() ) {
				$message = sprintf(
					/* translators: 1 is a link to the settings page retention setting */
					__( '<a href="%1$s">Set number of days the log is kept.</a>', 'simple-history' ),
					esc_url( Helpers::get_settings_page_url() . '#simple-history-premium-settings' )
				);
			} else {
				$message = sprintf(
				/* translators: 1 is a link to webpage with info about how to modify number of days to keep the log */
					__( '<a href="%1$s" target="_blank" class="sh-ExternalLink">Set number of days the log is kept (Premium).</a>', 'simple-history' ),
					esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_logger_purged' ) )
				);
			}

			$html_output = '<p>' . wp_kses(
				$message,
				[
					'a' => [
						'href'   => [],
						'target' => [],
						'class'  => [],
					],
				]
			) . '</p>';

			return Event_Details_Group::create_raw(
				$html_output,
				[
					'type'    => 'retention_link',
					'content' => Helpers::is_premium_add_on_active()
						? Helpers::get_settings_page_url() . '#simple-history-premium-settings'
						: Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_logger_purged' ),
				]
			);
		}

		return ( new Event_Details_Group() )
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
					new Event_Details_Item(
						[ 'menu_page_location' ],
						__( 'Menu page location', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'show_in_admin_bar' ],
						__( 'Show in admin bar', 'simple-history' ),
					),
				]
			)
			->set_title( __( 'Changed items', 'simple-history' ) );
	}
}
