<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Adds a sidebar panel to the Gutenberg block editor showing Simple History events for the current post.
 */
class Gutenberg_Sidebar_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		// Only load if experimental features are enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Enqueue Gutenberg sidebar scripts and styles.
	 */
	public function enqueue_block_editor_assets() {
		// Only load on WordPress 6.3+ to avoid compatibility issues.
		global $wp_version;
		if ( version_compare( $wp_version, '6.3', '<' ) ) {
			return;
		}

		// Only load on post edit screens where Simple History makes sense.
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' ) {
			return;
		}

		// Check if user has permission to view Simple History.
		if ( ! $this->user_can_view_history() ) {
			return;
		}

		// Only load for post types that have Simple History logging.
		if ( ! $this->post_type_has_history_logging( $screen->post_type ) ) {
			return;
		}

		// Ensure the built asset file exists.
		$asset_file_path = SIMPLE_HISTORY_PATH . 'build/index-gutenberg.asset.php';
		if ( ! file_exists( $asset_file_path ) ) {
			return;
		}

		$asset_file = include $asset_file_path;

		// Validate asset file structure.
		if ( ! is_array( $asset_file ) || ! isset( $asset_file['dependencies'], $asset_file['version'] ) ) {
			return;
		}

		wp_register_script(
			'simple_history_gutenberg_sidebar',
			SIMPLE_HISTORY_DIR_URL . 'build/index-gutenberg.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_script( 'simple_history_gutenberg_sidebar' );
		wp_set_script_translations( 'simple_history_gutenberg_sidebar', 'simple-history' );
	}

	/**
	 * Check if current user can view Simple History.
	 *
	 * @return bool
	 */
	private function user_can_view_history() {
		$view_capability = Helpers::get_view_history_capability();
		return current_user_can( $view_capability );
	}

	/**
	 * Check if the given post type has Simple History logging enabled.
	 * For MVP, we'll enable it for posts and pages.
	 *
	 * @param string $post_type Post type to check.
	 * @return bool
	 */
	private function post_type_has_history_logging( $post_type ) {
		$supported_post_types = [ 'post', 'page' ];

		/**
		 * Filter which post types show the Simple History sidebar panel.
		 *
		 * @param array $supported_post_types Array of post type names.
		 */
		$supported_post_types = apply_filters(
			'simple_history/gutenberg_sidebar/supported_post_types',
			$supported_post_types
		);

		return in_array( $post_type, $supported_post_types, true );
	}
}
