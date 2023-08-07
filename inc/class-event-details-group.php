<?php

namespace Simple_History;

class Event_Details_Group {
	/** @var array<Event_Details_Item> */
	public array $items = [];

	/** @var Event_Details_Group_Formatter */
	public Event_Details_Group_Formatter $formatter;

	/**
	 * @return Event_Details_Group $this
	 */
	public function __construct() {
		$this->formatter = new Event_Details_Group_Table_Formatter();

		return $this;
	}

	/**
	 * @param array<Event_Details_Item> $items
	 * @return Event_Details_Group $this
	 */
	public function add_items( $items ) {
		$this->items = array_merge( $this->items, $items );

		return $this;
	}

	/**
	 * @param Event_Details_Group_Formatter $formatter
	 * @return Event_Details_Group $this
	 */
	public function set_formatter( $formatter ) {
		$this->formatter = $formatter;

		return $this;
	}
}
