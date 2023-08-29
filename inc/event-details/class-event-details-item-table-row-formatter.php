<?php

namespace Simple_History\Event_Details;

class Event_Details_Item_Table_Row_Formatter extends Event_Details_Item_Formatter {
	public function to_html() {
		// Skip output of items with empty values.
		if ( empty( $this->item->new_value ) || is_null( $this->item->new_value ) ) {
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
			$this->get_value_diff_output()
		);
	}

	public function to_json() {
		// Use same formatter as inline items.
		$item_formatter = new Event_Details_Item_Default_Formatter( $this->item );
		return $item_formatter->to_json();
	}
}
