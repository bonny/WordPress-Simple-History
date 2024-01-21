<?php

namespace Simple_History\Event_Details;

/**
 * Formatter for a group of items.
 * Outputs items without any name, so just the value.
 * Useful when manually setting all output.
 */
class Event_Details_Item_RAW_Formatter extends Event_Details_Item_Formatter {
	/** @var string */
	protected $html_output = '';

	/** @var array<mixed> */
	protected $json_output = [];

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public function to_html() {
		return $this->html_output;
	}

	/**
	 * @inheritdoc
	 *
	 * @return array<mixed>
	 */
	public function to_json() {
		return $this->json_output;
	}

	/**
	 * @param string $html HTML output.
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_html_output( $html ) {
		$this->html_output = $html;

		return $this;
	}

	/**
	 * @param array<mixed> $json JSON output.
	 * @return Event_Details_Item_RAW_Formatter $this
	 */
	public function set_json_output( $json ) {
		$this->json_output = $json;

		return $this;
	}
}
