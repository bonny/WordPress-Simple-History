<?php

namespace Simple_History\Event_Details;

/**
 * Formatter for a group of items.
 */
class Event_Details_Group_Table_Formatter extends Event_Details_Group_Formatter {
	/**
	 * @inheritdoc
	 *
	 * @param Event_Details_Group $group Group to format.
	 * @return string
	 */
	public function to_html( $group ) {
		// Return empty string if there are no items.
		if ( empty( $group->items ) ) {
			return '';
		}

		$output = '';

		// Add group title if present (screen reader only for accessibility).
		if ( $group->get_title() ) {
			$output .= '<h4 class="screen-reader-text">' . esc_html( $group->get_title() ) . '</h4>';
		}
		$output .= '<table class="SimpleHistoryLogitem__keyValueTable">';
		$output .= '<tbody>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Table_Row_Formatter() );
			$output .= $item_formatter->to_html();
		}

		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}

	/**
	 * @inheritdoc
	 *
	 * @param Event_Details_Group $group Group to format.
	 * @return array<mixed>
	 */
	public function to_json( $group ) {
		$items_output = [];

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Table_Row_Formatter() );
			$items_output[] = $item_formatter->to_json();
		}

		return [
			'title' => $group->get_title(),
			'items' => $items_output,
		];
	}
}
