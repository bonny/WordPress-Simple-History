<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Helpers;
use Simple_History\Services\Licences_Settings_Page;
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

			WP_CLI::log(
				'  ' . __( 'Thank you for supporting Simple History — it keeps the plugin going!', 'simple-history' )
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

		if ( Helpers::experimental_features_is_enabled() ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%YExperimental features:%n enabled' ) );
		}

		WP_CLI::log( '' );
		WP_CLI::log( 'Useful subcommands:' );
		WP_CLI::log( '  wp simple-history event list                List events' );
		WP_CLI::log( '  wp simple-history event list --search=<q>   Search events' );
		WP_CLI::log( '  wp simple-history event get <id>            Show one event' );
		WP_CLI::log( '  wp simple-history db stats                  DB stats' );
		WP_CLI::log( '' );
		WP_CLI::log( 'Run `wp help simple-history` for the full reference.' );
	}

	/**
	 * Build a one-line summary of the premium license.
	 *
	 * The license key and the server's reply are stored by core, not by premium,
	 * so both are read from Licences_Settings_Page. An earlier version looked for
	 * a `\Simple_History_Premium\License` class that has never existed, which
	 * meant this line was silently never printed.
	 *
	 * The key itself is deliberately not included — it is a credential, and this
	 * output gets pasted into support threads.
	 *
	 * @return string
	 */
	private static function get_license_summary() {
		$license_key = Licences_Settings_Page::get_license_key();

		if ( empty( $license_key ) ) {
			return __( 'License: no key entered', 'simple-history' );
		}

		$license_message = Licences_Settings_Page::get_license_message();

		if ( is_string( $license_message ) && $license_message !== '' ) {
			/* translators: %s: license status message returned by the license server. */
			return sprintf( __( 'License: %s', 'simple-history' ), $license_message );
		}

		return __( 'License: key entered', 'simple-history' );
	}
}
