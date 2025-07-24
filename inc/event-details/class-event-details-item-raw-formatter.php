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
		$output = $this->json_output;

		// Include item name if it exists and isn't already in custom output,
		// but only if the custom output seems to be structured data that could benefit from a name field.
		// If it's a simple array with basic keys, don't add the name to avoid breaking existing usage.
		if ( $this->item && $this->item->name && ! isset( $output['name'] ) ) {
			// Only add name if the output has some structured content (contains 'type' or 'content' keys)
			// This ensures we don't interfere with purely custom JSON outputs.
			if ( isset( $output['type'] ) || isset( $output['content'] ) ) {
				$output['name'] = $this->item->name;
			}
		}

		return $output;
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
