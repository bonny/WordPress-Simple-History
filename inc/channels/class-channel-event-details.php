<?php

namespace Simple_History\Channels;

use Simple_History\Details_Text;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Builds the event details, the "what changed" part of an event, for channels.
 *
 * The details are rendered by the same Event Details API that the GUI uses,
 * so each logger decides which context keys matter and how they are labelled,
 * and empty or unchanged items are left out. They are built once per event,
 * from the row the logger just inserted, and shared by every channel.
 *
 * @since 5.36.0
 */
class Channel_Event_Details {
	/**
	 * Max number of characters in one detail line or value.
	 * Keeps diffs of long texts like post content from bloating log files.
	 *
	 * @var int
	 */
	public const MAX_VALUE_LENGTH = 200;

	/**
	 * Max number of detail lines or items per event.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 20;

	/**
	 * Get the details of a just-logged event.
	 *
	 * Returns:
	 * - text: "Label: Value" lines, one per line, with "old → new" for changes.
	 *   Raw text, not escaped for any output format.
	 * - items: changed values keyed by context key, for structured formats.
	 *   Each item has new and/or prev, plus the translated label.
	 *   Only loggers that use the Event Details API have items.
	 *
	 * @param array $context Context of the event, as passed to the inserted hook.
	 * @param array $data    Event row data, as passed to the inserted hook.
	 * @return array{text: string, items: array<string, array<string, string>>}
	 */
	public static function get( array $context, array $data ): array {
		$empty = [
			'text'  => '',
			'items' => [],
		];

		try {
			$details = Simple_History::get_instance()->get_log_row_details_output( self::build_row( $context, $data ) );
			$html    = $details->to_html();
		} catch ( \Throwable $e ) {
			// Details are an extra. A logger that fails to render them
			// must not stop the event from reaching the channels.
			return $empty;
		}

		return [
			'text'  => self::build_text( Details_Text::from_html( $html ) ),
			'items' => $details instanceof Event_Details_Container ? self::build_items( $details ) : [],
		];
	}

	/**
	 * Build a row object like the one the log query returns,
	 * so loggers render details at log time the same way as in the GUI.
	 *
	 * @param array $context Event context.
	 * @param array $data    Event row data.
	 * @return object
	 */
	private static function build_row( array $context, array $data ) {
		// Stored context values are strings, everything else is saved as JSON.
		// Do the same so loggers see the values they see when reading from the database.
		$row_context = [];

		foreach ( $context as $key => $value ) {
			$row_context[ $key ] = is_string( $value ) ? $value : Helpers::json_encode( $value );
		}

		$id = $data['id'] ?? null;

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		return (object) [
			'id'                  => $id,
			'date'                => $data['date'] ?? '',
			'logger'              => $data['logger'] ?? '',
			'level'               => $data['level'] ?? '',
			'message'             => $data['message'] ?? '',
			'occasionsID'         => $data['occasionsID'] ?? '',
			'initiator'           => $data['initiator'] ?? '',
			'repeatCount'         => '1',
			'subsequentOccasions' => '1',
			'maxId'               => $id,
			'minId'               => $id,
			'context_message_key' => $row_context['_message_key'] ?? null,
			'context'             => $row_context,
		];
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Limit the plain text details to MAX_ITEMS lines of MAX_VALUE_LENGTH characters.
	 *
	 * @param string $text Plain text details, one entry per line.
	 * @return string
	 */
	private static function build_text( string $text ): string {
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), 'strlen' ) );

		$limited = array_map( [ self::class, 'truncate' ], array_slice( $lines, 0, self::MAX_ITEMS ) );

		if ( count( $lines ) > self::MAX_ITEMS ) {
			$limited[] = '…';
		}

		return implode( "\n", $limited );
	}

	/**
	 * Collect changed values from the details, keyed by context key.
	 *
	 * Items without a context key, or without a text value (like image
	 * diffs), are left out. They are still part of the text details.
	 *
	 * @param Event_Details_Container $details Details from the logger.
	 * @return array<string, array<string, string>>
	 */
	private static function build_items( Event_Details_Container $details ): array {
		$items = [];

		foreach ( $details->groups as $group ) {
			foreach ( $group->items as $item ) {
				$key = $item->slug_new ?? $item->slug_prev ?? null;

				if ( $key === null || ( $item->new_value === null && $item->prev_value === null ) ) {
					continue;
				}

				$values = [];

				if ( $item->name !== null && $item->name !== '' ) {
					$values['label'] = $item->name;
				}

				if ( $item->new_value !== null ) {
					$values['new'] = self::truncate( $item->new_value );
				}

				if ( $item->prev_value !== null ) {
					$values['prev'] = self::truncate( $item->prev_value );
				}

				$items[ $key ] = $values;

				if ( count( $items ) >= self::MAX_ITEMS ) {
					return $items;
				}
			}
		}

		return $items;
	}

	/**
	 * Cut a string to MAX_VALUE_LENGTH characters.
	 *
	 * @param string $value Value to cut.
	 * @return string
	 */
	private static function truncate( string $value ): string {
		// Cuts on a character boundary, also without mbstring.
		if ( Helpers::truncate_request_value( $value, self::MAX_VALUE_LENGTH ) === $value ) {
			return $value;
		}

		return Helpers::truncate_request_value( $value, self::MAX_VALUE_LENGTH - 1 ) . '…';
	}
}
