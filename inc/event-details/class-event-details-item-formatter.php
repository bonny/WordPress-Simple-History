<?php

namespace Simple_History\Event_Details;

/**
 * Format a single item in a group,
 * i.e. output current value and previous value, if any.
 */
abstract class Event_Details_Item_Formatter {
	/**
	 * @var Event_Details_Item $item
	 */
	protected ?Event_Details_Item $item;

	/**
	 * @param Event_Details_Item $item Item to format.
	 */
	public function __construct( $item = null ) {
		$this->item = $item;
	}

	/**
	 * @param Event_Details_Item $item Item to format.
	 * @return Event_Details_Item_Formatter $this
	 */
	public function set_item( $item ) {
		$this->item = $item;

		return $this;
	}

	/**
	 * @return Event_Details_Item
	 */
	public function get_item() {
		return $this->item;
	}

	/**
	 * @return string
	 */
	abstract public function to_html();

	/**
	 * @return array<mixed>
	 */
	abstract public function to_json();

	/**
	 * @return string
	 */
	protected function get_value_diff_output() {
		$value_output = '';

		if ( $this->item->is_changed ) {
			$value_output .= sprintf(
				'
				<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>
				<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>	
				',
				esc_html( $this->item->new_value ),
				esc_html( $this->item->prev_value )
			);
		} elseif ( $this->item->is_removed ) {
			$value_output .= sprintf(
				'<span class="SimpleHistoryLogitem__keyValueTable__removedThing">%1$s</span>',
				esc_html( $this->item->prev_value )
			);
		} else {
			$value_output .= sprintf(
				'<span class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</span>',
				esc_html( $this->item->new_value )
			);
		}

		return $value_output;
	}
}
