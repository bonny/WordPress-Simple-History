<?php

namespace Simple_History\Dropins;

use WP_REST_Request;

/**
 * Dropin Name: Plugin Patches
 * Dropin Description: Used to patch plugins that causes issues with Simple History in any way.
 * Dropin URI: https://simple-history.com
 * Author: Pär Thernström
 */
class Plugin_Patches_Dropin extends Dropin {
	public function loaded() {
		add_filter(
			'simple_history/post_logger/skip_posttypes',
			array( $this, 'woocommerce_skip_scheduled_actions_posttype' )
		);

		$this->patch_co_authors_plus();
	}

	/**
	 * Patch for plugin Co Authors Plus.
	 *
	 * When editing a post and when user writes text in
	 * the "Select an author" metabox for the plugin,
	 * an AJAX request is performed that searches for users.
	 * If a user does not exist on the 'author' taxonomy
	 * it is added, and it is logged like this:
	 * 'Added term "testuser1" in taxonomy "author"'.
	 * It looks bad so we skip logging of this.
	 *
	 * We check that we have a REST_REQUEST and that the current route is for co authors plus.
	 */
	public function patch_co_authors_plus() {
		add_filter(
			'rest_pre_dispatch',
			array( $this, 'patch_co_authors_plus_handle_rest_pre_dispath' ),
			10,
			3
		);
	}

	/**
	 * @param mixed $result
	 * @param WP_REST_Server $server
	 * @param WP_REST_Request $request
	 */
	public function patch_co_authors_plus_handle_rest_pre_dispath( $result, $server, $request ) {
		// Act on request to the coauthors/v1/search route.
		if ( $request->get_route() === '/coauthors/v1/search' ) {
			// Skip logging of 'author' taxonomy.
			add_filter(
				'simple_history/categories_logger/skip_taxonomies',
				function( $taxononomies_to_skip ) {
					$taxononomies_to_skip[] = 'author';
					return $taxononomies_to_skip;
				},
			);
		}

		// Return null to not hijack the request.
		return null;
	}

	/**
	 * Skip logging of WooCommerce scheduled actions/cron related things,
	 * stored in the scheduled-action"post type. If not disabled the log can be filled with
	 * a large amount of actions for this posttype.
	 *
	 * @since 2.3
	 */
	public function woocommerce_skip_scheduled_actions_posttype( $skip_posttypes ) {
		$skip_posttypes[] = 'scheduled-action';
		return $skip_posttypes;
	}
}
