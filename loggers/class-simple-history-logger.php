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

	/** @var array<string,array{old?:mixed,new?:mixed,changed_only?:bool}> Accumulated settings changes, keyed by option name. */
	private $settings_changes = [];

	/** @var array<string,string>|null Cached map of tracked option name => label. */
	private $tracked_settings = null;

	/** @var array<int,string>|null Cached list of redacted option names. */
	private $redacted_settings = null;

	/** @var array<int,string>|null Cached list of changed-only option names. */
	private $changed_only_settings = null;

	/** @var array<string,mixed> Snapshot of tracked option values captured before deletion. */
	private $deleted_option_values = [];

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

		// Watch tracked settings (core + add-ons) across every save mechanism.
		add_action( 'updated_option', [ $this, 'on_tracked_option_updated' ], 10, 3 );
		add_action( 'added_option', [ $this, 'on_tracked_option_added' ], 10, 2 );
		add_action( 'delete_option', [ $this, 'on_tracked_option_pre_delete' ] );
		add_action( 'deleted_option', [ $this, 'on_tracked_option_deleted' ], 10, 1 );
		add_action( 'shutdown', [ $this, 'commit_settings_changes' ] );
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
	 * @param bool $force_rebuild Rebuild the cached list (used in tests).
	 * @return array<int,string>
	 */
	public function get_redacted_settings( $force_rebuild = false ) {
		if ( $this->redacted_settings !== null && ! $force_rebuild ) {
			return $this->redacted_settings;
		}

		/**
		 * Filter the list of tracked option names whose values are redacted in the log.
		 *
		 * @param array<int,string> $option_names List of option names to redact.
		 */
		$this->redacted_settings = apply_filters( 'simple_history/settings/redacted_options', [] );

		return $this->redacted_settings;
	}

	/**
	 * Get the list of tracked option names that are logged as "changed" without
	 * storing their before/after value (for large or structured settings).
	 *
	 * @param bool $force_rebuild Rebuild the cached list (used in tests).
	 * @return array<int,string>
	 */
	public function get_changed_only_settings( $force_rebuild = false ) {
		if ( $this->changed_only_settings !== null && ! $force_rebuild ) {
			return $this->changed_only_settings;
		}

		/**
		 * Filter the list of tracked option names logged as "changed" without
		 * storing their before/after value.
		 *
		 * Use this for large or structured settings (e.g. arrays of rules) whose
		 * raw value would be unreadable and bloat the log.
		 *
		 * @param array<int,string> $option_names List of option names.
		 */
		$this->changed_only_settings = apply_filters( 'simple_history/settings/changed_only_options', [] );

		return $this->changed_only_settings;
	}

	/**
	 * Whether an option should be logged as "changed" without its value.
	 *
	 * True when the option is explicitly registered as changed-only, or when
	 * either value is non-scalar (safety net so structured values are never
	 * serialized into the log).
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return bool
	 */
	private function is_changed_only_setting( $option, $old_value, $new_value ) {
		if ( in_array( $option, $this->get_changed_only_settings(), true ) ) {
			return true;
		}

		// Treat null like a scalar (get_option() may yield null/false); only
		// real structured values (arrays/objects) trigger the safety net.
		$old_is_simple = is_scalar( $old_value ) || is_null( $old_value );
		$new_is_simple = is_scalar( $new_value ) || is_null( $new_value );

		return ! $old_is_simple || ! $new_is_simple;
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

		// Only the Channels (log forwarding) settings are handled here; other
		// settings are captured by the global option watcher.
		if ( $option_page !== Channels_Settings_Page::SETTINGS_OPTION_GROUP ) {
			return;
		}

		add_filter( 'wp_redirect', [ $this, 'log_forwarding_settings_saved' ], 10, 2 );
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
	 * Record a changed tracked option.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public function on_tracked_option_updated( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		if ( $this->is_changed_only_setting( $option, $old_value, $new_value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => $this->prepare_setting_value( $option, $old_value ),
			'new' => $this->prepare_setting_value( $option, $new_value ),
		];
	}

	/**
	 * Record a newly added tracked option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function on_tracked_option_added( $option, $value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		// No prior value on add; use the new value for both scalarity checks.
		if ( $this->is_changed_only_setting( $option, $value, $value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => '',
			'new' => $this->prepare_setting_value( $option, $value ),
		];
	}

	/**
	 * Snapshot a tracked option's value before it is deleted.
	 *
	 * `deleted_option` does not provide the previous value, so capture it here.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public function on_tracked_option_pre_delete( $option ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$this->deleted_option_values[ $option ] = get_option( $option );
	}

	/**
	 * Record a deleted tracked option.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public function on_tracked_option_deleted( $option ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$old_value = array_key_exists( $option, $this->deleted_option_values )
			? $this->deleted_option_values[ $option ]
			: '';

		if ( $this->is_changed_only_setting( $option, $old_value, $old_value ) ) {
			$this->settings_changes[ $option ] = [
				'changed_only' => true,
				'new'          => __( '(deleted)', 'simple-history' ),
			];
		} else {
			$this->settings_changes[ $option ] = [
				'old' => $this->prepare_setting_value( $option, $old_value ),
				'new' => __( '(deleted)', 'simple-history' ),
			];
		}

		unset( $this->deleted_option_values[ $option ] );
	}

	/**
	 * Prepare an option value for storage in the log.
	 *
	 * Redacts sensitive options and stringifies non-scalar values.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Raw value.
	 * @return mixed
	 */
	private function prepare_setting_value( $option, $value ) {
		if ( in_array( $option, $this->get_redacted_settings(), true ) ) {
			return __( '(value hidden)', 'simple-history' );
		}

		if ( is_scalar( $value ) || $value === null ) {
			return $value;
		}

		$encoded = wp_json_encode( $value );

		return $encoded ? $encoded : __( '(non-serializable value)', 'simple-history' );
	}

	/**
	 * Commit all accumulated settings changes as one event.
	 *
	 * Hooked to `shutdown` so it runs regardless of how the save happened
	 * (Settings API, direct update_option, or REST).
	 *
	 * @return void
	 */
	public function commit_settings_changes() {
		if ( count( $this->settings_changes ) === 0 ) {
			return;
		}

		$context = [];

		foreach ( $this->settings_changes as $option => $change ) {
			$base = $this->get_setting_context_base( $option );

			if ( ! empty( $change['changed_only'] ) ) {
				$context[ "{$base}_new" ] = $change['new'] ?? __( '(changed)', 'simple-history' );

				continue;
			}

			$context[ "{$base}_prev" ] = $change['old'];
			$context[ "{$base}_new" ]  = $change['new'];
		}

		$this->info_message( 'modified_settings', $context );

		$this->settings_changes = [];
	}

	/**
	 * Get the context-key base for an option name.
	 *
	 * Strips the `simple_history_` prefix so core keys keep their historical
	 * short context names (e.g. `show_on_dashboard`). Add-on option names that
	 * do not start with `simple_history_` are used as-is; avoid registering an
	 * option name that collides with a core key once the prefix is stripped.
	 *
	 * @param string $option Option name.
	 * @return string
	 */
	private function get_setting_context_base( $option ) {
		return preg_replace( '/^simple_history_/', '', $option );
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
	 * Get the log row details for this logger.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
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

		// The generic settings renderer below only applies to settings changes.
		// Other message keys (e.g. cleared_log, backfill) have no changed-items detail.
		if ( $message_key !== 'modified_settings' ) {
			return '';
		}

		$context = isset( $row->context ) && is_array( $row->context ) ? $row->context : [];

		// Build a base => label lookup from the tracked-options map.
		$labels = [];
		foreach ( $this->get_tracked_settings() as $option => $label ) {
			$labels[ $this->get_setting_context_base( $option ) ] = $label;
		}

		$group       = new Event_Details_Group();
		$items       = [];
		$bases_added = [];

		foreach ( array_keys( $context ) as $key ) {
			if ( substr( $key, -4 ) === '_new' ) {
				$base = substr( $key, 0, -4 );
			} elseif ( substr( $key, -5 ) === '_prev' ) {
				$base = substr( $key, 0, -5 );
			} else {
				continue;
			}

			if ( isset( $bases_added[ $base ] ) ) {
				continue;
			}

			$bases_added[ $base ] = true;

			$label   = $labels[ $base ] ?? $base;
			$items[] = new Event_Details_Item( [ $base ], $label );
		}

		$group->add_items( $items );
		$group->set_title( __( 'Changed items', 'simple-history' ) );

		return $group;
	}
}
