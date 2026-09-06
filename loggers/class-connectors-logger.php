<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logs changes to API keys managed by the WordPress Connectors API (WP 7.0+).
 *
 * The Connectors API stores third-party service credentials (AI providers like
 * Anthropic/OpenAI/Google, anti-spam services like Akismet, etc.) as regular
 * options keyed under `connectors_*`. This logger tracks add/update/remove of
 * those credentials so site owners have an audit trail of who connected, changed,
 * or disconnected an external service — without ever storing the key value.
 */
class Connectors_Logger extends Logger {
	/** @var string Logger slug, stored in the database. */
	public $slug = 'ConnectorsLogger';

	/**
	 * Connector metadata keyed by setting_name, populated when hooks are bound.
	 *
	 * @var array<string, array{id: string, data: array}>
	 */
	protected $connectors_by_setting = array();

	/**
	 * Option values captured in the `delete_option` action, before the DB row
	 * is removed. Read in `deleted_option` so we have the pre-delete value to log.
	 *
	 * @var array<string, mixed>
	 */
	protected $pre_delete_values = array();

	/**
	 * Return logger info.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => _x( 'Connectors Logger', 'ConnectorsLogger', 'simple-history' ),
			'description' => __( 'Logs when API keys for third-party connectors (AI providers, anti-spam, etc.) are added, changed, or removed.', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'connector_api_key_added'   => __( 'Added API key for connector "{connector_name}"', 'simple-history' ),
				'connector_api_key_updated' => __( 'Updated API key for connector "{connector_name}"', 'simple-history' ),
				'connector_api_key_removed' => __( 'Removed API key for connector "{connector_name}"', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Connectors', 'Connectors logger: search', 'simple-history' ),
					'label_all' => _x( 'All connector changes', 'Connectors logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Added API key', 'Connectors logger: search', 'simple-history' )   => array(
							'connector_api_key_added',
						),
						_x( 'Updated API key', 'Connectors logger: search', 'simple-history' ) => array(
							'connector_api_key_updated',
						),
						_x( 'Removed API key', 'Connectors logger: search', 'simple-history' ) => array(
							'connector_api_key_removed',
						),
					),
				),
			),
		);
	}

	/**
	 * Hook into WordPress.
	 *
	 * Defer until `init` priority 30, after core registers default connector
	 * settings (priority 20) so `wp_get_connectors()` returns the full set.
	 *
	 * Trade-off: any connector registered at `init` priority 31+ is invisible
	 * to this snapshot and won't have its API key changes logged. In practice
	 * connectors register on the earlier `wp_connectors_init` action (which
	 * fires during core's priority-20 hook), so this is rarely an issue —
	 * but note the constraint when third-party connectors misbehave.
	 */
	public function loaded() {
		add_action( 'init', array( $this, 'register_connector_hooks' ), 30 );
	}

	/**
	 * Build the connectors-by-setting lookup, then bind the global option hooks.
	 *
	 * All four hooks (`added_option`, `updated_option`, `delete_option`,
	 * `deleted_option`) are global — they fire for every option write across
	 * WordPress, and each callback short-circuits via the
	 * `$connectors_by_setting` lookup when the option name isn't ours. That's
	 * cheaper to reason about and matches the delete pattern, which has to be
	 * global anyway because `delete_option_{$name}` fires *after* the DB row
	 * is gone and we need a pre-delete read.
	 */
	public function register_connector_hooks() {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return;
		}

		foreach ( wp_get_connectors() as $connector_id => $connector_data ) {
			$auth = $connector_data['authentication'] ?? array();

			if ( empty( $auth['method'] ) || $auth['method'] !== 'api_key' || empty( $auth['setting_name'] ) ) {
				continue;
			}

			$setting_name = $auth['setting_name'];

			// First connector to claim a setting name wins. Two connectors
			// sharing one setting would otherwise produce duplicate events.
			if ( isset( $this->connectors_by_setting[ $setting_name ] ) ) {
				continue;
			}

			$this->connectors_by_setting[ $setting_name ] = array(
				'id'   => $connector_id,
				'data' => $connector_data,
			);
		}

		if ( empty( $this->connectors_by_setting ) ) {
			return;
		}

