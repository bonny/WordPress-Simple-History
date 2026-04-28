<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Helpers;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP-CLI command that prints Simple History version + add-on status.
 */
class WP_CLI_Info_Command extends WP_CLI_Command {
	/**
	 * Show Simple History version, premium add-on status, and useful links.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history info
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$version = defined( 'SIMPLE_HISTORY_VERSION' ) ? SIMPLE_HISTORY_VERSION : '?';

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BSimple History%n ' . $version ) );
		WP_CLI::log( '' );

		$premium_active = Helpers::is_premium_add_on_active();

		if ( $premium_active ) {
			WP_CLI::log(
				WP_CLI::colorize( '%GPremium add-on:%n active' )
			);

			// If a license/expiry helper is available, surface it.
			$license_summary = self::get_license_summary();
			if ( $license_summary !== '' ) {
				WP_CLI::log( '  ' . $license_summary );
			}
		} else {
			WP_CLI::log(
				WP_CLI::colorize( '%YPremium add-on:%n not installed' )
			);
			WP_CLI::log( '  With Premium you also get:' );
			WP_CLI::log( '    · Export to CSV / JSON' );
			WP_CLI::log( '    · Custom retention rules' );
			WP_CLI::log( '    · Alerts (email, Slack, webhook)' );
			WP_CLI::log( '    · Log forwarding (syslog, etc.)' );
			WP_CLI::log( '' );
			WP_CLI::log( '  Learn more: https://simple-history.com/premium/' );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Useful subcommands:' );
		WP_CLI::log( '  wp simple-history list           List events' );
		WP_CLI::log( '  wp simple-history search <q>     Search events' );
		WP_CLI::log( '  wp simple-history event get <id> Show one event' );
		WP_CLI::log( '  wp simple-history db stats       DB stats' );
		WP_CLI::log( '' );
		WP_CLI::log( 'Run `wp help simple-history` for the full reference.' );
	}

	/**
	 * Build a one-line summary of the premium license, if a public helper exposes it.
	 *
	 * Falls back to an empty string when the premium plugin doesn't expose a known
	 * helper — the calling code just skips the line in that case.
	 *
	 * @return string
	 */
	private static function get_license_summary() {
		// Premium exposes license helpers under its own namespace; we reach for them
		// defensively so this command never fatals when premium is missing or older
		// than the helper we look for.
		if ( ! class_exists( '\\Simple_History_Premium\\License' ) ) {
			return '';
		}

		$license_class = '\\Simple_History_Premium\\License';

		if ( method_exists( $license_class, 'get_status_summary' ) ) {
			$summary = call_user_func( array( $license_class, 'get_status_summary' ) );
			if ( is_string( $summary ) && $summary !== '' ) {
				return $summary;
			}
		}

		return '';
	}
}
