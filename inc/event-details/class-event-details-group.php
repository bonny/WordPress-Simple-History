<?php

namespace Simple_History\Event_Details;

/**
 * Formatter for a group of items.
 */
class Event_Details_Group {
	/** @var array<Event_Details_Item> */
	public array $items = [];

	/** @var Event_Details_Group_Formatter */
	public Event_Details_Group_Formatter $formatter;

	/** @var string|null Group title. Used in for example JSON output. */
	public ?string $title = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->formatter = new Event_Details_Group_Table_Formatter();
	}

	/**
	 * @param array<Event_Details_Item> $items Items to add.
	 * @return Event_Details_Group $this Fluent return.
	 */
	public function add_items( $items ) {
		$this->items = array_merge( $this->items, $items );

		return $this;
	}

	/**
	 * @param Event_Details_Item $item Item to add.
	 * @return Event_Details_Group $this
	 */
	public function add_item( $item ) {
		$this->items[] = $item;

		return $this;
	}

	/**
	 * @param Event_Details_Group_Formatter $formatter Formatter to use.
	 * @return Event_Details_Group $this
	 */
	public function set_formatter( $formatter ) {
		$this->formatter = $formatter;

		return $this;
	}

	/**
	 * @param string $title Title for group.
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

	/**
	 * Create a group that outputs raw HTML (and optional JSON).
	 *
	 * Shortcut for the common pattern of creating a single-item group
	 * with a RAW formatter for content like thumbnails, media players,
	 * or pre-formatted HTML blocks.
	 *
	 * @param string       $html Raw HTML output.
	 * @param array<mixed> $json Optional JSON output for REST API.
	 * @return Event_Details_Group
	 */
	public static function create_raw( $html, $json = [] ) {
		$formatter = ( new Event_Details_Item_RAW_Formatter() )
			->set_html_output( $html );

		if ( ! empty( $json ) ) {
			$formatter->set_json_output( $json );
		}

		$group = new self();
		$group->set_formatter( new Event_Details_Group_Single_Item_Formatter() );
		$group->add_item(
			( new Event_Details_Item() )->set_formatter( $formatter )
		);

		return $group;
	}
}
