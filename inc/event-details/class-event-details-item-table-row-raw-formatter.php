<?php

namespace Simple_History\Event_Details;

/**
 * Raw item in a table, i.e. the key value is outputted as usual
 * but the value is raw.
 */
class Event_Details_Item_Table_Row_RAW_Formatter extends Event_Details_Item_RAW_Formatter {
	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function to_html() {
		// Skip output of items with empty raw HTML.
		if ( empty( $this->html_output ) ) {
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
