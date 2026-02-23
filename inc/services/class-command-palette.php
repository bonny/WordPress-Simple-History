<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Registers a command in the WordPress command palette (Cmd+K / Ctrl+K)
 * that navigates to the Simple History event log filtered by the current post.
 *
 * Available in the block editor when editing posts and pages.
 * Requires WordPress 6.3+.
 *
 * @since 5.24.0
 */
class Command_Palette extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
	}

	/**
	 * Enqueue the command palette script in the block editor.
	 */
	public function enqueue_block_editor_assets() {
		// Only for users who can view history.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'read'.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return;
		}

		if ( ! file_exists( SIMPLE_HISTORY_PATH . 'build/index-command-palette.asset.php' ) ) {
			return;
		}

		$asset = include SIMPLE_HISTORY_PATH . 'build/index-command-palette.asset.php';

		wp_enqueue_script(
			'simple-history-command-palette',
			SIMPLE_HISTORY_DIR_URL . 'build/index-command-palette.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'simple-history-command-palette', 'simple-history' );

		wp_localize_script(
			'simple-history-command-palette',
			'simpleHistoryCommandPalette',
			[
				'historyPageUrl' => Helpers::get_history_admin_url(),
			]
		);
	}
}
