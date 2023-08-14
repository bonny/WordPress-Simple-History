<?php

namespace Simple_History\Event_Details;

/**
 * Class that holds configuration for a group of context items.
 * Items in a group will be formatted in the same way.
 * To format another set of items in another way then add a new config group.
 */
class Event_Details_Container {
	/** @var array<Event_Details_Group> */
	public array $groups;

	/** @var array<string,mixed> */
	protected array $context;

	/**
	 * @param Event_Details_Group|array<Event_Details_Group> $group_or_groups Group or array of groups.
	 * @param array<string,mixed> $context
	 */
	public function __construct( $group_or_groups = [], $context = [] ) {
		$this->context = $context;
		$this->groups = [];

		if ( is_array( $group_or_groups ) ) {
			$this->add_groups( $group_or_groups );
		} else {
			$this->add_group( $group_or_groups );
		}
	}

	/**
	 * Add context and use context to set prev and new values
	 * for each item in each group.
	 *
	 * @param array<string,mixed> $context
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
				} else if ( is_null( $item->new_value ) ) {
					$item->is_removed = true;
				} else {
					$item->is_changed = true;
				}
			}
		}

		$this->context = $context;

		return $this;
	}

	/**
	 * Shortcut to add a single item,
	 * the item will be added to a group first.
	 *
	 * @param Event_Details_Item $context_item
	 * @param string|null $group_title Optional name of the auto created group.
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
	 * @param Event_Details_Group $group
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
	 * @param array<Event_Details_Group> $groups
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
	 * @param array<Event_Details_Item> $items
	 * @param string|null $group_title Optional name of the auto created group.
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
	public function get_html_output() {
		$output = '';

		foreach ( $this->groups as $group ) {
			$output .= $group->formatter->get_html_output( $group );
		}

		return $output;
	}

	/**
	 * @return array<mixed>
	 */
	public function get_json_output() {
		$output = [];

		foreach ( $this->groups as $group ) {
			$output[] = $group->formatter->get_json_output( $group );
		}

		return $output;
	}
}

/**
 * Append prev and new values and modification status to each item
 * in the context_output_config array.
 *
 * @param array $context Context array.
 * @param Event_Details_Container $context_output_config DTO object with config for each setting.
 * @return Event_Details_Container Modified $context_output_config.
 */
/* function append_modified_values_status_to_context_output_config_array( $context, $context_output_config ) {
	// Find prev and new values for each setting,
	// e.g. the slug + "_new" or "_prev".
	foreach ( $context_output_config->groups as $key => $setting ) {
		$slug = $setting->slug;

		$prev_value = $context[ "{$slug}_prev" ] ?? null;
		$new_value = $context[ "{$slug}_new" ] ?? null;

		$context_output_config->groups[ $key ]->is_changed = false;
		$context_output_config->groups[ $key ]->is_added = false;
		$context_output_config->groups[ $key ]->is_removed = false;

		// If both prev and new are null then no change was made.
		if ( is_null( $prev_value ) && is_null( $new_value ) ) {
			continue;
		}

		// If both prev and new are the same then no change was made.
		if ( $prev_value === $new_value ) {
			continue;
		}

		if ( is_null( $prev_value ) ) {
			// If prev is null then it was added.
			$prev_value = '<em>' . __( 'Not set', 'simple-history' ) . '</em>';
			$context_output_config->groups[ $key ]->is_added = true;
		} else if ( is_null( $new_value ) ) {
			// If new is null then it was removed.
			$new_value = '<em>' . __( 'Not set', 'simple-history' ) . '</em>';
			$context_output_config->groups[ $key ]->is_removed = true;
		} else {
			$context_output_config->groups[ $key ]->is_changed = true;
		}

		$context_output_config->groups[ $key ]->prev_value = $prev_value;
		$context_output_config->groups[ $key ]->new_value = $new_value;
	}

	return $context_output_config;
} */

/**
 * Generate a table with items that are modified, added, or removed.
 *
 * @param array $context Context array.
 * @param Event_Details_Container $context_config Array with config for each setting.
 * @return string HTML table.
 */
/* function generate_added_removed_table_from_context_output_config_array( $context, $context_config ) {
	$context_config = append_modified_values_status_to_context_output_config_array( $context, $context_config );

	$table = '<table class="SimpleHistoryLogitem__keyValueTable"><tbody>';

	foreach ( $context_config->groups as $setting ) {
		if ( $setting->is_changed ) {
			$new_value_to_show = $setting->new_value;
			$prev_value_to_show = $setting->prev_value;

			if ( $setting->number_yes_no ) {
				$new_value_to_show = $setting->new_value === '1' ? 'Yes' : 'No';
				$prev_value_to_show = $setting->prev_value === '1' ? 'Yes' : 'No';
			}

			$table .= sprintf(
				'
					<tr>
						<td>%1$s</td>
						<td>
						</td>
					</tr>
					',
				esc_html( $setting->name ),
				esc_html( $new_value_to_show ),
				esc_html( $prev_value_to_show ),
			);
		}
	}

	$table .= '</tbody></table>';

	return $table;
} */
