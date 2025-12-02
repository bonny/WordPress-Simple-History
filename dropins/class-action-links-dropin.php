<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Add link to Simple History to the list of action links available following bulk plugin updates, single plugin installation, and single plugin updates.
 */
class Action_Links_Dropin extends Dropin {
	/** @inheritDoc */
	public function loaded() {
		add_filter( 'update_bulk_plugins_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'update_plugin_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'install_plugin_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'update_bulk_theme_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'update_translations_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'install_theme_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
		add_filter( 'update_theme_complete_actions', [ $this, 'add_simple_history_link' ], 10, 1 );
	}

	/**
	 * Add link to Simple History to the list of action links available following bulk plugin updates, single plugin installation, and single plugin updates.
	 *
	 * @param array $update_actions Array of action links.
	 * @return array
	 */
	public function add_simple_history_link( $update_actions ) {
		/**
		 * Filters whether to show the action link to Simple History.
		 *
		 * @since 4.17.0
		 */
		$show_link = apply_filters( 'simple_history/show_action_link', true );

		// Bail if filter returns false.
		if ( $show_link === false ) {
			return $update_actions;
		}

		// Bail if the current user can't view the history.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'manage_options'.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return $update_actions;
		}

		$update_actions['simple_history'] = sprintf(
			'<a href="%s" target="_parent">%s</a>',
			Helpers::get_history_admin_url(),
			__( 'Go to Simple History', 'simple-history' )
		);

		return $update_actions;
	}
}
