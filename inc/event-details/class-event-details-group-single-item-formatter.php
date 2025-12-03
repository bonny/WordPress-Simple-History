<?php

namespace Simple_History\Event_Details;

/**
 * A group with a single item, just plain output, no table or inline or similar.
 * They are added to the details group without a group first (group is generated in add function).
 * TODO: How to handle values? Placeholders?, {} or %s-format?
 */
class Event_Details_Group_Single_Item_Formatter extends Event_Details_Group_Formatter {
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

		foreach ( $group->items as $item ) {
			$formatter = $item->get_formatter();
			$output   .= $formatter->to_html();
		}

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

		// Use same formatter as inline items.
		foreach ( $group->items as $item ) {
			$formatter = $item->get_formatter();
			$output[]  = $formatter->to_json();
		}

		return [
			'title' => $group->get_title(),
			'items' => $output,
		];
	}
}
