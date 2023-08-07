<?php

namespace Simple_History\Event_Details;

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

	/** @var ?Event_Details_Item_Formatter */
	public ?Event_Details_Item_Formatter $formatter = null;

	/**
	 * @param string|array<string> $slug_or_slugs
	 * @param string $name
	 */
	public function __construct( $slug_or_slugs = null, $name = null ) {
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
	}

	/**
	 * Manually set the current/new value of the item.
	 * If used then value will not be fetched from context.
	 *
	 * @param string $new_value
	 * @return Event_Details_Item $this
	 */
	public function set_new_value( $new_value ) {
		$this->new_value = $new_value;

		return $this;
	}

	/**
	 * Manually set the previous value of the item.
	 * If used then value will not be fetched from context.
	 *
	 * @param string $prev_value
	 * @return Event_Details_Item $this
	 */
	public function set_prev_value( $prev_value ) {
		$this->prev_value = $prev_value;

		return $this;
	}

	/**
	 * Manually set both new/current value and
	 * previous value of the item.
	 *
	 * @param string $new_value
	 * @param string $prev_value
	 * @return Event_Details_Item $this
	 */
	public function set_values( $new_value, $prev_value ) {
		$this->set_new_value( $new_value );
		$this->set_prev_value( $prev_value );

		return $this;
	}

	/**
	 * @param Event_Details_Item_Formatter $formatter
	* @return Event_Details_Item $this
	 */
	public function set_formatter( $formatter ) {
		$this->formatter = $formatter;

		return $this;
	}

	/**
	 * @param ?Event_Details_Item_Formatter $fallback_formatter Formatter to use if item does not have any formatter specified.
	 * @return Event_Details_Item_Formatter|null
	 */
	public function get_formatter( $fallback_formatter = null ) {
		if ( $this->formatter instanceof Event_Details_Item_Formatter ) {
			return $this->formatter;
		}

		return $fallback_formatter;
	}

	/**
	 * @return bool
	 */
	public function has_formatter() {
		return $this->formatter instanceof Event_Details_Item_Formatter;
	}
}
