<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Add a "View history" item/shortcut to the admin bar.
 */
class Network_Menu_Items extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_network_menu_item' ), 40 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_item' ), 40 );
	}


	/**
	 * Adds a "View history" item/shortcut to the network admin, on blogs where Simple History is installed
	 *
	 * Useful because Simple History is something at least the author of this plugin often use on a site :)
	 *
	 * @since 2.7.1
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_network_menu_item( $wp_admin_bar ) {
		/**
		 * Filter to control if admin bar shortcut should be added
		 *
		 * @since 2.7.1
		 *
		 * @param bool $add_item Add item
		 */
		$add_item = apply_filters( 'simple_history/add_admin_bar_network_menu_item', true );

		if ( ! $add_item ) {
			return;
		}

		// Don't show for logged out users or single site mode.
		if ( ! is_user_logged_in() || ! is_multisite() ) {
			return;
		}

		// Show only when the user has at least one site, or they're a super admin.
		if ( ( is_countable( $wp_admin_bar->user->blogs ) ? count( $wp_admin_bar->user->blogs ) : 0 ) < 1 && ! is_super_admin() ) {
			return;
		}

		// User must have capability to view the history page.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'read'.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- Only checking DB-level data (plugin active status, admin URL).
			switch_to_blog( $blog->userblog_id );

			// Check if simple History is active on this blog.
			if ( Helpers::is_plugin_active( SIMPLE_HISTORY_BASENAME ) ) {
				$menu_id        = 'simple-history-blog-' . $blog->userblog_id;
				$parent_menu_id = 'blog-' . $blog->userblog_id;

				// Each network site is added by WP core with id "blog-1", "blog-2" ... "blog-n"
				// https://codex.wordpress.org/Function_Reference/add_node.
				$args = array(
					'id'     => $menu_id,
					'parent' => $parent_menu_id,
					'title'  => _x( 'View History', 'Admin bar network name', 'simple-history' ),
					'href'   => Helpers::get_history_admin_url(),
					'meta'   => array(
						'class' => 'ab-item--simplehistory',
					),
				);

				$wp_admin_bar->add_node( $args );
			}

			restore_current_blog();
		}
	}

	/**
	 * Adds a "View history" item/shortcut to the admin bar
	 *
	 * Useful because Simple History is something at least the author of this plugin often use on a site :)
	 *
	 * @since 2.7.1
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function add_admin_bar_menu_item( $wp_admin_bar ) {
		/**
		 * Filter to control if admin bar shortcut should be added.
		 *
		 * @since 2.7.1
		 *
		 * @param bool $add_item Add item
		 */
		$add_item = apply_filters( 'simple_history/add_admin_bar_menu_item', true );

		if ( ! $add_item ) {
			return;
		}

		// Don't show for logged out users.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Setting to show as page must be true.
		// if ( ! Helpers::setting_show_as_page() ) {
		// return;
		// }.

		// User must have capability to view the history page.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'read'.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		$args = array(
			'id'     => 'simple-history-view-history',
			'parent' => 'site-name',
			'title'  => _x( 'View History', 'Admin bar name', 'simple-history' ),
			'href'   => Helpers::get_history_admin_url(),
			'meta'   => array(
				'class' => 'ab-item--simplehistory',
			),
		);

		$wp_admin_bar->add_node( $args );
	}
}
