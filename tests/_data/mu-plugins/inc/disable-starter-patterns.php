<?php
/**
 * Disable the "Choose a pattern" modal when creating new pages.
 *
 * WP 6.8 shows a starter pattern modal every time a new page is created.
 * This interferes with acceptance tests because the modal overlay blocks
 * editor interactions. There is no official preference to disable it yet.
 *
 * The modal only appears when post-content patterns exist.
 * Removing them at a late priority catches both core and theme patterns.
 *
 * @see https://github.com/WordPress/gutenberg/issues/56181
 */
add_action(
	'init',
	function () {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( $registry->get_all_registered() as $pattern ) {
			$block_types = $pattern['blockTypes'] ?? [];

			if ( in_array( 'core/post-content', $block_types, true ) ) {
				unregister_block_pattern( $pattern['name'] );
			}
		}
	},
	999
);
