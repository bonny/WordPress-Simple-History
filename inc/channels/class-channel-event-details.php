<?php

namespace Simple_History\Channels;

use Simple_History\Details_Text;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Item_RAW_Formatter;
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
	 *   Each item has new and/or prev, plus the translated label. An item
	 *   with a list of changes, like the changed lines of post content
	 *   (key post_content_diff), has changes instead: a list of new/prev.
	 * - list: every item in display order, also those without a context key,
	 *   for alerts that show the details as a list. Each item has label,
	 *   new and/or prev, and key when it has one. A change between two values
	 *   too long to show has changed = true instead of the values.
	 *
	 * Only loggers that use the Event Details API have items and list. Loggers
	 * that still return HTML only have text.
	 *
	 * @param array $context Context of the event, as passed to the inserted hook.
	 * @param array $data    Event row data, as passed to the inserted hook.
	 * @return array{text: string, items: array<string, array<string, string|array<int, array<string, string|bool>>>>, list: array<int, array<string, string|bool>>}
	 */
	public static function get( array $context, array $data ): array {
		$empty = [
			'text'  => '',
			'items' => [],
			'list'  => [],
		];

		try {
			$details = Simple_History::get_instance()->get_log_row_details_output( self::build_row( $context, $data ) );
			$html    = $details->to_html();
		} catch ( \Throwable $e ) {
			// Details are an extra. A logger that fails to render them
			// must not stop the event from reaching the channels.
			return $empty;
		}

		$is_structured = $details instanceof Event_Details_Container;

		return [
			'text'  => self::build_text( Details_Text::from_html( $html, true ) ),
			'items' => $is_structured ? self::build_items( $details ) : [],
			'list'  => $is_structured ? self::build_list( $details ) : [],
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
	 * diffs), are left out. They are still part of the text details. An item
	 * with a list of changes, like the changed lines of post content, gets
	 * "changes" with a new/prev entry per change instead of new and prev.
	 *
	 * @param Event_Details_Container $details Details from the logger.
	 * @return array<string, array<string, string|array<int, array<string, string|bool>>>>
	 */
	private static function build_items( Event_Details_Container $details ): array {
		$items = [];

		foreach ( $details->groups as $group ) {
			foreach ( $group->items as $item ) {
				$json    = $item->get_formatter()->to_json();
				$changes = self::get_changes( $json );

				// A list of changes, like the changed lines of post content.
				if ( $changes !== [] && is_string( $json['slug_new'] ?? null ) ) {
					$items[ $json['slug_new'] ] = array_filter(
						[
							'label'   => $item->name,
							'changes' => array_slice( $changes, 0, self::MAX_ITEMS ),
						]
					);

					if ( count( $items ) >= self::MAX_ITEMS ) {
						return $items;
					}

					continue;
				}

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
	 * Collect every item in display order, with or without a context key.
	 *
	 * Values come from each item's formatter, so items rendered by a raw
	 * formatter get their plain text value too. Items without a text value,
	 * like image diffs, are left out.
	 *
	 * @param Event_Details_Container $details Details from the logger.
	 * @return array<int, array<string, string|bool>>
	 */
	private static function build_list( Event_Details_Container $details ): array {
		$list = [];

		foreach ( $details->groups as $group ) {
			foreach ( $group->items as $item ) {
				$formatter = $item->get_formatter();
				$json      = $formatter->to_json();

				$label = $json['name'] ?? $item->name;
				$new   = $json['new_value'] ?? $item->new_value;
				$prev  = $json['prev_value'] ?? $item->prev_value;

				$new  = is_scalar( $new ) && (string) $new !== '' ? (string) $new : null;
				$prev = is_scalar( $prev ) && (string) $prev !== '' ? (string) $prev : null;

				// A list of changes, like the changed lines of post content:
				// one entry per change.
				$changes = self::get_changes( $json );

				if ( $changes !== [] ) {
					$key = is_string( $json['slug_new'] ?? null ) ? [ 'key' => $json['slug_new'] ] : [];

					foreach ( $changes as $change ) {
						$list[] = [ 'label' => is_string( $label ) ? $label : '' ] + $change + $key;

						if ( count( $list ) >= self::MAX_ITEMS ) {
							return $list;
						}
					}

					continue;
				}

				// Raw HTML without a label and value of its own, like the post
				// logger's content diff, rows that third-party code adds, or
				// custom fields: one entry per line of its text. A raw item
				// with a previous value has structured data, which is kept.
				$has_label         = is_string( $label ) && $label !== '';
				$is_unlabelled_raw = $formatter instanceof Event_Details_Item_RAW_Formatter && $prev === null && ( ! $has_label || $new === null );

				if ( $is_unlabelled_raw ) {
					foreach ( self::build_list_from_html( $formatter->to_html(), is_string( $label ) ? $label : '' ) as $entry ) {
						$list[] = $entry;

						if ( count( $list ) >= self::MAX_ITEMS ) {
							return $list;
						}
					}

					continue;
				}

				if ( $new === null && $prev === null ) {
					continue;
				}

				$entry = [
					'label' => is_string( $label ) ? $label : '',
				];

				$key = $item->slug_new ?? $item->slug_prev ?? null;

				if ( $key !== null ) {
					$entry['key'] = $key;
				}

				// Two long texts, like post content: cut-off starts of both say nothing
				// about what changed, so only say that it changed.
				$is_long = self::truncate( (string) $new ) !== (string) $new || self::truncate( (string) $prev ) !== (string) $prev;

				if ( $new !== null && $prev !== null && $is_long ) {
					$entry['changed'] = true;
				} else {
					if ( $new !== null ) {
						$entry['new'] = self::truncate( $new );
					}

					if ( $prev !== null ) {
						$entry['prev'] = self::truncate( $prev );
					}
				}

				$list[] = $entry;

				if ( count( $list ) >= self::MAX_ITEMS ) {
					return $list;
				}
			}
		}

		return $list;
	}

	/**
	 * Get the "changes" list of an item's JSON as new/prev entries.
	 *
	 * Like other values, a change between two values too long to show has
	 * changed = true instead of the values.
	 *
	 * @param array $json JSON output of an item's formatter.
	 * @return array<int, array<string, string|bool>> Empty when the item has no changes list.
	 */
	private static function get_changes( array $json ): array {
		if ( ! is_array( $json['changes'] ?? null ) ) {
			return [];
		}

		$changes = [];

		foreach ( $json['changes'] as $change ) {
			$new  = is_scalar( $change['new_value'] ?? null ) && (string) $change['new_value'] !== '' ? (string) $change['new_value'] : null;
			$prev = is_scalar( $change['prev_value'] ?? null ) && (string) $change['prev_value'] !== '' ? (string) $change['prev_value'] : null;

			$is_long = self::truncate( (string) $new ) !== (string) $new || self::truncate( (string) $prev ) !== (string) $prev;

			if ( $new !== null && $prev !== null && $is_long ) {
				$changes[] = [ 'changed' => true ];

				continue;
			}

			$entry = [];

			if ( $new !== null ) {
				$entry['new'] = self::truncate( $new );
			}

			if ( $prev !== null ) {
				$entry['prev'] = self::truncate( $prev );
			}

			if ( $entry === [] ) {
				continue;
			}

			$changes[] = $entry;
		}

		return $changes;
	}

	/**
	 * Turn raw details HTML into list entries, one per key-value pair or line.
	 *
	 * The value is the whole change as text, like "old → new", so it goes in
	 * new. Lines that are not a pair get the item's label.
	 *
	 * @param string $html  Details HTML.
	 * @param string $label Label of the item, for lines without one.
	 * @return array<int, array<string, string>>
	 */
	private static function build_list_from_html( $html, $label ) {
		$list = [];

		foreach ( Details_Text::pairs( $html ) as $pair ) {
			// A pair inside a labelled item keeps its key in the value,
			// unless the key is the item's label.
			$use_key = $pair['key'] !== '' && ( $label === '' || $pair['key'] === $label );
			$value   = $use_key || $pair['key'] === '' ? $pair['value'] : Details_Text::format_pair( $pair['key'], $pair['value'] );

			$list[] = [
				'label' => $use_key ? $pair['key'] : $label,
				'new'   => self::truncate( $value ),
			];
		}

		return $list;
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