		add_action( 'added_option', array( $this, 'handle_added_option' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'handle_updated_option' ), 10, 3 );
		add_action( 'delete_option', array( $this, 'capture_value_before_delete' ) );
		add_action( 'deleted_option', array( $this, 'handle_deleted_option' ) );
	}

	/**
	 * Look up the connector behind an option name. Returns null when the
	 * option isn't one we're tracking — callers use this as a short-circuit.
	 *
	 * @param string $option Option name.
	 * @return array{id: string, data: array}|null
	 */
	protected function get_connector_for_option( $option ) {
		return $this->connectors_by_setting[ $option ] ?? null;
	}

	/**
	 * Global `added_option` dispatcher — fires for every add_option() call.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Stored value.
	 */
	public function handle_added_option( $option, $value ) {
		$info = $this->get_connector_for_option( $option );
		if ( $info === null ) {
			return;
		}

		$this->on_connector_option_added( $info['id'], $info['data'], $value );
	}

	/**
	 * Global `updated_option` dispatcher — fires for every update_option() call.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 */
	public function handle_updated_option( $option, $old_value, $new_value ) {
		$info = $this->get_connector_for_option( $option );
		if ( $info === null ) {
			return;
		}

		$this->on_connector_option_updated( $info['id'], $info['data'], $old_value, $new_value );
	}

	/**
	 * Stash a connector setting's value before WordPress deletes it.
	 *
	 * Fired by the global `delete_option` action, which runs *before* the
	 * DB delete. Only stashes values for setting names that belong to a
	 * registered connector.
	 *
	 * @param string $option Option name about to be deleted.
	 */
	public function capture_value_before_delete( $option ) {
		if ( $this->get_connector_for_option( $option ) === null ) {
			return;
		}

		$this->pre_delete_values[ $option ] = get_option( $option, '' );
	}

	/**
	 * Log a connector option deletion.
	 *
	 * Fired by the global `deleted_option` action, which only runs after a
	 * successful `$wpdb->delete()` — so any log entry here corresponds to a
	 * real removal.
	 *
	 * The pre-delete value is normally pulled from `$pre_delete_values`
	 * (populated by `capture_value_before_delete`). Tests may pass
	 * `$old_value_override` to drive the handler directly without staging
	 * the stash via reflection.
	 *
	 * @param string      $option             Option name that was deleted.
	 * @param string|null $old_value_override Test seam: explicit pre-delete value.
	 */
	public function handle_deleted_option( $option, $old_value_override = null ) {
		$info = $this->get_connector_for_option( $option );
		if ( $info === null ) {
			return;
		}

		if ( $old_value_override !== null ) {
			$old_value = $old_value_override;
		} else {
			$old_value = $this->pre_delete_values[ $option ] ?? '';
			unset( $this->pre_delete_values[ $option ] );
		}

		$this->on_connector_option_deleted( $info['id'], $info['data'], $old_value );
	}

	/**
	 * Handle a brand-new API key being stored for a connector.
	 *
	 * @param string $connector_id   Connector identifier (e.g. "anthropic").
	 * @param array  $connector_data Connector data from wp_get_connectors().
	 * @param mixed  $value          New option value.
	 */
	public function on_connector_option_added( $connector_id, $connector_data, $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return;
		}

		$context = array_merge(
			$this->build_base_context( $connector_id, $connector_data ),
			$this->build_secret_descriptor( 'new', $value )
		);

		$this->info_message( 'connector_api_key_added', $context );
	}

	/**
	 * Handle a connector API key being updated.
	 *
	 * Treats empty → non-empty as an add and non-empty → empty as a removal so
	 * we report what the admin actually did, not which WP hook fired.
	 *
	 * @param string $connector_id   Connector identifier.
	 * @param array  $connector_data Connector data from wp_get_connectors().
	 * @param mixed  $old_value      Previous option value.
	 * @param mixed  $new_value      New option value.
	 */
	public function on_connector_option_updated( $connector_id, $connector_data, $old_value, $new_value ) {
		$old_string = is_string( $old_value ) ? $old_value : '';
		$new_string = is_string( $new_value ) ? $new_value : '';

		if ( $old_string === $new_string ) {
			return;
		}

		$base_context = $this->build_base_context( $connector_id, $connector_data );

		if ( $old_string === '' && $new_string !== '' ) {
			$this->info_message(
				'connector_api_key_added',
				array_merge( $base_context, $this->build_secret_descriptor( 'new', $new_string ) )
			);
			return;
		}

		if ( $old_string !== '' && $new_string === '' ) {
			$this->warning_message(
				'connector_api_key_removed',
				array_merge( $base_context, $this->build_secret_descriptor( 'prev', $old_string ) )
			);
			return;
		}

		$this->notice_message(
			'connector_api_key_updated',
			array_merge(
				$base_context,
				$this->build_secret_descriptor( 'prev', $old_string ),
				$this->build_secret_descriptor( 'new', $new_string )
			)
		);
	}

	/**
	 * Handle a connector option being deleted entirely.
	 *
	 * @param string $connector_id   Connector identifier.
	 * @param array  $connector_data Connector data from wp_get_connectors().
	 * @param mixed  $old_value      Value before deletion.
	 */
	public function on_connector_option_deleted( $connector_id, $connector_data, $old_value ) {
		if ( ! is_string( $old_value ) || $old_value === '' ) {
			return;
		}

		$context = array_merge(
			$this->build_base_context( $connector_id, $connector_data ),
			$this->build_secret_descriptor( 'prev', $old_value )
		);

		$this->warning_message( 'connector_api_key_removed', $context );
	}

	/**
	 * Build the shared context (connector identity + setting metadata).
	 *
	 * @param string $connector_id   Connector identifier.
	 * @param array  $connector_data Connector data from wp_get_connectors().
	 * @return array
	 */
	protected function build_base_context( $connector_id, $connector_data ) {
		return array(
			'connector_id'           => $connector_id,
			'connector_name'         => $connector_data['name'] ?? $connector_id,
			'connector_type'         => $connector_data['type'] ?? '',
			'connector_setting_name' => $connector_data['authentication']['setting_name'] ?? '',
		);
	}

	/**
	 * Action link to the WordPress 7.0 Connectors settings page.
	 *
	 * Only surfaced when the Connectors page is actually reachable on this
	 * site (i.e. WP 7.0+ and the AI Client class is loaded). On older WP, or
	 * if a future WP version moves the page, we silently omit the link rather
	 * than handing the admin a dead end.
	 *
	 * @param object $row Log row (unused — the page is a single global URL).
	 * @return array<array{url: string, label: string, action: string}>
	 */
	public function get_action_links( $row ) {
		unset( $row );

		if ( ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		if ( ! function_exists( 'wp_get_connectors' ) ) {
			return array();
		}

		return array(
			array(
				'url'    => admin_url( 'options-connectors.php' ),
				'label'  => __( 'Manage connectors', 'simple-history' ),
				'action' => 'edit',
			),
		);
	}

	/**
	 * Describe a secret for the context array in a way that's safe to log.
	 *
	 * For secrets long enough that the last 4 chars don't constitute the whole
	 * value, records `api_key_{$direction}_last_4` — a stable, low-information
	 * identifier matching the GitHub/Stripe/OpenAI/AWS audit-log conventions.
	 *
	 * For secrets too short to expose any suffix safely (length <= 4),
	 * records `api_key_{$direction}_was_short` + `_length` instead. Storing the
	 * raw short value or a useless asterisk mask would add zero forensic
	 * value while implying we held a "partial." In practice such inputs are
	 * almost always invalid test keys, but the audit trail should still
	 * record that a change occurred.
	 *
	 * @param string $direction Either 'new' or 'prev' — keyed into context names.
	 * @param string $secret    The credential value (never stored in raw form).
	 * @return array<string, string>
	 */
	protected function build_secret_descriptor( $direction, $secret ) {
		$keys   = $this->secret_context_keys( $direction );
		$suffix = Helpers::mask_secret( $secret );

		if ( $suffix !== null ) {
			return array( $keys['suffix'] => $suffix );
		}

		return array(
			$keys['was_short'] => 'true',
			$keys['length']    => (string) strlen( $secret ),
		);
	}

	/**
	 * Single source of truth for the three context keys used by both
	 * `build_secret_descriptor()` (writer) and
	 * `describe_stored_secret_for_display()` (reader). A typo or schema
	 * change in one without the other would silently break the round-trip.
	 *
	 * @param string $direction Either 'new' or 'prev'.
	 * @return array{suffix: string, was_short: string, length: string}
	 */
	protected function secret_context_keys( $direction ) {
		return array(
			'suffix'    => "api_key_{$direction}_last_4",
			'was_short' => "api_key_{$direction}_was_short",
			'length'    => "api_key_{$direction}_length",
		);
	}

	/**
	 * Render the event details table with masked-credential formatting.
	 *
	 * Pure-suffix storage (`7890`) is correct for the database but ambiguous
	 * for a reader: it looks like a fragment rather than a credential. The
	 * Event Details API lets us format it as `••••7890` at render time,
	 * matching the visual convention used by Stripe, GitHub, OpenAI, and
	 * WordPress 7.0's own Connectors page.
	 *
	 * @param object $row Log row with `->context` array of stored key/value pairs.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		$group = new Event_Details_Group();
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		if ( ! empty( $context['connector_type'] ) ) {
			$item = new Event_Details_Item( null, __( 'Connector type', 'simple-history' ) );
			$item->set_new_value( $this->humanize_connector_type( $context['connector_type'] ) );
			$group->add_item( $item );
		}

		$prev_display = $this->describe_stored_secret_for_display( $context, 'prev' );
		$new_display  = $this->describe_stored_secret_for_display( $context, 'new' );

		// Context-aware labels: on an add (no prev), the new value is just
		// "API key" — there's nothing to be "new" relative to. Update events
		// keep the prev/new pair for clarity.
		$has_prev      = $prev_display !== '';
		$new_key_label = $has_prev
			? __( 'New API key', 'simple-history' )
			: __( 'API key', 'simple-history' );

		if ( $has_prev ) {
			$item = new Event_Details_Item( null, __( 'Previous API key', 'simple-history' ) );
			$item->set_new_value( $prev_display );
			$group->add_item( $item );
		}

		if ( $new_display !== '' ) {
			$item = new Event_Details_Item( null, $new_key_label );
			$item->set_new_value( $new_display );
			$group->add_item( $item );
		}

		return $group;
	}

	/**
	 * Convert a connector type slug into a human-readable label.
	 *
	 * WP 7.0 ships `ai_provider` and `spam_filtering`; third-party connectors
	 * may add their own. Falls back to a generic snake_case → "Sentence case"
	 * transform so any future type renders sensibly without a code change.
	 *
	 * @param string $type Stored connector type slug.
	 * @return string
	 */
	protected function humanize_connector_type( $type ) {
		$known = array(
			'ai_provider'    => __( 'AI provider', 'simple-history' ),
			'spam_filtering' => __( 'Spam filtering', 'simple-history' ),
		);

		if ( isset( $known[ $type ] ) ) {
			return $known[ $type ];
		}

		return Helpers::snake_case_to_sentence_case( $type );
	}

	/**
	 * Convert one direction's stored secret descriptor back into display text.
	 *
	 * Mirror image of `build_secret_descriptor()`: handles both the normal
	 * `last_4` suffix path (rendered as `••••XXXX`) and the short-secret
	 * fallback (rendered as a non-disclosing length notice).
	 *
	 * @param array  $context   Event context keyed by stored context names.
	 * @param string $direction Either 'new' or 'prev'.
	 * @return string Empty string when nothing was stored for this direction.
	 */
	protected function describe_stored_secret_for_display( array $context, $direction ) {
		$keys = $this->secret_context_keys( $direction );

		if ( isset( $context[ $keys['suffix'] ] ) && $context[ $keys['suffix'] ] !== '' ) {
			return Helpers::format_masked_secret_for_display( $context[ $keys['suffix'] ] );
		}

		if ( isset( $context[ $keys['was_short'] ] ) && $context[ $keys['was_short'] ] === 'true' ) {
			$length = isset( $context[ $keys['length'] ] ) ? (int) $context[ $keys['length'] ] : 0;

			return sprintf(
				/* translators: %d: character count of a value that was too short to mask. */
				_n(
					'(value too short to display — %d character)',
					'(value too short to display — %d characters)',
					$length,
					'simple-history'
				),
				$length
			);
		}

		return '';
	}
}
