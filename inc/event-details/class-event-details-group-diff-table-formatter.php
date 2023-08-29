<?php

namespace Simple_History\Event_Details;

class Event_Details_Group_Diff_Table_Formatter extends Event_Details_Group_Formatter {
	public function to_html( $group ) {
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';
		$output .= '<tbody>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Diff_Table_Row_Formatter() );
			$output .= $item_formatter->to_html();
		}

		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}

	public function to_json( $group ) {
		$output = [];

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Diff_Table_Row_Formatter() );
			$output[] = $item_formatter->to_json();
		}

		return [
			'title' => $group->get_title(),
			'items' => $output,
		];
	}
}
