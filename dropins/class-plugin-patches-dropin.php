<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Plugin Patches
 * Dropin Description: Used to patch plugins that causes issues with Simple History in any way.
 * Dropin URI: https://simple-history.com
 * Author: Pär Thernström
 */
class Plugin_Patches_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_filter(
			'simple_history/post_logger/skip_posttypes',
			array( $this, 'woocommerce_skip_scheduled_actions_posttype' )
		);

		add_filter(
			'simple_history/post_logger/skip_posttypes',
			array( $this, 'woocommerce_skip_hpos_posttype' )
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
		Helpers::disable_taxonomy_log( 'author', Helpers::is_plugin_active( 'co-authors-plus/co-authors-plus.php' ) );
	}

	/**
	 * Skip logging of WooCommerce scheduled actions/cron related things,
	 * stored in the scheduled-action"post type. If not disabled the log can be filled with
	 * a large amount of actions for this posttype.
	 *
	 * @since 2.3
	 * @param array $skip_posttypes Array with post types to skip.
	 * @return array
	 */
	public function woocommerce_skip_scheduled_actions_posttype( $skip_posttypes ) {
		$skip_posttypes[] = 'scheduled-action';
		return $skip_posttypes;
	}

	/**
	 * WooCommerce new High-Performance Order Storage (HPOS) system
	 * uses a custom post type called "shop_order_placehold" to store
	 * order placeholder posts.
	 *
	 * If post type is not skipped every manually added new WC order is logged as
	 * 'Updated post ""'.
	 *
	 * @since 4.6.0
	 * @param array $skip_posttypes Array with post types to skip.
	 * @return array
	 */
	public function woocommerce_skip_hpos_posttype( $skip_posttypes ) {
		$skip_posttypes[] = 'shop_order_placehold';
		return $skip_posttypes;
	}
}
