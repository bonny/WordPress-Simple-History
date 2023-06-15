<?php

namespace Simple_History\Dropins;

/**
 * Dropin Name: Plugin Patches
 * Dropin Description: Used to patch plugins that behave weird
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class Plugin_Patches_Dropin extends Dropin {
	public function loaded() {
		add_filter(
			'simple_history/post_logger/skip_posttypes',
			array( $this, 'woocommerce_skip_scheduled_actions_posttype' )
		);
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
