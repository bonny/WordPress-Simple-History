<?php

namespace Simple_History;

/**
 * A group with a single item, just plain output, no table or inline or similar.
 * They are added to the details group without a group first (group is generated in add function).
 * TODO: How to handle values? Placeholders?, {} or %s-format?
 */
class Event_Details_Group_Single_Item_Formatter extends Event_Details_Group_Formatter {
	public function get_html_output( $group ) {
		$output = '';

		foreach ( $group->items as $item ) {
			if ( $item->has_formatter() ) {
				$formatter = $item->get_formatter();
			} else {
				$formatter = new Event_Details_Item_Default_Formatter( $item );
			}

			$output .= $formatter->get_output();
		}

		return $output;
	}

	public function get_json_output( $group ) {
		$output = [];
		return $output;
	}
}
