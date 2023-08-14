<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

class Network_Menu_Items extends Service {
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

		// Setting to show as page must be true
		if ( ! $this->simple_history->setting_show_as_page() ) {
			return;
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->simple_history->get_view_history_capability() ) ) {
			return;
		}

		foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
			switch_to_blog( $blog->userblog_id );

			// Check if simple History is active on this blog.
			if ( Helpers::is_plugin_active( SIMPLE_HISTORY_BASENAME ) ) {
				$menu_id = 'simple-history-blog-' . $blog->userblog_id;
				$parent_menu_id = 'blog-' . $blog->userblog_id;
				$url = admin_url(
					apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page'
				);

				// Each network site is added by WP core with id "blog-1", "blog-2" ... "blog-n"
				// https://codex.wordpress.org/Function_Reference/add_node
				$args = array(
					'id' => $menu_id,
					'parent' => $parent_menu_id,
					'title' => _x( 'View History', 'Admin bar network name', 'simple-history' ),
					'href' => $url,
					'meta' => array(
						'class' => 'ab-item--simplehistory',
					),
				);

				$wp_admin_bar->add_node( $args );
			} // End if().

			restore_current_blog();
		} // End foreach().
	}

	/**
	 * Adds a "View history" item/shortcut to the admin bar
	 *
	 * Useful because Simple History is something at least the author of this plugin often use on a site :)
	 *
	 * @since 2.7.1
	 */
	public function add_admin_bar_menu_item( $wp_admin_bar ) {
		/**
		 * Filter to control if admin bar shortcut should be added
		 *
		 * @since 2.7.1
		 *
		 * @param bool $add_item Add item
		 */
		$add_item = apply_filters( 'simple_history/add_admin_bar_menu_item', true );

		if ( ! $add_item ) {
			return;
		}

		// Don't show for logged out users
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Setting to show as page must be true
		if ( ! $this->simple_history->setting_show_as_page() ) {
			return;
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->simple_history->get_view_history_capability() ) ) {
			return;
		}

		$menu_id = 'simple-history-view-history';
		$parent_menu_id = 'site-name';
		$url = admin_url( apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page' );

		$args = array(
			'id' => $menu_id,
			'parent' => $parent_menu_id,
			'title' => _x( 'View History', 'Admin bar name', 'simple-history' ),
			'href' => $url,
			'meta' => array(
				'class' => 'ab-item--simplehistory',
			),
		);

		$wp_admin_bar->add_node( $args );
	}

}
