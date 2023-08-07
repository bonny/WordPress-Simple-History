<?php

namespace Simple_History\Event_Details;

// Format a group of items.
abstract class Event_Details_Group_Formatter {
	/**
	 * @param Event_Details_Group $group
	 * @return string
	 */
	abstract public function get_html_output( $group );

	/**
	 * @param Event_Details_Group $group
	 * @return array<mixed>
	 */
	abstract public function get_json_output( $group );
}
