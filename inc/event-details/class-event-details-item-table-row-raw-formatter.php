<?php

namespace Simple_History\Event_Details;

/**
 * Raw item in a table, i.e. the key value is outputed as usual
 * but the value is raw.
 */
class Event_Details_Item_Table_Row_RAW_Formatter extends Event_Details_Item_RAW_Formatter {
	// public function get_html_output() {
	// 	return $this->html_output;
	// }
	public function get_html_output() {
		// Skip output of items with empty raw HTML.
		if ( is_null( $this->html_output ) ) {
			return '';
		}

		return sprintf(
			'
				<tr>
					<td>%1$s</td>
					<td>%2$s</td>
				</tr>
			',
			esc_html( $this->item->name ),
			$this->html_output
		);
	}
}
