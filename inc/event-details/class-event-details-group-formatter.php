<?php

namespace Simple_History\Event_Details;

/**
 * Format a group of items.
 */
abstract class Event_Details_Group_Formatter {
	/**
	 * @param Event_Details_Group $group Group to format.
	 * @return string
	 */
	abstract public function to_html( $group );

	/**
	 * @param Event_Details_Group $group Group to format.
	 * @return array<mixed>
	 */
	abstract public function to_json( $group );
}
