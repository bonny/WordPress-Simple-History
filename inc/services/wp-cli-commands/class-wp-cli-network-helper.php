<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Event;
use Simple_History\Log_Query;
use Simple_History\Simple_History;
use WP_CLI;

/**
 * Shared helpers for the WP-CLI event commands.
 *
 * Keeps the multisite + Premium guard behind `--network` consistent across
 * every command that offers it (`list`, `event search`, `event get`,
 * `event stick`, `event unstick`, `event is-sticky`, `event list-sticky`),
 * plus the `<event_id>` positional those commands share. Each method exits
 * the CLI process on failure via WP_CLI::error(), so callers can treat the
 * return value as non-null.
 *
 * @since 5.33.0
 */
class WP_CLI_Network_Helper {
	/**
	 * What to tell someone who asked for the network log on a site that
	 * has no provider for it.
	 *
	 * A method rather than a constant because the text is translated, and a
	 * class constant cannot hold a function call.
	 *
	 * @since 5.33.0
	 *
	 * @return string
	 */
	private static function no_provider_message(): string {
		return __( '--network requires Simple History Premium, which adds network event logging. See: https://simple-history.com/premium/', 'simple-history' );
	}

	/**
	 * Validate an `<event_id>` positional, erroring out if it is not a number.
	 *
	 * Every event command takes the same positional and rejected it in its
	 * own words; this is the one place that decides what "an event id" is.
	 *
	 * @since 5.33.0
	 *
	 * @param mixed $raw Raw CLI positional value.
	 * @return int
	 */
	public static function parse_event_id( $raw ): int {
		if ( ! is_numeric( $raw ) ) {
			WP_CLI::error( 'Event ID must be an integer.' );
		}

		return (int) $raw;
	}

	/**
	 * Read the `--network` flag and reject if used on a single-site install.
	 *
	 * @param array $assoc_args Associative args as received by the command.
	 * @return bool True if the command should run in network mode.
	 */
	public static function is_network_mode( array $assoc_args ): bool {
		$is_network = ! empty( $assoc_args['network'] );

		if ( $is_network && ! is_multisite() ) {
			WP_CLI::error( __( '--network requires a multisite network.', 'simple-history' ) );
		}

		return $is_network;
	}

	/**
	 * Resolve a Log_Query for the requested scope. Exits via WP_CLI::error()
	 * if `--network` was passed but no provider (Premium) has registered a
	 * network-scoped factory.
	 *
	 * @param bool $is_network Result of is_network_mode().
	 * @return Log_Query
	 */
	public static function get_log_query( bool $is_network ): Log_Query {
		$query = $is_network
			? Simple_History::get_instance()->get_network_log_query()
			: new Log_Query();

		if ( $query === null ) {
			WP_CLI::error( self::no_provider_message() );
		}

		return $query;
	}

	/**
	 * Resolve an Event for the requested scope. Exits via WP_CLI::error()
	 * if `--network` was passed without a provider, or if the event doesn't
	 * exist in the chosen scope.
	 *
	 * @param int  $event_id   Validated event ID.
	 * @param bool $is_network Result of is_network_mode().
	 * @return Event Event that is guaranteed to exist.
	 */
	public static function get_event( int $event_id, bool $is_network ): Event {
		if ( $is_network ) {
			$event = Simple_History::get_instance()->get_network_event( $event_id );

			if ( $event === null ) {
				WP_CLI::error( self::no_provider_message() );
			}
		} else {
			$event = Event::get( $event_id );
		}

		if ( ! $event || ! $event->exists() ) {
			WP_CLI::error( "Event {$event_id} does not exist." );
		}

		return $event;
	}
}
