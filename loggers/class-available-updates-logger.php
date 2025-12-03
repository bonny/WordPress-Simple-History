<?php

namespace Simple_History\Loggers;

use Simple_History\Log_Initiators;

/**
 * Logs available updates to themes, plugins and WordPress core
 *
 * @package SimpleHistory
 */
class Available_Updates_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'AvailableUpdatesLogger';

	/**
	 * Return logger info
	 *
	 * @return array
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => _x( 'Available Updates Logger', 'AvailableUpdatesLogger', 'simple-history' ),
			'type'        => 'core',
			'description' => __( 'Logs found updates to WordPress, plugins, and themes', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'core_update_available'   => __( 'Found an update to WordPress', 'simple-history' ),
				'plugin_update_available' => __( 'Found an update to plugin "{plugin_name}"', 'simple-history' ),
				'theme_update_available'  => __( 'Found an update to theme "{theme_name}"', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'WordPress and plugins updates found', 'Plugin logger: updates found', 'simple-history' ),
					'label_all' => _x( 'All found updates', 'Plugin logger: updates found', 'simple-history' ),
					'options'   => array(
						_x( 'WordPress updates found', 'Plugin logger: updates found', 'simple-history' ) => array(
							'core_update_available',
						),
						_x( 'Plugin updates found', 'Plugin logger: updates found', 'simple-history' ) => array(
							'plugin_update_available',
						),
						_x( 'Theme updates found', 'Plugin logger: updates found', 'simple-history' ) => array(
							'theme_update_available',
						),
					),
				), // search array.
			), // labels.
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {

		// When WP is done checking for core updates it sets a site transient called "update_core".
		add_action( 'set_site_transient_update_core', array( $this, 'on_setted_update_core_transient' ), 10, 1 );

		// Ditto for plugins.
		add_action( 'set_site_transient_update_plugins', array( $this, 'on_setted_update_plugins_transient' ), 10, 1 );

		add_action( 'set_site_transient_update_themes', array( $this, 'on_setted_update_update_themes' ), 10, 1 );
	}

	/**
	 * Called when WordPress is done checking for core updates.
	 * WP sets site transient 'update_core' when done.
	 * Log found core update.
	 *
	 * @param object $updates Updates object.
	 */
	public function on_setted_update_core_transient( $updates ) {

		global $wp_version;

		$last_version_checked = get_option( "simplehistory_{$this->get_slug()}_wp_core_version_available" );

		// During update of network sites this was not set, so make sure to check.
		if ( empty( $updates->updates[0]->current ) ) {
			return;
		}

		$new_wp_core_version = $updates->updates[0]->current; // The new WP core version.

		// Some plugins can mess with version, so get fresh from the version file.
		require_once ABSPATH . WPINC . '/version.php';

		// If found version is same version as we have logged about before then don't continue.
		if ( $last_version_checked === $new_wp_core_version ) {
			return;
		}

		// is WP core update available?
		if ( isset( $updates->updates[0]->response ) && 'upgrade' === $updates->updates[0]->response ) {
			$this->notice_message(
				'core_update_available',
				array(
					'wp_core_current_version' => $wp_version,
					'wp_core_new_version'     => $new_wp_core_version,
					'_initiator'              => Log_Initiators::WORDPRESS,
				)
			);

			// Store updated version available, so we don't log that version again.
			update_option( "simplehistory_{$this->get_slug()}_wp_core_version_available", $new_wp_core_version );
		}
	}

	/**
	 * Called when WordPress is done checking for plugin updates.
	 * WP sets site transient 'update_plugins' when done.
	 * Log found plugin updates.
	 *
	 * @param object $updates Updates object.
	 */
	public function on_setted_update_plugins_transient( $updates ) {

		if ( empty( $updates->response ) || ! is_array( $updates->response ) ) {
			return;
		}

		$option_key      = "simplehistory_{$this->get_slug()}_plugin_updates_available";
		$checked_updates = get_option( $option_key );

		if ( ! is_array( $checked_updates ) ) {
			$checked_updates = array();
		}

		// File needed plugin API.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// For each available update.
		foreach ( $updates->response as $key => $data ) {
			// Make sure plugin directory exists or get_plugin_data will give warning.
			$file = WP_PLUGIN_DIR . '/' . $key;

			// Continue with next plugin if plugin file did not exist.
			if ( ! is_file( $file ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$fp = fopen( $file, 'r' );

			// Continue with next plugin if plugin file could not be read.
			if ( false === $fp ) {
				continue;
			}

			$plugin_info = get_plugin_data( $file, true, false );

			$plugin_new_version = $data->new_version ?? '';

			// Check if this plugin and this version has been checked/logged already.
			if ( ! array_key_exists( $key, $checked_updates ) ) {
				$checked_updates[ $key ] = array(
					'checked_version' => null,
				);
			}

			if ( $checked_updates[ $key ]['checked_version'] === $plugin_new_version ) {
				// This version has been checked/logged already.
				continue;
			}

			$checked_updates[ $key ]['checked_version'] = $plugin_new_version;

			$this->notice_message(
				'plugin_update_available',
				array(
					'plugin_name'            => $plugin_info['Name'] ?? '',
					'plugin_current_version' => $plugin_info['Version'] ?? '',
					'plugin_new_version'     => $plugin_new_version,
					'_initiator'             => Log_Initiators::WORDPRESS,
				)
			);
		}

		update_option( $option_key, $checked_updates );
	}

	/**
	 * Called when WordPress is done checking for theme updates.
	 * WP sets site transient 'update_themes' when done.
	 * Log found theme updates.
	 *
	 * @param object $updates Updates object.
	 */
	public function on_setted_update_update_themes( $updates ) {

		if ( empty( $updates->response ) || ! is_array( $updates->response ) ) {
			return;
		}

		$option_key      = "simplehistory_{$this->get_slug()}_theme_updates_available";
		$checked_updates = get_option( $option_key );

		if ( ! is_array( $checked_updates ) ) {
			$checked_updates = array();
		}

		// For each available update.
		foreach ( $updates->response as $key => $data ) {
			$theme_info = wp_get_theme( $key );

			$theme_new_version = $data['new_version'] ?? '';

			// check if this plugin and this version has been checked/logged already.
			if ( ! array_key_exists( $key, $checked_updates ) ) {
				$checked_updates[ $key ] = array(
					'checked_version' => null,
				);
			}

			if ( $checked_updates[ $key ]['checked_version'] === $theme_new_version ) {
				// This version has been checked/logged already.
				continue;
			}

			$checked_updates[ $key ]['checked_version'] = $theme_new_version;

			$this->notice_message(
				'theme_update_available',
				array(
					'theme_name'            => $theme_info['Name'] ?? '',
					'theme_current_version' => $theme_info['Version'] ?? '',
					'theme_new_version'     => $theme_new_version,
					'_initiator'            => Log_Initiators::WORDPRESS,
				)
			);
		}

		update_option( $option_key, $checked_updates );
	}

	/**
	 * Append prev and current version of update object as details in the output
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_details_output( $row ) {

		$output = '';

		$current_version     = null;
		$new_version         = null;
		$context_message_key = $row->context_message_key ?? null;

		$context = $row->context ?? array();

		switch ( $context_message_key ) {
			case 'core_update_available':
				$current_version = $context['wp_core_current_version'] ?? null;
				$new_version     = $context['wp_core_new_version'] ?? null;
				break;

			case 'plugin_update_available':
				$current_version = $context['plugin_current_version'] ?? null;
				$new_version     = $context['plugin_new_version'] ?? null;
				break;

			case 'theme_update_available':
				$current_version = $context['theme_current_version'] ?? null;
				$new_version     = $context['theme_new_version'] ?? null;
				break;
		}

		if ( $current_version && $new_version ) {
			$output .= '<p>';
			$output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
			$output .= '<em>' . __( 'Available version', 'simple-history' ) . '</em> ' . esc_html( $new_version );
			$output .= '</span> ';

			$output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
			$output .= '<em>' . __( 'Installed version', 'simple-history' ) . '</em> ' . esc_html( $current_version );
			$output .= '</span>';

			$output .= '</p>';

			// Add link to update-page, if user is allowed  to that page.
			$is_allowed_to_update_page = current_user_can( 'update_core' ) || current_user_can( 'update_themes' ) || current_user_can( 'update_plugins' );

			if ( $is_allowed_to_update_page ) {
				$output .= sprintf( '<p><a href="%1$s">', admin_url( 'update-core.php' ) );
				$output .= __( 'View all updates', 'simple-history' );
				$output .= '</a></p>';
			}
		}

		return $output;
	}
}
