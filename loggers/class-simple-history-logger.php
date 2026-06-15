<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Services\Channels_Settings_Page;
use Simple_History\Services\Licences_Settings_Page;

/**
 * Logs changes made on the Simple History settings page.
 */
class Simple_History_Logger extends Logger {
	/** @var string Logger slug */
	protected $slug = 'SimpleHistoryLogger';

	/** @var int Max string length of a stored setting value; longer values are logged as changed-only. */
	private const MAX_STORED_VALUE_LENGTH = 500;

	/** @var array<string,array{old?:mixed,new?:mixed,changed_only?:bool,deleted?:bool}> Accumulated settings changes, keyed by option name. Values are raw; redaction happens at commit. */
	private $settings_changes = [];

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
	 * The result is intentionally not cached: the watcher runs for every
	 * option write on the site, and caching would silently lock out add-ons
	 * that register their filter after the first option write of the request.
	 *
	 * @return array<string,string>
	 */
	public function get_tracked_settings() {
		$core_settings = [
			'simple_history_show_on_dashboard'             => __( 'Show on dashboard', 'simple-history' ),
			'simple_history_show_as_page'                  => __( 'Show as a page', 'simple-history' ),
			'simple_history_pager_size'                    => __( 'Items on page', 'simple-history' ),
			'simple_history_pager_size_dashboard'          => __( 'Items on dashboard', 'simple-history' ),
			'simple_history_enable_rss_feed'               => __( 'RSS feed enabled', 'simple-history' ),
			'simple_history_detective_mode_enabled'        => __( 'Detective Mode enabled', 'simple-history' ),
			'simple_history_menu_page_location'            => __( 'Menu page location', 'simple-history' ),
			'simple_history_show_in_admin_bar'             => __( 'Show in admin bar', 'simple-history' ),
			'simple_history_experimental_features_enabled' => __( 'Experimental features enabled', 'simple-history' ),
			'simple_history_reactions_enabled'             => __( 'Reactions', 'simple-history' ),
			'simple_history_email_report_enabled'          => __( 'Email report enabled', 'simple-history' ),
			'simple_history_email_report_recipients'       => __( 'Email report recipients', 'simple-history' ),
			Licences_Settings_Page::OPTION_NAME_LICENSE_KEY => __( 'License key', 'simple-history' ),
		];

		/**
		 * Filter the map of option keys that Simple History logs when changed.
		 *
		 * Add-ons use this to have their own settings logged as
		 * "Modified settings" via the Simple History logger.
		 *
		 * @param array<string,string> $settings Map of option name => human label.
		 */
		return apply_filters( 'simple_history/settings/tracked_options', $core_settings );
	}

	/**
	 * Get the tracked options whose value is a checkbox (1/0) and should be
	 * rendered as "On"/"Off" in the settings-changed log details.
	 *
	 * @return array<string> Option names.
	 */
	public function get_boolean_settings() {
		$boolean_settings = [
			'simple_history_show_on_dashboard',
			'simple_history_show_as_page',
			'simple_history_enable_rss_feed',
			'simple_history_detective_mode_enabled',
			'simple_history_show_in_admin_bar',
			'simple_history_experimental_features_enabled',
			'simple_history_reactions_enabled',
			'simple_history_email_report_enabled',
		];

		/**
		 * Filter the tracked options rendered as "On"/"Off" (instead of 1/0)
		 * in the "Modified settings" log details. Add-ons add their own
		 * checkbox options here.
		 *
		 * @param array<string> $boolean_settings Option names.
		 */
		return apply_filters( 'simple_history/settings/boolean_options', $boolean_settings );
	}

	/**
	 * Format a checkbox option value for display as "On" or "Off".
	 *
	 * Returns null unchanged so an absent value (added/removed setting) is
	 * still detected as such by the details container.
	 *
	 * @param mixed $value Raw stored value.
	 * @return string|null
	 */
	private function format_boolean_setting_value( $value ) {
		if ( $value === null ) {
			return null;
		}

		return $value ? __( 'On', 'simple-history' ) : __( 'Off', 'simple-history' );
	}

