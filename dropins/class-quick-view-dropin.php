<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Displays the latest events from Simple History in the admin bar using React.
 */
class Quick_View_Dropin extends Dropin {
	/** @inheritDoc */
	public function loaded() {
		// Init the plugin at prio 20 so it's easy to modify on init, without having to use a lower prio.
		add_action( 'init', [ $this, 'initialize' ], 20 );
	}

	/**
	 * Initialize the dropin.
	 * Fired from the 'init' hook.
	 */
	public function initialize() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$divi_frontend_builder_active = isset( $_GET['et_fb'] );

		// Bail if Divi frontend builder is active because it will cause React errors/issues.
		// https://github.com/bonny/WordPress-Simple-History/issues/565.
		if ( $divi_frontend_builder_active ) {
			return;
		}

		// Only available for users with the view history capability.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'read'.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		// Only available if settings is true.
		if ( ! Helpers::setting_show_in_admin_bar() ) {
			return;
		}

		// Check that admin bar is actually shown.
		// CSS and JS will be added to the HTML even if the admin bar is not shown,
		// which is wrong.
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		add_action( 'admin_bar_menu', [ $this, 'add_simple_history_to_admin_bar' ], 100 );

		// Quick View is available both in the admin and on the front end,
		// so we need to enqueue the scripts on both.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this,'enqueue_scripts' ] );
	}

	/**
	 * Add the Simple History menu item to the admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_simple_history_to_admin_bar( $wp_admin_bar ) {
		// Add the main menu item.
		$wp_admin_bar->add_node(
			array(
				// Id's are prefixed automatically, so no need to prefix them here.
				'id'    => 'simple-history',
				'title' => 'History',
				'href'  => Helpers::get_history_admin_url(),
			)
		);

		$wp_admin_bar->add_group(
			array(
				'parent' => 'simple-history',
				'id'     => 'simple-history-react-root-group',
				'title'  => '',
			),
		);

		// Must add this or the group will not be rendered.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'simple-history-react-root-group',
				'id'     => 'simple-history-subnode-1',
				'title'  => '',
			)
		);
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts() {
		$asset_file = include SIMPLE_HISTORY_PATH . 'build/index-admin-bar.asset.php';

		wp_enqueue_script(
			'simple_history_admin_bar_scripts',
			SIMPLE_HISTORY_DIR_URL . 'build/index-admin-bar.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		wp_enqueue_style(
			'simple_history_admin_bar_styles',
			SIMPLE_HISTORY_DIR_URL . 'build/index-admin-bar.css',
			[],
			$asset_file['version']
		);

		wp_localize_script(
			'simple_history_admin_bar_scripts',
			'simpleHistoryAdminBar',
			[
				'adminPageUrl'              => Helpers::get_history_admin_url(),
				'viewSettingsUrl'           => Helpers::get_settings_page_url(),
				// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'read'.
				'currentUserCanViewHistory' => current_user_can( Helpers::get_view_history_capability() ),
			],
		);
	}
}
