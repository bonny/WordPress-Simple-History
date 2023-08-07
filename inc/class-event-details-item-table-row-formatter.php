<?php

namespace Simple_History;

class Event_Details_Item_Table_Row_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		// Skip output of items with empty values.
		if ( is_null( $this->item->new_value ) ) {
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
}
