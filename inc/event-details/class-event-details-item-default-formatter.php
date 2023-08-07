<?php

namespace Simple_History\Event_Details;

class Event_Details_Item_Default_Formatter extends Event_Details_Item_Formatter {
	public function get_html_output() {
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

	public function get_json_output() {
		$return = [];

		if ( isset( $this->item->name ) ) {
			$return['name'] = $this->item->name;
		}

		if ( isset( $this->item->new_value ) ) {
			$return['new_value'] = $this->item->new_value;
		}

		if ( isset( $this->item->prev_value ) ) {
			$return['prev_value'] = $this->item->prev_value;
		}

		if ( isset( $this->item->slug_new ) ) {
			$return['slug_new'] = $this->item->slug_new;
		}

		if ( isset( $this->item->slug_prev ) ) {
			$return['slug_prev'] = $this->item->slug_prev;
		}

		return $return;
	}
}
