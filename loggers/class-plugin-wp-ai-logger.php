<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logger for the official WordPress "AI" plugin (wordpress.org/plugins/ai).
 *
 * The AI plugin persists everything this logger cares about as options:
 *
 * - `wpai_connector_approvals`: per caller (plugin/theme) × AI provider approval matrix.
 * - `wpai_connector_approval_pending`: queue of denied access attempts awaiting admin review.
 * - `wpai_features_enabled`: global on/off switch for all AI features.
 * - `wpai_feature_{id}_enabled`: per-feature on/off toggles.
 * - `wpai_feature_{id}_field_developer`: per-feature AI provider + model selection.
 *
 * Hooking the generic option actions (instead of the AI plugin's internal API)
 * means this logger is a cheap no-op when the AI plugin is not installed: the
 * handlers bail on the `wpai_` prefix check and never touch any AI plugin code.
 *
 * Credential values (API keys) are never seen by this logger — connector keys
 * live in separate `connectors_*` options handled by Connectors_Logger, which
 * stores only a masked suffix.
 *
 * AI prompt/response content is intentionally not logged: the AI plugin has
 * its own request log for that, and prompts can contain private site content.
 */
class Plugin_WP_AI_Logger extends Logger {
	/** @var string Logger slug, stored in the database. */
	public $slug = 'PluginWPAILogger';

	/**
	 * Approvals granted during this request, as "caller_basename::connector_id"
	 * keys. Used to suppress the bogus "dismissed request" event that would
	 * otherwise fire when approving a pending request (the AI plugin removes
	 * the pending entry right after storing the approval).
	 *
	 * @var array<string, bool>
	 */
	protected $approvals_granted_this_request = [];

	/**
	 * Return logger info.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => _x( 'Plugin: AI Logger', 'Logger: WP AI', 'simple-history' ),
			'description' => __( 'Logs feature settings and connector approval changes in the WordPress AI plugin', 'simple-history' ),
			'name_via'    => _x( 'Using plugin AI', 'Logger: WP AI', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => [
				'ai_features_global_enabled'     => __( 'Enabled AI features', 'simple-history' ),
				'ai_features_global_disabled'    => __( 'Disabled AI features', 'simple-history' ),
				'ai_feature_enabled'             => __( 'Enabled AI feature "{feature_name}"', 'simple-history' ),
				'ai_feature_disabled'            => __( 'Disabled AI feature "{feature_name}"', 'simple-history' ),
				'ai_feature_model_changed'       => __( 'Changed AI model for feature "{feature_name}"', 'simple-history' ),
				'ai_feature_setting_changed'     => __( 'Changed setting "{setting_label}" for AI feature "{feature_name}"', 'simple-history' ),
				'ai_connector_access_approved'   => __( 'Approved AI provider "{connector_name}" access for "{caller_name}"', 'simple-history' ),
				'ai_connector_access_revoked'    => __( 'Revoked AI provider "{connector_name}" access for "{caller_name}"', 'simple-history' ),
				'ai_connector_access_requested'  => __( '"{caller_name}" requested access to AI provider "{connector_name}"', 'simple-history' ),
				'ai_connector_request_dismissed' => __( 'Dismissed AI provider access request from "{caller_name}" for "{connector_name}"', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label'     => _x( 'AI plugin', 'WP AI logger: search', 'simple-history' ),
					'label_all' => _x( 'All AI plugin activity', 'WP AI logger: search', 'simple-history' ),
					'options'   => [
						_x( 'Features enabled or disabled', 'WP AI logger: search', 'simple-history' ) => [
							'ai_features_global_enabled',
							'ai_features_global_disabled',
							'ai_feature_enabled',
							'ai_feature_disabled',
						],
						_x( 'AI models and feature settings changed', 'WP AI logger: search', 'simple-history' ) => [
							'ai_feature_model_changed',
							'ai_feature_setting_changed',
						],
						_x( 'Connector access approved or revoked', 'WP AI logger: search', 'simple-history' ) => [
							'ai_connector_access_approved',
							'ai_connector_access_revoked',
						],
						_x( 'Connector access requested or dismissed', 'WP AI logger: search', 'simple-history' ) => [
							'ai_connector_access_requested',
							'ai_connector_request_dismissed',
						],
					],
				],
			],
		];
	}

	/**
	 * Hook into WordPress.
	 *
	 * Both option hooks are global but the handlers bail immediately unless
	 * the option name starts with `wpai_`, so sites without the AI plugin
	 * pay one strncmp per option write and nothing more.
	 */
	public function loaded() {
		add_action( 'added_option', [ $this, 'on_added_option' ], 10, 2 );
		add_action( 'updated_option', [ $this, 'on_updated_option' ], 10, 3 );
	}

	/**
	 * Handle a `wpai_*` option being created for the first time.
	 *
	 * Several AI plugin options don't exist until their first change (the
	 * registered defaults are all false/empty), so the first enable of a
	 * feature or the first approval arrives via `added_option`.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Stored value.
	 */
	public function on_added_option( $option, $value ) {
		$this->handle_wpai_option_change( $option, null, $value );
	}

	/**
	 * Handle a `wpai_*` option being updated.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value.
	 * @param mixed  $new_value New value.
	 */
	public function on_updated_option( $option, $old_value, $new_value ) {
		$this->handle_wpai_option_change( $option, $old_value, $new_value );
	}

	/**
	 * Dispatch a single AI plugin option change to the right handler.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous value, or null when option was just added.
	 * @param mixed  $new_value New value.
	 */
	protected function handle_wpai_option_change( $option, $old_value, $new_value ) {
		if ( strpos( $option, 'wpai_' ) !== 0 ) {
			return;
		}

		if ( $option === 'wpai_connector_approvals' ) {
			$this->diff_connector_approvals( $old_value, $new_value );

			return;
		}

		if ( $option === 'wpai_connector_approval_pending' ) {
			$this->diff_pending_requests( $old_value, $new_value );

			return;
		}

		if ( $option === 'wpai_features_enabled' ) {
			$this->log_global_features_toggle( $new_value );

			return;
		}

		if ( preg_match( '/^wpai_feature_(.+)_enabled$/', $option, $matches ) === 1 ) {
			$this->log_feature_toggle( $matches[1], $new_value );

			return;
		}

		if ( preg_match( '/^wpai_feature_(.+)_field_developer$/', $option, $matches ) === 1 ) {
			$this->log_feature_model_change( $matches[1], $old_value, $new_value );

			return;
		}

		if ( preg_match( '/^wpai_feature_(.+)_field_(.+)$/', $option, $matches ) === 1 ) {
			$this->log_feature_setting_change( $matches[1], $matches[2], $old_value, $new_value );
		}
	}

	/**
	 * Diff the approval matrix and log each granted or revoked pair.
	 *
	 * The matrix is `caller_basename => connector_id => bool`. Approving from
	 * the pending list and toggling the matrix checkbox both write this same
	 * option, so one diff covers every approval path in the UI and REST API.
	 *
	 * @param mixed $old_value Previous matrix.
	 * @param mixed $new_value New matrix.
	 */
	protected function diff_connector_approvals( $old_value, $new_value ) {
		$old_pairs = $this->flatten_approvals( $old_value );
		$new_pairs = $this->flatten_approvals( $new_value );

		foreach ( array_diff_key( $new_pairs, $old_pairs ) as $pair_key => $pair ) {
			$this->approvals_granted_this_request[ $pair_key ] = true;

			$this->info_message(
				'ai_connector_access_approved',
				$this->build_approval_context( $pair['caller_basename'], $pair['connector_id'] )
			);
		}

		foreach ( array_diff_key( $old_pairs, $new_pairs ) as $pair ) {
			$this->notice_message(
				'ai_connector_access_revoked',
				$this->build_approval_context( $pair['caller_basename'], $pair['connector_id'] )
			);
		}
	}

	/**
	 * Diff the pending-requests queue and log new requests and dismissals.
	 *
	 * Repeated denied attempts only bump the `attempts` counter on an existing
	 * entry; those updates are deliberately not logged to avoid one event per
	 * denied HTTP request. Only brand-new queue entries are logged.
	 *
	 * @param mixed $old_value Previous queue.
	 * @param mixed $new_value New queue.
	 */
	protected function diff_pending_requests( $old_value, $new_value ) {
		$old_pending = is_array( $old_value ) ? $old_value : [];
		$new_pending = is_array( $new_value ) ? $new_value : [];

		foreach ( array_diff_key( $new_pending, $old_pending ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$this->notice_message(
				'ai_connector_access_requested',
				$this->build_pending_context( $entry )
			);
		}

		foreach ( array_diff_key( $old_pending, $new_pending ) as $pair_key => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// An approval removes its pending entry in the same request;
			// that removal is part of the approval, not a dismissal.
			if ( isset( $this->approvals_granted_this_request[ $pair_key ] ) ) {
				continue;
			}

			$this->info_message(
				'ai_connector_request_dismissed',
				$this->build_pending_context( $entry )
			);
		}
	}

	/**
	 * Log the global AI features switch being flipped.
	 *
	 * @param mixed $new_value New option value.
	 */
	protected function log_global_features_toggle( $new_value ) {
		if ( $this->is_truthy( $new_value ) ) {
			$this->info_message( 'ai_features_global_enabled' );
		} else {
			$this->info_message( 'ai_features_global_disabled' );
		}
	}

	/**
	 * Log a single AI feature being enabled or disabled.
	 *
	 * @param string $feature_id Feature id from the option name, e.g. "comment_moderation".
	 * @param mixed  $new_value  New option value.
	 */
	protected function log_feature_toggle( $feature_id, $new_value ) {
		$context = [
			'feature_id'   => $feature_id,
			'feature_name' => $this->humanize_slug( $feature_id ),
		];

		if ( $this->is_truthy( $new_value ) ) {
			$this->info_message( 'ai_feature_enabled', $context );
		} else {
			$this->info_message( 'ai_feature_disabled', $context );
		}
	}

	/**
	 * Log a feature's AI provider/model selection being changed.
	 *
	 * Provider and model names are configuration, not secrets, so prev/new
	 * values are stored as-is for diff display.
	 *
	 * @param string $feature_id Feature id from the option name.
	 * @param mixed  $old_value  Previous value, array with provider/model keys.
	 * @param mixed  $new_value  New value, array with provider/model keys.
	 */
	protected function log_feature_model_change( $feature_id, $old_value, $new_value ) {
		$old_provider = is_array( $old_value ) ? (string) ( $old_value['provider'] ?? '' ) : '';
		$old_model    = is_array( $old_value ) ? (string) ( $old_value['model'] ?? '' ) : '';
		$new_provider = is_array( $new_value ) ? (string) ( $new_value['provider'] ?? '' ) : '';
		$new_model    = is_array( $new_value ) ? (string) ( $new_value['model'] ?? '' ) : '';

		if ( $old_provider === $new_provider && $old_model === $new_model ) {
			return;
		}

		$context = [
			'feature_id'   => $feature_id,
			'feature_name' => $this->humanize_slug( $feature_id ),
		];

		if ( $old_provider !== $new_provider ) {
			$context['feature_provider_new'] = $new_provider;

			if ( $old_provider !== '' ) {
				$context['feature_provider_prev'] = $old_provider;
			}
		}

		if ( $old_model !== $new_model ) {
			$context['feature_model_new'] = $new_model;

			if ( $old_model !== '' ) {
				$context['feature_model_prev'] = $old_model;
			}
		}

		$this->info_message( 'ai_feature_model_changed', $context );
	}

	/**
	 * Log a feature's custom setting being changed, e.g. content classification
	 * strategy or max suggestions.
	 *
	 * Scalar values are stored as readable prev/new strings; structured values
	 * are not stored — the event still records that the setting changed.
	 *
	 * @param string $feature_id   Feature id from the option name.
	 * @param string $setting_name Setting field name from the option name.
	 * @param mixed  $old_value    Previous value.
	 * @param mixed  $new_value    New value.
	 */
	protected function log_feature_setting_change( $feature_id, $setting_name, $old_value, $new_value ) {
		$context = [
			'feature_id'    => $feature_id,
			'feature_name'  => $this->humanize_slug( $feature_id ),
			'setting_name'  => $setting_name,
			'setting_label' => $this->humanize_slug( $setting_name ),
		];

		if ( is_scalar( $old_value ) && (string) $old_value !== '' ) {
			$context['feature_setting_prev'] = (string) $old_value;
		}

		if ( is_scalar( $new_value ) || $new_value === null ) {
			$context['feature_setting_new'] = (string) $new_value;
		}

		$this->info_message( 'ai_feature_setting_changed', $context );
	}

	/**
	 * Convert a feature or setting slug ("connector-approval", "max_suggestions")
	 * into a readable label ("Connector approval", "Max suggestions").
	 *
	 * @param string $slug Slug with hyphens and/or underscores.
	 * @return string
	 */
	protected function humanize_slug( $slug ) {
		return Helpers::snake_case_to_sentence_case( str_replace( '-', '_', $slug ) );
	}

	/**
	 * Flatten an approval matrix into "caller::connector" keyed pairs.
	 *
	 * Keys match the AI plugin's own pending-queue key format so approval
	 * pairs can be compared against pending entry keys directly.
	 *
	 * @param mixed $matrix Raw option value.
	 * @return array<string, array{caller_basename: string, connector_id: string}>
	 */
	protected function flatten_approvals( $matrix ) {
		if ( ! is_array( $matrix ) ) {
			return [];
		}

		$pairs = [];

		foreach ( $matrix as $caller_basename => $connectors ) {
			if ( ! is_string( $caller_basename ) || ! is_array( $connectors ) ) {
				continue;
			}

			foreach ( $connectors as $connector_id => $approved ) {
				if ( ! is_string( $connector_id ) || ! $approved ) {
					continue;
				}

				$pairs[ $caller_basename . '::' . $connector_id ] = [
					'caller_basename' => $caller_basename,
					'connector_id'    => $connector_id,
				];
			}
		}

		return $pairs;
	}

	/**
	 * Build context for an approval granted/revoked event.
	 *
	 * @param string $caller_basename Caller plugin basename or theme slug.
	 * @param string $connector_id    Connector id, e.g. "anthropic".
	 * @return array
	 */
	protected function build_approval_context( $caller_basename, $connector_id ) {
		return [
			'connector_id'    => $connector_id,
			'connector_name'  => $this->get_connector_name( $connector_id ),
			'caller_basename' => $caller_basename,
			'caller_name'     => $this->get_caller_name( $caller_basename ),
		];
	}

	/**
	 * Build context for a pending request created/dismissed event.
	 *
	 * Pending entries carry their own caller display name, captured by the
	 * AI plugin when the denied attempt happened — prefer it over a lookup
	 * since the caller may have been uninstalled by the time of a dismissal.
	 *
	 * @param array $entry Pending queue entry.
	 * @return array
	 */
	protected function build_pending_context( $entry ) {
		$caller_basename = (string) ( $entry['caller_basename'] ?? '' );
		$caller_name     = (string) ( $entry['caller_name'] ?? '' );
		$connector_id    = (string) ( $entry['connector_id'] ?? '' );

		if ( $caller_name === '' ) {
			$caller_name = $this->get_caller_name( $caller_basename );
		}

		$context = [
			'connector_id'    => $connector_id,
			'connector_name'  => $this->get_connector_name( $connector_id ),
			'caller_basename' => $caller_basename,
			'caller_name'     => $caller_name,
		];

		$caller_type = (string) ( $entry['caller_type'] ?? '' );

		if ( $caller_type !== '' ) {
			$context['caller_type'] = $caller_type;
		}

		return $context;
	}

	/**
	 * Resolve a connector id to its display name.
	 *
	 * Uses the WordPress Connectors API when available; falls back to the
	 * capitalized id so events still read well if the AI plugin or WP 7.0
	 * connectors are gone when the lookup happens.
	 *
	 * @param string $connector_id Connector id, e.g. "anthropic".
	 * @return string
	 */
	protected function get_connector_name( $connector_id ) {
		if ( $connector_id === '' ) {
			return '';
		}

		if ( function_exists( 'wp_get_connector' ) ) {
			$connector = wp_get_connector( $connector_id );

			if ( is_array( $connector ) && ! empty( $connector['name'] ) && is_string( $connector['name'] ) ) {
				return $connector['name'];
			}
		}

		return ucfirst( $connector_id );
	}

	/**
	 * Resolve a caller basename (plugin basename or theme slug) to a display name.
	 *
	 * @param string $caller_basename Caller basename, e.g. "ai/ai.php" or a theme slug.
	 * @return string
	 */
	protected function get_caller_name( $caller_basename ) {
		if ( $caller_basename === '' ) {
			return '';
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		if ( isset( $all_plugins[ $caller_basename ]['Name'] ) && $all_plugins[ $caller_basename ]['Name'] !== '' ) {
			return $all_plugins[ $caller_basename ]['Name'];
		}

		// Theme slugs have no slash or file extension.
		if ( strpos( $caller_basename, '/' ) === false && pathinfo( $caller_basename, PATHINFO_EXTENSION ) === '' ) {
			$theme = wp_get_theme( $caller_basename );

			if ( $theme->exists() ) {
				return $theme->get( 'Name' );
			}
		}

		return $caller_basename;
	}

	/**
	 * Interpret a stored option value as a boolean.
	 *
	 * Boolean options pass through update_option() as true/false but are
	 * stored and returned as "1"/"" strings.
	 *
	 * @param mixed $value Stored option value.
	 * @return bool
	 */
	protected function is_truthy( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Show technical identifiers and provider/model diffs below the message.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$context = isset( $row->context ) && is_array( $row->context ) ? $row->context : [];

		$group = new Event_Details_Group();
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		$group->add_items(
			[
				new Event_Details_Item( [ 'feature_provider' ], __( 'AI provider', 'simple-history' ) ),
				new Event_Details_Item( [ 'feature_model' ], __( 'AI model', 'simple-history' ) ),
				new Event_Details_Item( [ 'feature_setting' ], __( 'Setting value', 'simple-history' ) ),
				new Event_Details_Item( 'caller_basename', __( 'Caller', 'simple-history' ) ),
				new Event_Details_Item( 'connector_id', __( 'Connector', 'simple-history' ) ),
			]
		);

		if ( isset( $context['caller_type'] ) && $context['caller_type'] !== '' ) {
			$item = new Event_Details_Item( null, __( 'Caller type', 'simple-history' ) );
			$item->set_new_value( Helpers::snake_case_to_sentence_case( $context['caller_type'] ) );
			$group->add_item( $item );
		}

		return $group;
	}

	/**
	 * Link approval events to the Connector Approvals page and settings
	 * events to the AI settings page.
	 *
	 * Links are only offered while the AI plugin is active, so the admin is
	 * never handed a dead URL.
	 *
	 * @param object $row Log row.
	 * @return array<array{url: string, label: string, action: string}>
	 */
	public function get_action_links( $row ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [];
		}

		if ( ! function_exists( 'WordPress\AI\get_ai_connectors' ) ) {
			return [];
		}

		$message_key = $row->context['_message_key'] ?? '';

		$approval_message_keys = [
			'ai_connector_access_approved',
			'ai_connector_access_revoked',
			'ai_connector_access_requested',
			'ai_connector_request_dismissed',
		];

		if ( in_array( $message_key, $approval_message_keys, true ) ) {
			return [
				[
					'url'    => admin_url( 'tools.php?page=ai-connector-approval' ),
					'label'  => __( 'Manage connector approvals', 'simple-history' ),
					'action' => 'edit',
				],
			];
		}

		return [
			[
				'url'    => admin_url( 'options-general.php?page=ai-wp-admin' ),
				'label'  => __( 'Manage AI settings', 'simple-history' ),
				'action' => 'edit',
			],
		];
	}
}