	/**
	 * Whether changes to an option should be logged.
	 *
	 * True for options in the tracked-settings map, and for any option
	 * registered to the Simple History general settings group. The latter
	 * keeps settings from add-ons that register settings the normal way,
	 * but have not (yet) adopted the tracked-options filter, logged like
	 * they were by earlier versions of this logger.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private function is_tracked_option( $option ) {
		if ( array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return true;
		}

		$registered_settings = get_registered_settings();
		$option_group        = $registered_settings[ $option ]['group'] ?? '';

		return $option_group === Simple_History::SETTINGS_GENERAL_OPTION_GROUP;
	}

	/**
	 * Get the list of tracked option names whose values must not be stored
	 * in the log (e.g. secrets/API keys). Their change is logged as
	 * "(changed)" without storing any value.
	 *
	 * @return array<int,string>
	 */
	public function get_redacted_settings() {
		$core_redacted = [
			Licences_Settings_Page::OPTION_NAME_LICENSE_KEY,
		];

		/**
		 * Filter the list of tracked option names whose values are never stored in the log.
		 *
		 * @param array<int,string> $option_names List of option names to redact.
		 */
		return apply_filters( 'simple_history/settings/redacted_options', $core_redacted );
	}

	/**
	 * Get the list of tracked option names that are logged as "changed" without
	 * storing their before/after value (for large or structured settings).
	 *
	 * @return array<int,string>
	 */
	public function get_changed_only_settings() {
		/**
		 * Filter the list of tracked option names logged as "changed" without
		 * storing their before/after value.
		 *
		 * Use this for large or structured settings (e.g. arrays of rules) whose
		 * raw value would be unreadable and bloat the log.
		 *
		 * @param array<int,string> $option_names List of option names.
		 */
		return apply_filters( 'simple_history/settings/changed_only_options', [] );
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

		if ( ! $old_is_simple || ! $new_is_simple ) {
			return true;
		}

		// Very long values would bloat the log; log them as changed-only too.
		if ( is_string( $old_value ) && strlen( $old_value ) > self::MAX_STORED_VALUE_LENGTH ) {
			return true;
		}

		return is_string( $new_value ) && strlen( $new_value ) > self::MAX_STORED_VALUE_LENGTH;
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
		if ( ! $this->is_tracked_option( $option ) ) {
			return;
		}

		if ( $this->is_changed_only_setting( $option, $old_value, $new_value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => $this->get_first_seen_old_value( $option, $old_value ),
			'new' => $new_value,
		];
	}

	/**
	 * Get the old value to buffer for an option change.
	 *
	 * When an option changes multiple times during the same request, keep the
	 * first-seen old value so the logged event shows the value the option had
	 * before the request, not an intermediate one.
	 *
	 * Returns the raw value: redaction happens at commit, since comparing
	 * redacted values would make every change to a redacted option look like
	 * a no-op.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value reported by the current hook call.
	 * @return mixed
	 */
	private function get_first_seen_old_value( $option, $old_value ) {
		$buffered_change = $this->settings_changes[ $option ] ?? null;

		if ( $buffered_change !== null && array_key_exists( 'old', $buffered_change ) ) {
			return $buffered_change['old'];
		}

		return $old_value;
	}

	/**
	 * Record a newly added tracked option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function on_tracked_option_added( $option, $value ) {
		if ( ! $this->is_tracked_option( $option ) ) {
			return;
		}

		// No prior value on add; use the new value for both scalarity checks.
		if ( $this->is_changed_only_setting( $option, $value, $value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		// Keep the buffered old value if the option was deleted (or otherwise
		// changed) earlier in the same request, so delete + re-add logs the
		// true previous value instead of an empty one.
		$this->settings_changes[ $option ] = [
			'old' => $this->get_first_seen_old_value( $option, '' ),
			'new' => $value,
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
		if ( ! $this->is_tracked_option( $option ) ) {
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
		if ( ! $this->is_tracked_option( $option ) ) {
			return;
		}

		$old_value = array_key_exists( $option, $this->deleted_option_values )
			? $this->deleted_option_values[ $option ]
			: '';

		$change = [ 'deleted' => true ];

		if ( $this->is_changed_only_setting( $option, $old_value, $old_value ) ) {
			$change['changed_only'] = true;
		} else {
			$change['old'] = $this->get_first_seen_old_value( $option, $old_value );
		}

		$this->settings_changes[ $option ] = $change;

		unset( $this->deleted_option_values[ $option ] );
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

		$context           = [];
		$redacted_settings = $this->get_redacted_settings();

		foreach ( $this->settings_changes as $option => $change ) {
			$base = $this->get_setting_context_base( $option );

			// Changed-only entries carry no values at all; redacted entries
			// carry raw values in the buffer but never store them in the log.
			$is_changed_only = ! empty( $change['changed_only'] );
			$stores_value    = ! $is_changed_only && ! in_array( $option, $redacted_settings, true );

			if ( ! empty( $change['deleted'] ) ) {
				if ( $stores_value ) {
					$context[ "{$base}_prev" ] = $change['old'];
				}

				$context[ "{$base}_new" ] = __( '(deleted)', 'simple-history' );

				continue;
			}

			// Skip changes that ended up back at the original value,
			// e.g. an option that was changed and reverted in the same request.
			if ( ! $is_changed_only && (string) $change['old'] === (string) $change['new'] ) {
				continue;
			}

			if ( ! $stores_value ) {
				$context[ "{$base}_new" ] = __( '(changed)', 'simple-history' );

				continue;
			}

			$context[ "{$base}_prev" ] = $change['old'];
			$context[ "{$base}_new" ]  = $change['new'];
		}

		$this->settings_changes = [];

		if ( count( $context ) === 0 ) {
			return;
		}

		$this->info_message( 'modified_settings', $context );
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
	 * @return Event_Details_Group
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
		// Other message keys (e.g. cleared_log, backfill) have no changed-items
		// detail; return an empty group (not a string) so the
		// `simple_history/log_row_details_output-SimpleHistoryLogger` filter
		// keeps running for those rows like it did in earlier versions.
		if ( $message_key !== 'modified_settings' ) {
			return new Event_Details_Group();
		}

		$context = isset( $row->context ) && is_array( $row->context ) ? $row->context : [];

		// Build a base => label lookup from the tracked-options map.
		$labels = [];
		foreach ( $this->get_tracked_settings() as $option => $label ) {
			$labels[ $this->get_setting_context_base( $option ) ] = $label;
		}

		// Bases whose stored 1/0 value renders as "On"/"Off" instead of a number.
		$boolean_bases = [];
		foreach ( $this->get_boolean_settings() as $option ) {
			$boolean_bases[ $this->get_setting_context_base( $option ) ] = true;
		}

		$group = new Event_Details_Group();
		$items = [];

		// Every change writes a `{base}_new` context key, so scanning the
		// `_new` suffix alone finds each changed setting exactly once.
		foreach ( array_keys( $context ) as $key ) {
			if ( ! str_ends_with( $key, '_new' ) ) {
				continue;
			}

			$base = substr( $key, 0, -4 );

			// Only render known settings. Events stored by earlier versions can
			// contain unrelated keys captured during the same save request, and
			// add-ons that have not adopted the tracked-options filter render
			// their own items via the details-output filter.
			if ( ! isset( $labels[ $base ] ) ) {
				continue;
			}

			$item = new Event_Details_Item( [ $base ], $labels[ $base ] );

			// Render checkbox settings as On/Off rather than the stored 1/0.
			if ( isset( $boolean_bases[ $base ] ) ) {
				$item->set_values(
					$this->format_boolean_setting_value( $context[ $base . '_new' ] ?? null ),
					$this->format_boolean_setting_value( $context[ $base . '_prev' ] ?? null )
				);
			}

			$items[] = $item;
		}

		$group->add_items( $items );
		$group->set_title( __( 'Changed items', 'simple-history' ) );

		return $group;
	}
}
