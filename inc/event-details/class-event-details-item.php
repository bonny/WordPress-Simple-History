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
	protected $formatter = null;

	/**
	 * @param string|array<string> $slug_or_slugs Key slug of current, new, or updated value.
	 * @param string               $name        Human readable name of setting.
	 */
	public function __construct( $slug_or_slugs = null, $name = null ) {
		// Set keys to use for new/current and old/prev values.
		if ( is_array( $slug_or_slugs ) && count( $slug_or_slugs ) === 2 ) {
			// Single slug as string = just exactly that context key.
			// Array as slugs = 0 key = new/updated value, 1 = old/prev value.
			$this->slug_new  = $slug_or_slugs[0];
			$this->slug_prev = $slug_or_slugs[1];
		} elseif ( is_array( $slug_or_slugs ) && count( $slug_or_slugs ) === 1 ) {
			// Single item in array = use new format with "_new" and "_prev".
			$this->slug_new  = $slug_or_slugs[0] . '_new';
			$this->slug_prev = $slug_or_slugs[0] . '_prev';
		} elseif ( is_string( $slug_or_slugs ) ) {
			// Not array, use exactly that single key slug.
			$this->slug_new = $slug_or_slugs;
		}

		$this->name = $name;
	}

	/**
	 * Manually set the current/new value of the item.
	 * If used then value will not be fetched from context.
	 *
	 * @param string $new_value New value.
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
	 * @param string $prev_value Previous value.
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
	 * @param string $new_value New value.
	 * @param string $prev_value Previous value.
	 * @return Event_Details_Item $this
	 */
	public function set_values( $new_value, $prev_value ) {
		$this->set_new_value( $new_value );
		$this->set_prev_value( $prev_value );

		return $this;
	}

	/**
	 * Sets a formatter to use for item.
	 * Accepts an instance of a formatter, useful for example when passing in a custom raw formatter, where
	 * HTML and JSON output is set manually.
	 *
	 * @param class-string<Event_Details_Item_Formatter>|Event_Details_Item_Formatter $formatter_or_formatter_class Formatter class name to use if item does not have any formatter specified.
	 * @return Event_Details_Item $this
	 */
	public function set_formatter( $formatter_or_formatter_class ) {
		if ( $formatter_or_formatter_class instanceof Event_Details_Item_Formatter ) {
			$this->formatter = $formatter_or_formatter_class;
			$this->formatter->set_item( $this );
		} elseif ( is_subclass_of( $formatter_or_formatter_class, Event_Details_Item_Formatter::class ) ) {
			$this->formatter = new $formatter_or_formatter_class( $this );
		}

		return $this;
	}

	/**
	 * @param class-string<Event_Details_Item_Formatter>|Event_Details_Item_Formatter|null $fallback_formatter_or_formatter_class Formatter class name to use if item does not have any formatter specified.
	 * @return Event_Details_Item_Formatter
	 */
	public function get_formatter( $fallback_formatter_or_formatter_class = null ) {
		$formatter = null;

		// Use fallback formatter if item formatter is not already set.
		if ( $this->formatter instanceof Event_Details_Item_Formatter ) {
			$formatter = $this->formatter;
		} elseif ( $fallback_formatter_or_formatter_class instanceof Event_Details_Item_Formatter ) {
			$formatter = $fallback_formatter_or_formatter_class;
		} elseif ( is_subclass_of( $fallback_formatter_or_formatter_class, Event_Details_Item_Formatter::class ) ) {
			$formatter = new $fallback_formatter_or_formatter_class();
		} else {
			// Use default formatter as final fallback.
			$formatter = new Event_Details_Item_Default_Formatter();
		}

		$formatter->set_item( $this );

		return $formatter;
	}

	/**
	 * Check if a formatter is available for this item.
	 * Always returns true because get_formatter() guarantees a formatter.
	 *
	 * @return bool
	 */
	public function has_formatter() {
		return true;
	}

	/**
	 * Check if this item has a custom formatter explicitly set.
	 *
	 * @return bool
	 */
	public function has_custom_formatter() {
		return $this->formatter instanceof Event_Details_Item_Formatter;
	}
}
