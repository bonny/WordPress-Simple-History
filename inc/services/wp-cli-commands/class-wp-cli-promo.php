<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Helpers;
use WP_CLI;

/**
 * Helper for rendering tasteful upsell footers in human-readable WP-CLI output.
 *
 * Inspired by `docker compose up` and npm/composer footers — single short block,
 * shown after the actual command output, suppressed for machine-readable formats
 * and for users who already have premium installed.
 */
class WP_CLI_Promo {
	/**
	 * Print a one-block premium hint after a command's output, when appropriate.
	 *
	 * Suppressed when:
	 *  - `Helpers::show_promo_boxes()` returns false (premium installed, Extended
	 *    Settings installed, or the `simple_history/show_promo_boxes` filter said so), or
	 *  - the caller asked for a machine-readable format (json/csv/yaml/...).
	 *
	 * Output can be overridden via the `simple_history/wp_cli_promo_footer` filter.
	 *
	 * @param array $assoc_args Assoc args from the calling WP-CLI command.
	 * @return void
	 */
	public static function maybe_print_footer( $assoc_args = array() ) {
		$lines = self::get_footer_lines( $assoc_args );

		if ( count( $lines ) === 0 ) {
			return;
		}

		foreach ( $lines as $line ) {
			WP_CLI::log( (string) $line );
		}
	}

	/**
	 * Return the lines that would be printed for the given assoc args.
	 *
	 * Returns an empty array when the footer should be suppressed (premium active,
	 * machine-readable format, or filtered to empty).
	 *
	 * @param array $assoc_args Assoc args from the calling WP-CLI command.
	 * @return string[]
	 */
	public static function get_footer_lines( $assoc_args = array() ) {
		if ( ! self::should_show_footer( $assoc_args ) ) {
			return array();
		}

		// WP_CLI is absent when this helper is called from unit tests; fall back to
		// the plain string so the function stays usable outside the CLI runtime.
		$header = class_exists( WP_CLI::class )
			? WP_CLI::colorize( '%BWhat\'s next:%n' )
			: "What's next:";

		$lines = array(
			'',
			$header,
			'    Simple History Premium adds export, custom retention, alerts,',
			'    and log forwarding — built for teams running this at scale.',
			'    https://simple-history.com/premium/',
		);

		/**
		 * Filter the WP-CLI premium upsell footer lines.
		 *
		 * Return an empty array to suppress the footer entirely, or modify the
		 * lines to customise the message.
		 *
		 * @param string[] $lines      Array of output lines.
		 * @param array    $assoc_args Assoc args from the calling command.
		 */
		$lines = apply_filters( 'simple_history/wp_cli_promo_footer', $lines, $assoc_args );

		return is_array( $lines ) ? $lines : array();
	}

	/**
	 * Decide whether the footer should render for the given command invocation.
	 *
	 * Public so tests can verify the gating without touching WP_CLI::log().
	 *
	 * @param array $assoc_args Assoc args from the calling WP-CLI command.
	 * @return bool
	 */
	public static function should_show_footer( $assoc_args = array() ) {
		// Honour the project-wide promo gate — respects the premium add-on,
		// Extended Settings, and the `simple_history/show_promo_boxes` filter.
		if ( ! Helpers::show_promo_boxes() ) {
			return false;
		}

		$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';

		// Only show on the default human-readable table format. Machine-readable
		// formats (json, csv, yaml, count, ids, ...) must stay clean so scripted
		// pipelines aren't broken by promo output.
		return $format === '' || $format === 'table';
	}
}
