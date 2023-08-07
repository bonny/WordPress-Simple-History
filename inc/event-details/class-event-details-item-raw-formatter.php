<?php

namespace Simple_History\Event_Details;

class Event_Details_Item_RAW_Formatter extends Event_Details_Item_Formatter {
	/** @var string */
	protected $html_output = '';

	/** @var array<mixed> */
	protected $json_output = [];

	public function get_html_output() {
		return $this->html_output;
	}

	public function get_json_output() {
		return $this->json_output;
	}

	/**
	 * @param string $html
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_html_output( $html ) {
		$this->html_output = $html;

		return $this;
	}

	/**
	 * @param array<mixed> $json
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_json_output( $json ) {
		$this->json_output = $json;

		return $this;
	}
}
