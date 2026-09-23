<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;
use Simple_History\Log_Initiators;

/**
 * Logs changes to WordPress roles and capabilities.
 *
 * Monitors the wp_user_roles option for changes, detecting when roles
 * are created, deleted, or have their capabilities modified.
 *
 * Requires experimental features to be enabled.
 */
class Role_Capability_Logger extends Logger {
	/**
	 * Largest capability list that is shown in the event details panel.
	 *
	 * Plugin roles can carry 80+ capabilities. Listing them all turns the
	 * details panel into an unreadable wall of slugs that buries the other
	 * items, so longer lists are left out. Nothing is lost: the number of
	 * capabilities is part of the event message and the full list stays in
	 * the event context.
	 *
	 * @var int
	 */
	private const MAX_CAPS_IN_DETAILS = 10;

	/** @var string Logger slug */
	public $slug = 'SimpleRoleCapabilityLogger';

	/**
	 * Plugin context array for the current activation/deactivation cycle.
	 *
	 * @var array
	 */
	private $plugin_context = array();

	/**
	 * Initial roles snapshot captured on first update_option call.
	 *
	 * Used to compare against final state at shutdown so that transient
	 * add/remove cycles within a single request cancel out.
	 *
	 * @var array|null
	 */
	private $initial_roles = null;

	/**
	 * Who and what made each change in this request, keyed by change.
	 *
	 * Recorded when the roles option is updated, because by shutdown the
	 * plugin that made the change is no longer on the call stack and the
	 * activation and Action Scheduler state is gone. Keys are a role slug
	 * (role created or deleted), "<slug>|name" (display name) or
	 * "<slug>|cap|<cap>" (capability). A later update overwrites an earlier
	 * one for the same key.
	 *
	 * @var array<string, array{sequence: int, initiator: string|null, plugin_context: array, calling_plugin: array|null}>
	 */
	private $attributions = array();

