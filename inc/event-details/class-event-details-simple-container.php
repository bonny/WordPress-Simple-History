<?php

namespace Simple_History\Event_Details;

/**
 * A simple container for event details,
 * to be used to wrap old event details functions.
 */
class Event_Details_Simple_Container implements Event_Details_Container_Interface {
	/** @var string|Event_Details_Container_Interface */
	private $html;

	/**
	 * @param string|Event_Details_Container_Interface|null $html HTML or Event_Details_Container_Interface.
	 */
	public function __construct( $html = '' ) {
		$this->html = $html;
	}

	/**
	 * @inheritdoc
	 */
	public function to_html() {
		if ( $this->html instanceof Event_Details_Container_Interface ) {
			return $this->html->to_html();
		}

		return $this->html;
	}

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function __toString() {
		return (string) $this->to_html();
	}

	/**
	 * Old event details does not have support for JSON output,
	 * so we return an empty array.
	 *
	 * @return array<mixed> Empty array.
	 */
	public function to_json() {
		return [];
	}
}
