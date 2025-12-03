<?php

namespace Simple_History\Event_Details;

/**
 * Format a group of items as an inline list.
 */
class Event_Details_Group_Inline_Formatter extends Event_Details_Group_Formatter {
	/**
	 * @inheritdoc
	 *
	 * @param Event_Details_Group $group Group to format.
	 * @return string
	 */
	public function to_html( $group ) {
		$output = '';

		// Add group title if present (screen reader only for accessibility).
		if ( $group->get_title() ) {
			$output .= '<h4 class="screen-reader-text">' . esc_html( $group->get_title() ) . '</h4>';
		}

		$output .= '<p>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Default_Formatter() );
			$output        .= $item_formatter->to_html();
		}

		$output .= '</p>';

		return $output;
	}

	/**
	 * @inheritdoc
	 *
	 * @param Event_Details_Group $group Group to format.
	 * @return array<mixed>
	 */
	public function to_json( $group ) {
		$output = [];

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Default_Formatter() );
			$output[]       = $item_formatter->to_json();
		}

		return [
			'title' => $group->get_title(),
			'items' => $output,
		];
	}
}
