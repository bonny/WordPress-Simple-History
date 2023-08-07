<?php

namespace Simple_History;

class Event_Details_Item_Default_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		$name = '';
		if ( ! empty( $this->item->name ) ) {
			$name = sprintf( '<em>%1$s:</em> ', esc_html( $this->item->name ) );
		}

		return sprintf(
			'<span class="SimpleHistoryLogitem__inlineDivided">%1$s%2$s</span> ',
			$name,
			$this->get_value_diff_output(),
		);
	}
}
