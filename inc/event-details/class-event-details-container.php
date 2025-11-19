<?php

namespace Simple_History\Event_Details;

/**
 * Class that holds configuration for a group of context items.
 * Items in a group will be formatted in the same way.
 * To format another set of items in another way then add a new config group.
 */
class Event_Details_Container implements Event_Details_Container_Interface {
	/** @var array<Event_Details_Group> */
	public array $groups;

	/** @var array<string,mixed> */
	protected array $context;

	/**
	 * @param Event_Details_Group|array<Event_Details_Group> $group_or_groups Group or array of groups.
	 * @param array<string,mixed>                            $context       Context to use for setting prev and new values.
	 */
	public function __construct( $group_or_groups = [], $context = [] ) {
		$this->context = $context;
		$this->groups  = [];

		if ( is_array( $group_or_groups ) ) {
			$this->add_groups( $group_or_groups );
		} else {
			$this->add_group( $group_or_groups );
		}
	}

	/**
	 * Return the HTML output when accessing this object as a string.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->to_html();
	}

	/**
	 * Add context and use context to set prev and new values
	 * for each item in each group.
	 *
	 * @param array<string,mixed> $context Context to use for setting prev and new values.
	 * @return Event_Details_Container $this
	 */
	public function set_context( $context ) {
		$this->context = $context;

		$this->update_item_values_from_context();

		return $this;
	}

	/**
	 * @return Event_Details_Container $this
	 */
	private function update_item_values_from_context() {
		$context = $this->context;

		foreach ( $this->groups as $group ) {
			foreach ( $group->items as $item ) {
				// Get new value if value not already set.
				if ( isset( $item->slug_new ) && ! isset( $item->new_value ) ) {
					$item->new_value = $context[ $item->slug_new ] ?? null;
				}

				// Get prev value if value not already set.
				if ( isset( $item->slug_prev ) && ! isset( $item->prev_value ) ) {
					$item->prev_value = $context[ $item->slug_prev ] ?? null;
				}

				if ( is_null( $item->prev_value ) ) {
					$item->is_added = true;
				} elseif ( is_null( $item->new_value ) ) {
					$item->is_removed = true;
				} else {
					$item->is_changed = true;
				}
			}
		}

		$this->context = $context;

		$this->remove_empty_items();

		return $this;
	}

	/**
	 * Remove items with empty values.
	 * Empty = no new_value set.
	 * But if item has an old_value it's still interesting, because
	 * then a change has been made from "something" to "nothing".
	 *
	 * @return Event_Details_Container $this
	 */
	private function remove_empty_items() {

		foreach ( $this->groups as $group_key => $group ) {
			foreach ( $group->items as $item_key => $item ) {
				// Don't remove items that have custom formatters set,
				// as they may not rely on new_value/prev_value.
				if ( $item->has_custom_formatter() ) {
					continue;
				}

				if ( empty( $item->new_value ) && empty( $item->prev_value ) ) {
					unset( $this->groups[ $group_key ]->items[ $item_key ] );
				}
			}
		}

		return $this;
	}

	/**
	 * Shortcut to add a single item,
	 * the item will be added to a group first.
	 *
	 * @param Event_Details_Item $context_item Item to add.
	 * @param string|null        $group_title Optional name of the auto created group.
	 * @return Event_Details_Container $this
	 */
	public function add_item( $context_item, $group_title = null ) {
		$context_item_group = new Event_Details_Group();
		$context_item_group->add_items( [ $context_item ] );
		$context_item_group->set_formatter( new Event_Details_Group_Single_Item_Formatter() );
		$context_item_group->set_title( $group_title );
		$this->add_group( $context_item_group );

		return $this;
	}

	/**
	 * @param Event_Details_Group $group Group to add.
	 * @return Event_Details_Container $this
	 */
	public function add_group( $group ) {
		$this->groups[] = $group;

		// Update from context again, since we have a new group.
		$this->update_item_values_from_context();

		return $this;
	}

	/**
	 * Add groups.
	 *
	 * @param array<Event_Details_Group> $groups Groups to add.
	 * @return Event_Details_Container $this
	 */
	public function add_groups( $groups ) {
		foreach ( $groups as $group ) {
			$this->add_group( $group );
		}

		return $this;
	}

	/**
	 * Add many items. They will automatically
	 * be added to a group first to share common styles.
	 *
	 * @param array<Event_Details_Item> $items   Items to add.
	 * @param string|null               $group_title Optional name of the auto created group.
	 * @return Event_Details_Container $this
	 */
	public function add_items( $items, $group_title = null ) {
		$event_details_group = new Event_Details_Group();
		$event_details_group->set_title( $group_title );
		$event_details_group->add_items( $items );
		$this->add_group( $event_details_group );

		return $this;
	}

	/**
	 * @return string
	 */
	public function to_html() {
		$output = '';

		foreach ( $this->groups as $group ) {
			$output .= $group->formatter->to_html( $group );
		}

		return $output;
	}

	/**
	 * @return array<mixed>
	 */
	public function to_json() {
		$output = [];

		foreach ( $this->groups as $group ) {
			$output[] = $group->formatter->to_json( $group );
		}

		return $output;
	}
}
