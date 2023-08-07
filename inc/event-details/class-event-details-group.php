<?php

namespace Simple_History\Event_Details;

class Event_Details_Group {
	/** @var array<Event_Details_Item> */
	public array $items = [];

	/** @var Event_Details_Group_Formatter */
	public Event_Details_Group_Formatter $formatter;

	/** @var string|null Group title. Used in for example JSON output. */
	public ?string $title = null;

	public function __construct() {
		$this->formatter = new Event_Details_Group_Table_Formatter();
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

	/**
	 * @param string $title
	 * @return Event_Details_Group $this
	 */
	public function set_title( $title = null ) {
		$this->title = $title;

		return $this;
	}

	/**
	 * Get title for group.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}
}
