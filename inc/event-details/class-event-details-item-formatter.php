<?php

namespace Simple_History\Event_Details;

// Format a single item in a group,
// i.e. output current value and previous value, if any.
abstract class Event_Details_Item_Formatter {
	/**
	 * @var Event_Details_Item $item
	 */
	protected ?Event_Details_Item $item;

	/**
	 * @param Event_Details_Item $item
	 */
	public function __construct( $item = null ) {
		$this->item = $item;

	}

	/**
	 * @param Event_Details_Item $item
	 * @return Event_Details_Item_Formatter $this
	 */
	public function set_item( $item ) {
		$this->item = $item;

		return $this;
	}

	/**
	 * @return string
	 */
	abstract public function get_html_output();

	/**
	 * @return array<mixed>
	 */
	abstract public function get_json_output();

	/**
	 * @return string
	 */
	protected function get_value_diff_output() {
		$value_output = '';

		if ( $this->item->is_changed ) {
			$value_output = sprintf(
				'
				<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>
				<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>	
				',
				esc_html( $this->item->new_value ),
				esc_html( $this->item->prev_value )
			);
		} else {
			$value_output = sprintf(
				'<span class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</span>',
				esc_html( $this->item->new_value )
			);
		}

		return $value_output;
	}
}
