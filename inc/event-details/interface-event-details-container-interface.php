<?php

namespace Simple_History\Event_Details;

interface Event_Details_Container_Interface {
	/**
	 * @return string
	 */
	public function to_html();

	/**
	 * @return array<mixed>
	 */
	public function to_json();

	/**
	 * @return string
	 */
	public function __toString();
}
