<?php

namespace Simple_History\Event_Details;

/**
 * Formatter for a group of items.
 */
class Event_Details_Item_Table_Row_Formatter extends Event_Details_Item_Formatter {
	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function to_html() {
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

	/**
	 * @inheritdoc
	 *
	 * @return array<mixed>
	 */
	public function to_json() {
		// Use same formatter as inline items.
		$item_formatter = new Event_Details_Item_Default_Formatter( $this->item );
		return $item_formatter->to_json();
	}
}
