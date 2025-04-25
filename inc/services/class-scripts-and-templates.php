<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Setup scripts and templates.
 */
class Scripts_And_Templates extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_footer', array( $this, 'add_logger_javascript_in_admin_footer' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Output logger JavaScript into admin footer.
	 */
	public function add_logger_javascript_in_admin_footer() {
		if ( Helpers::is_on_our_own_pages() ) {
			// Call plugins so they can add their js.
			foreach ( $this->simple_history->get_instantiated_loggers() as $one_logger ) {
				$one_logger['instance']->admin_js();
			}
		}
	}

	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Bail if not on our own pages.
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		add_thickbox();

		wp_enqueue_style(
			'simple_history_styles',
			SIMPLE_HISTORY_DIR_URL . 'css/styles.css',
			false,
			SIMPLE_HISTORY_VERSION
		);

		wp_enqueue_style(
			'simple_history_icons',
			SIMPLE_HISTORY_DIR_URL . 'css/icons.css',
			false,
			SIMPLE_HISTORY_VERSION
		);

		wp_enqueue_style(
			'simple_history_utility_styles',
			SIMPLE_HISTORY_DIR_URL . 'css/utility-classes.css',
			false,
			SIMPLE_HISTORY_VERSION
		);

		wp_enqueue_script(
			'simple_history_script',
			SIMPLE_HISTORY_DIR_URL . 'js/scripts.js',
			array( 'jquery', 'backbone', 'wp-util' ),
			SIMPLE_HISTORY_VERSION,
			true
		);

		// Translations that we use in JavaScript.
		wp_localize_script(
			'simple_history_script',
			'simpleHistoryScriptVars',
			array(
				'settingsConfirmClearLog' => __( 'Remove all log items?', 'simple-history' ),
			)
		);

		// Call plugins admin_css-method, so they can add CSS.
		foreach ( $this->simple_history->get_instantiated_loggers() as $one_logger ) {
			$one_logger['instance']->admin_css();
		}

		/**
		 * Fires when the admin scripts have been enqueued.
		 * Only fires on any of the pages where Simple History is used.
		 *
		 * @since 2.0
		 *
		 * @param Simple_History $instance The Simple_History instance.
		 */
		do_action( 'simple_history/enqueue_admin_scripts', $this->simple_history );
	}
}
