<?php

// phpcs:disable PSR12.Properties.ConstantVisibility.NotFound

/**
 * Describes log initiator, i.e. who caused to log event to happened
 */
class SimpleLoggerLogInitiators {

	// A wordpress user that at the log event created did exist in the wp database
	// May have been deleted when the log is viewed.
	const WP_USER = 'wp_user';

	// Cron job run = wordpress initiated
	// Email sent to customer on webshop = system/wordpress/anonymous web user
	// Javascript error occurred on website = anonymous web user.
	const WEB_USER = 'web_user';

	// WordPress core or plugins updated automatically via wp-cron.
	const WORDPRESS = 'wp';

	// WP CLI / terminal.
	const WP_CLI = 'wp_cli';

	// I dunno.
	const OTHER = 'other';
}
