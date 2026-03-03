<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs changes to WordPress roles and capabilities.
 *
 * Monitors the wp_user_roles option for changes, detecting when roles
 * are created, deleted, or have their capabilities modified.
 *
 * Requires experimental features to be enabled.
 */
class Role_Capability_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleRoleCapabilityLogger';

	/**
	 * Plugin basename currently being activated or deactivated.
	 *
	 * @var string
	 */
	private $current_plugin_basename = '';

	/**
	 * Plugin context array for the current update cycle.
	 *
	 * Set at the start of on_roles_updated() and cleared at the end.
	 *
	 * @var array
	 */
	private $plugin_context = array();

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
					'Added {cap_count} capabilities to role "{role_name}": {capabilities}',
					'Role logger: capabilities added to role',
					'simple-history'
				),
				'role_caps_removed'         => _x(
					'Removed {cap_count} capabilities from role "{role_name}": {capabilities}',
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
		$this->current_plugin_basename = $plugin;
		$this->plugin_context          = $this->build_plugin_context( $plugin, 'activation' );
	}

	/**
	 * Store plugin basename when deactivation starts.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_deactivation_start( $plugin ) {
		$this->current_plugin_basename = $plugin;
		$this->plugin_context          = $this->build_plugin_context( $plugin, 'deactivation' );
	}

	/**
	 * Clear plugin context when activation/deactivation completes.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_plugin_activation_end( $plugin ) {
		$this->current_plugin_basename = '';
		$this->plugin_context          = array();
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
	 * Diffs old and new values to detect role/capability changes.
	 *
	 * @param array $old_value Previous roles array.
	 * @param array $new_value Updated roles array.
	 */
	public function on_roles_updated( $old_value, $new_value ) {
		if ( ! is_array( $old_value ) || ! is_array( $new_value ) ) {
			return;
		}

		$this->log_created_roles( $old_value, $new_value );
		$this->log_deleted_roles( $old_value, $new_value );
		$this->log_modified_roles( $old_value, $new_value );
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

			$this->notice_message(
				'role_created',
				array_merge(
					array(
						'role_slug'    => $role_slug,
						'role_name'    => $role_data['name'] ?? $role_slug,
						'cap_count'    => count( $caps ),
						'capabilities' => implode( ', ', $caps ),
					),
					$this->plugin_context
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
						'role_slug' => $role_slug,
						'role_name' => $role_data['name'] ?? $role_slug,
					),
					$this->plugin_context
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
					'role_slug' => $role_slug,
					'old_name'  => $old_name,
					'new_name'  => $new_name,
				),
				$this->plugin_context
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
			$this->warning_message(
				'role_caps_added',
				array_merge(
					array(
						'role_slug'    => $role_slug,
						'role_name'    => $role_name,
						'cap_count'    => count( $added_caps ),
						'capabilities' => implode( ', ', $added_caps ),
					),
					$this->plugin_context
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
				),
				$this->plugin_context
			)
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

		// Show capabilities list for role creation and capability changes.
		$messages_with_caps = array( 'role_created', 'role_caps_added', 'role_caps_removed' );
		if ( in_array( $message_key, $messages_with_caps, true ) && ! empty( $context['capabilities'] ) ) {
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