	/**
	 * Counter that orders the attributions, so the most recent can be picked.
	 *
	 * @var int
	 */
	private $attribution_sequence = 0;

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'Role & Capability Logger', 'simple-history' ),
			'description' => __( 'Logs changes to user roles and capabilities', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'role_created'              => _x(
					'Created role "{role_name}" ({role_slug}) with {cap_count} capabilities',
					'Role logger: role created',
					'simple-history'
				),
				'role_deleted'              => _x(
					'Deleted role "{role_name}" ({role_slug})',
					'Role logger: role deleted',
					'simple-history'
				),
				'role_caps_added'           => _x(
					'Added {cap_count} capabilities to role "{role_name}"',
					'Role logger: capabilities added to role',
					'simple-history'
				),
				'role_caps_removed'         => _x(
					'Removed {cap_count} capabilities from role "{role_name}"',
					'Role logger: capabilities removed from role',
					'simple-history'
				),
				'role_display_name_changed' => _x(
					'Changed display name for role "{role_slug}" from "{old_name}" to "{new_name}"',
					'Role logger: role display name changed',
					'simple-history'
				),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Roles & Capabilities', 'Role logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Roles created', 'Role logger: search', 'simple-history' ) => array(
							'role_created',
						),
						_x( 'Roles deleted', 'Role logger: search', 'simple-history' ) => array(
							'role_deleted',
						),
						_x( 'Capabilities changed', 'Role logger: search', 'simple-history' ) => array(
							'role_caps_added',
							'role_caps_removed',
							'role_display_name_changed',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		global $wpdb;
		$role_key = $wpdb->prefix . 'user_roles';

		add_action( "update_option_{$role_key}", array( $this, 'on_roles_updated' ), 10, 2 );
		add_action( 'activate_plugin', array( $this, 'on_plugin_activation_start' ) );
		add_action( 'deactivate_plugin', array( $this, 'on_plugin_deactivation_start' ) );
		add_action( 'activated_plugin', array( $this, 'on_plugin_activation_end' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_activation_end' ) );
	}

	/**
	 * Store plugin basename when activation starts.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_activation_start( $plugin ) {
		$this->plugin_context = $this->build_plugin_context( $plugin, 'activation' );
	}

	/**
	 * Store plugin basename when deactivation starts.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_deactivation_start( $plugin ) {
		$this->plugin_context = $this->build_plugin_context( $plugin, 'deactivation' );
	}

	/**
	 * Clear plugin context when activation/deactivation completes.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_activation_end( $plugin ) {
		$this->plugin_context = array();
	}

	/**
	 * Build plugin context array with resolved plugin name.
	 *
	 * Resolves the human-readable plugin name at write time so it's
	 * available in the log even if the plugin is later uninstalled.
	 *
	 * @param string $plugin_basename Plugin basename (e.g. "woocommerce/woocommerce.php").
	 * @param string $action Either "activation" or "deactivation".
	 * @return array Context array.
	 */
	private function build_plugin_context( $plugin_basename, $action ) {
		$plugin_name = $plugin_basename;
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( file_exists( $plugin_file ) ) {
			$plugin_data = get_plugin_data( $plugin_file, false, false );
			if ( ! empty( $plugin_data['Name'] ) ) {
				$plugin_name = $plugin_data['Name'];
			}
		}

		return array(
			'plugin_context'        => $plugin_basename,
			'plugin_context_name'   => $plugin_name,
			'plugin_context_action' => $action,
		);
	}

	/**
	 * Called when the wp_user_roles option is updated.
	 *
	 * Instead of logging immediately, captures the initial state on the first
	 * call and defers logging to shutdown. This prevents log spam from plugins
	 * that repeatedly add/remove capabilities during a single request
	 * (e.g., Astra/Spectra toggling caps on every admin_init).
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 */
	public function on_roles_updated( $old_value, $new_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		// Capture the initial state only on the first update in this request.
		// Later updates within the same request will be compared against this
		// baseline at shutdown, so transient add/remove cycles cancel out.
		if ( $this->initial_roles === null ) {
			$this->initial_roles = $old_value;
			add_action( 'shutdown', array( $this, 'on_shutdown_log_role_changes' ) );
		}

		$change_keys = $this->get_change_keys( $old_value, $new_value );

		if ( empty( $change_keys ) ) {
			return;
		}

		// Record who made the changes while the code that made them is still
		// running. The logging itself happens in the shutdown handler.
		$attribution = array(
			'sequence'       => ++$this->attribution_sequence,
			'initiator'      => $this->get_initiator_for_role_change(),
			'plugin_context' => $this->plugin_context,
			// During activation the plugin is already known, so skip the backtrace.
			'calling_plugin' => empty( $this->plugin_context ) ? Helpers::get_calling_plugin() : null,
		);

		foreach ( $change_keys as $change_key ) {
			$this->attributions[ $change_key ] = $attribution;
		}
	}

	/**
	 * Get a key for each change between two role arrays.
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 * @return string[] Keys in the format described for $attributions.
	 */
	private function get_change_keys( $old_value, $new_value ) {
		$keys = array();

		foreach ( array_keys( $old_value + $new_value ) as $role_slug ) {
			$old_role = $old_value[ $role_slug ] ?? null;
			$new_role = $new_value[ $role_slug ] ?? null;

			if ( $old_role === $new_role ) {
				continue;
			}

			if ( ! is_array( $old_role ) || ! is_array( $new_role ) ) {
				$keys[] = (string) $role_slug;
				continue;
			}

			if ( ( $old_role['name'] ?? '' ) !== ( $new_role['name'] ?? '' ) ) {
				$keys[] = $role_slug . '|name';
			}

			$old_granted = array_keys( array_filter( $old_role['capabilities'] ?? array() ) );
			$new_granted = array_keys( array_filter( $new_role['capabilities'] ?? array() ) );
			$changed     = array_merge( array_diff( $new_granted, $old_granted ), array_diff( $old_granted, $new_granted ) );

			foreach ( $changed as $cap ) {
				$keys[] = $role_slug . '|cap|' . $cap;
			}
		}

		return $keys;
	}

	/**
	 * Decide who is responsible for a role change happening right now.
	 *
	 * The user is responsible only when their request was the reason for the
	 * change: activating a plugin, or a form or API call that writes. Plugins
	 * also change roles from their own upgrade routines, which run on whatever
	 * request comes first after an update: a page view, a REST call from the
	 * block editor, a cron run. Those are WordPress, whoever is logged in.
	 *
	 * @return string|null Initiator to store, or null to use the default detection (the current user).
	 */
	private function get_initiator_for_role_change() {
		// Activating or deactivating a plugin is the user's own action.
		if ( ! empty( $this->plugin_context ) ) {
			return null;
		}

		// WP-CLI, cron and Action Scheduler. Checked now, because the
		// Action Scheduler state is gone by shutdown.
		$automatic_initiator = Log_Initiators::get_automatic_initiator();

		if ( $automatic_initiator !== null ) {
			return $automatic_initiator;
		}

		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && self::is_write_request() ) {
			return null;
		}

		return Log_Initiators::WORDPRESS;
	}

	/**
	 * Whether the current request is one a user makes to change something.
	 *
	 * That is any method other than GET, HEAD and OPTIONS (a form post, an
	 * admin-ajax post, a REST write), or a GET action link carrying a nonce
	 * (activate, trash and so on). Read requests never change state on
	 * purpose, even when they carry the REST nonce header.
	 *
	 * This is coarse on purpose: a plugin's upgrade routine that happens to run
	 * during a post the user sent for some other reason is still credited to
	 * the user. Telling those apart would mean guessing from hook names, and
	 * many plugins handle their own form posts on admin_init.
	 *
	 * @return bool
	 */
	private static function is_write_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( ! in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return true;
		}

		// Only checks that a nonce is present, to tell an action link from a
		// page view. Nothing is processed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['_wpnonce'] ) || isset( $_GET['_ajax_nonce'] );
	}

	/**
	 * Get the context that says who and what made a change.
	 *
	 * Uses the most recent attribution among the given change keys.
	 *
	 * @param string[] $change_keys Keys of the changes that make up one event.
	 * @return array Context with plugin activation keys, `_initiator` and `_via_plugin` as they apply.
	 */
	private function get_attribution_context( $change_keys ) {
		$attribution = null;

		foreach ( $change_keys as $change_key ) {
			$candidate = $this->attributions[ $change_key ] ?? null;

			if ( $candidate === null ) {
				continue;
			}

			if ( $attribution !== null && $candidate['sequence'] <= $attribution['sequence'] ) {
				continue;
			}

			$attribution = $candidate;
		}

		if ( $attribution === null ) {
			return array();
		}

		$context = $attribution['plugin_context'];

		if ( $attribution['initiator'] !== null ) {
			$context['_initiator'] = $attribution['initiator'];
		}

		if ( ! empty( $attribution['plugin_context'] ) ) {
			$basename = $attribution['plugin_context']['plugin_context'];

			$context['_via_plugin']      = $attribution['plugin_context']['plugin_context_name'];
			$context['_via_plugin_slug'] = dirname( $basename ) === '.' ? basename( $basename, '.php' ) : dirname( $basename );
		} elseif ( ! empty( $attribution['calling_plugin'] ) ) {
			$context['_via_plugin']      = Helpers::get_calling_plugin_name( $attribution['calling_plugin'] );
			$context['_via_plugin_slug'] = $attribution['calling_plugin']['slug'];
		}

		return $context;
	}

	/**
	 * Log role changes at end of request by comparing initial vs final state.
	 *
	 * This batching approach prevents log spam from plugins that repeatedly
	 * add/remove capabilities during a single request (e.g., on every admin_init).
	 * Only net changes that actually persisted are logged.
	 */
	public function on_shutdown_log_role_changes() {
		if ( $this->initial_roles === null ) {
			return;
		}

		global $wpdb;
		$role_key    = $wpdb->prefix . 'user_roles';
		$final_roles = get_option( $role_key );

		if ( ! is_array( $final_roles ) ) {
			return;
		}

		$this->log_created_roles( $this->initial_roles, $final_roles );
		$this->log_deleted_roles( $this->initial_roles, $final_roles );
		$this->log_modified_roles( $this->initial_roles, $final_roles );
	}

	/**
	 * Log newly created roles.
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 */
	private function log_created_roles( $old_value, $new_value ) {
		$added_roles = array_diff_key( $new_value, $old_value );

		foreach ( $added_roles as $role_slug => $role_data ) {
			$caps = isset( $role_data['capabilities'] ) ? array_keys( array_filter( $role_data['capabilities'] ) ) : array();
			sort( $caps );

			$this->notice_message(
				'role_created',
				array_merge(
					array(
						'role_slug'    => $role_slug,
						'role_name'    => $role_data['name'] ?? $role_slug,
						'cap_count'    => count( $caps ),
						'capabilities' => implode( ', ', $caps ),
						'_occasionsID' => self::class . '/role_created/' . $role_slug,
					),
					$this->get_attribution_context( array( (string) $role_slug ) )
				)
			);
		}
	}

	/**
	 * Log deleted roles.
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 */
	private function log_deleted_roles( $old_value, $new_value ) {
		$removed_roles = array_diff_key( $old_value, $new_value );

		foreach ( $removed_roles as $role_slug => $role_data ) {
			$this->warning_message(
				'role_deleted',
				array_merge(
					array(
						'role_slug'    => $role_slug,
						'role_name'    => $role_data['name'] ?? $role_slug,
						'_occasionsID' => self::class . '/role_deleted/' . $role_slug,
					),
					$this->get_attribution_context( array( (string) $role_slug ) )
				)
			);
		}
	}

	/**
	 * Log changes to existing roles (capability and display name changes).
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 */
	private function log_modified_roles( $old_value, $new_value ) {
		$common_roles = array_intersect_key( $new_value, $old_value );

		foreach ( $common_roles as $role_slug => $new_data ) {
			$old_data = $old_value[ $role_slug ];

			$this->log_display_name_change( $role_slug, $old_data, $new_data );
			$this->log_capability_changes( $role_slug, $old_data, $new_data );
		}
	}

	/**
	 * Log role display name change.
	 *
	 * @param string $role_slug Role slug.
	 * @param array  $old_data Previous role data.
	 * @param array  $new_data Updated role data.
	 */
	private function log_display_name_change( $role_slug, $old_data, $new_data ) {
		$old_name = $old_data['name'] ?? '';
		$new_name = $new_data['name'] ?? '';

		if ( $old_name === $new_name ) {
			return;
		}

		$this->notice_message(
			'role_display_name_changed',
			array_merge(
				array(
					'role_slug'    => $role_slug,
					'old_name'     => $old_name,
					'new_name'     => $new_name,
					'_occasionsID' => self::class . '/role_display_name_changed/' . $role_slug,
				),
				$this->get_attribution_context( array( $role_slug . '|name' ) )
			)
		);
	}

	/**
	 * Log capability additions and removals on a role.
	 *
	 * @param string $role_slug Role slug.
	 * @param array  $old_data Previous role data.
	 * @param array  $new_data Updated role data.
	 */
	private function log_capability_changes( $role_slug, $old_data, $new_data ) {
		$old_caps = $old_data['capabilities'] ?? array();
		$new_caps = $new_data['capabilities'] ?? array();

		// Normalize: only consider granted (true) capabilities.
		$old_granted = array_keys( array_filter( $old_caps ) );
		$new_granted = array_keys( array_filter( $new_caps ) );

		$added_caps   = array_diff( $new_granted, $old_granted );
		$removed_caps = array_diff( $old_granted, $new_granted );

		$role_name = $new_data['name'] ?? $role_slug;

		if ( ! empty( $added_caps ) ) {
			sort( $added_caps );
			$this->notice_message(
				'role_caps_added',
				array_merge(
					array(
						'role_slug'    => $role_slug,
						'role_name'    => $role_name,
						'cap_count'    => count( $added_caps ),
						'capabilities' => implode( ', ', $added_caps ),
						'_occasionsID' => self::class . '/role_caps_added/' . $role_slug . '/' . implode( ',', $added_caps ),
					),
					$this->get_attribution_context( $this->get_cap_change_keys( $role_slug, $added_caps ) )
				)
			);
		}

		if ( empty( $removed_caps ) ) {
			return;
		}

		sort( $removed_caps );
		$this->warning_message(
			'role_caps_removed',
			array_merge(
				array(
					'role_slug'    => $role_slug,
					'role_name'    => $role_name,
					'cap_count'    => count( $removed_caps ),
					'capabilities' => implode( ', ', $removed_caps ),
					'_occasionsID' => self::class . '/role_caps_removed/' . $role_slug . '/' . implode( ',', $removed_caps ),
				),
				$this->get_attribution_context( $this->get_cap_change_keys( $role_slug, $removed_caps ) )
			)
		);
	}

	/**
	 * Get the change keys for capabilities changed on a role.
	 *
	 * @param string   $role_slug Role slug.
	 * @param string[] $caps      Capabilities.
	 * @return string[]
	 */
	private function get_cap_change_keys( $role_slug, $caps ) {
		return array_map(
			function ( $cap ) use ( $role_slug ) {
				return $role_slug . '|cap|' . $cap;
			},
			$caps
		);
	}

	/**
	 * Get output for the log row details.
	 *
	 * Shows capabilities list and plugin context when available.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? '';

		$group = new Event_Details_Group();

		// Show plugin context if available.
		if ( ! empty( $context['plugin_context_name'] ) ) {
			$action = $context['plugin_context_action'] ?? 'activation';
			$label  = $action === 'deactivation'
				? __( 'During deactivation of', 'simple-history' )
				: __( 'During activation of', 'simple-history' );

			$group->add_item(
				( new Event_Details_Item( 'plugin_context_name', $label ) )
			);
		}

		// Show capabilities list for role creation and capability changes,
		// but only when the list is short enough to be readable.
		$messages_with_caps = [ 'role_created', 'role_caps_added', 'role_caps_removed' ];
		$cap_count          = (int) ( $context['cap_count'] ?? 0 );

		$show_capabilities = in_array( $message_key, $messages_with_caps, true )
			&& ! empty( $context['capabilities'] )
			&& $cap_count <= self::MAX_CAPS_IN_DETAILS;

		if ( $show_capabilities ) {
			$group->add_item(
				( new Event_Details_Item( 'capabilities', __( 'Capabilities', 'simple-history' ) ) )
			);
		}

		if ( empty( $group->items ) ) {
			return '';
		}

		return $group;
	}
}
