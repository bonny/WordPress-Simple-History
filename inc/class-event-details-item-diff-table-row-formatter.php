<?php

namespace Simple_History;

class Event_Details_Item_Diff_Table_Row_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		$value_with_diff = helpers::Text_Diff(
			$this->item->prev_value,
			$this->item->new_value,
		);

		return sprintf(
			'
                <tr>
                    <td>%1$s</td>
                    <td>%2$s</td>
                </tr>
            ',
			esc_html( $this->item->name ),
			$value_with_diff,
		);
	}
}
