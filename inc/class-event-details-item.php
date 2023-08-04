<?php

namespace Simple_History;

/**
 * DTO class for a single context diff config item.
 */
class Event_Details_Item {
	/** @var string Human readable name of setting */
	public ?string $name;

	/** @var string Key slug of current, new, or updated value. */
	public ?string $slug_new;

	/** @var string Key slug of previous value. */
	public ?string $slug_prev;

	/** @var string New value */
	public ?string $new_value = null;

	/** @var string Previous value */
	public ?string $prev_value = null;

	/** @var bool If value of item is changed */
	public ?bool $is_changed = null;

	/** @var bool If value of item is added */
	public ?bool $is_added = null;

	/** @var bool If value of item is removed */
	public ?bool $is_removed = null;

	/** @var bool If value of item should be formatted as "Yes" and "No" instead of 0 and 1. */
	// public bool $number_yes_no = false;

	/** @var array How item should be formatted */
	// public array $format = [
	// 	'number_yes_no' => false,
	// ];

	/**
	 * @param array|string $slug_or_slugs
	 * @param string $name
	 * @param array<string,mixed> $additional_args
	 */
	public function __construct( $slug_or_slugs, $name = null, $additional_args = [] ) {
		// Set keys to use for new/current and old/prev values
		if ( is_array( $slug_or_slugs ) && count( $slug_or_slugs ) === 2 ) {
			// Single slug as string = just exactly that context key.
			// Array as slugs = 0 key = new/updated value, 1 = old/prev value.
			$this->slug_new = $slug_or_slugs[0];
			$this->slug_prev = $slug_or_slugs[1];
		} else if ( is_array( $slug_or_slugs ) && count( $slug_or_slugs ) === 1 ) {
			// Single item in array = use new format with "_new" and "_prev".
			$this->slug_new = $slug_or_slugs[0] . '_new';
			$this->slug_prev = $slug_or_slugs[0] . '_prev';
		} else if ( is_string( $slug_or_slugs ) ) {
			// Not array, use exactly that single key slug.
			$this->slug_new = $slug_or_slugs;
		}

		$this->name = $name;

		// if ( isset( $additional_args['number_yes_no'] ) ) {
		// 	$this->number_yes_no = $additional_args['number_yes_no'];
		// }
	}
}
