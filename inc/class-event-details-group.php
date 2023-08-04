<?php

namespace Simple_History;

class Event_Details_Group {
	/** @var array<Event_Details_Item> */
	public array $items = [];

	/** @var Event_Details_Group_Formatter */
	public Event_Details_Group_Formatter $formatter;

	public function __construct() {
		$this->formatter = new Event_Details_Group_Table_Formatter();
	}

	/**
	 * @param array<Event_Details_Item> $items
	 */
	public function add_items( $items ) {
		$this->items = array_merge( $this->items, $items );
	}

	/**
	 * @param Event_Details_Group_Formatter $formatter
	 * @return void
	 */
	public function set_formatter( $formatter ) {
		$this->formatter = $formatter;
	}
}
