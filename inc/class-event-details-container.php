<?php

namespace Simple_History;

/**
 * Class that holds configuration for a group of context items.
 * Items in a group will be formatted in the same way.
 * To format another set of items in another way then add a new config group.
 */
class Event_Details_Container {
	/** @var array<Event_Details_Group> */
	public array $groups;

	/** @var array<string,mixed> */
	public array $context;

	/**
	 * @param array<Event_Details_Group> $context_items
	 */
	public function __construct( $context_items = [] ) {
		$this->groups = $context_items;
	}

	/**
	 * Add context and use context to set prev and new values
	 * for each item in each group.
	 *
	 * @param array<string,mixed> $context
	 * @return void
	 */
	public function set_context( $context ) {
		foreach ( $this->groups as $group ) {
			foreach ( $group->items as $item ) {
				if ( isset( $item->slug_new ) ) {
					$item->new_value = $context[ $item->slug_new ] ?? null;
				}

				if ( isset( $item->slug_prev ) ) {
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
	}

	/**
	 * @param Event_Details_Item $context_item
	 * @return void
	 */
	public function add_item( $context_item ) {
		// Create group with single item.
		$context_item_group = new Event_Details_Group();
		$context_item_group->add_items( [ $context_item ] );
		$context_item_group->set_formatter( new Event_Details_Group_Single_Item_Formatter() );
		$this->add_group( $context_item_group );
	}

	/**
	 * @param Event_Details_Group $group
	 * @return void
	 */
	public function add_group( $group ) {
		$this->groups[] = $group;
	}

	/**
	 * Add many items. They will automatically
	 * be added to a group first to share common styles.
	 *
	 * @param array<Event_Details_Item> $items
	 * @return void
	 */
	public function add_items( $items ) {
		$event_details_group = new Event_Details_Group();
		$event_details_group->add_items( $items );
		$this->add_group( $event_details_group );
	}

	/**
	 * @param array<string,mixed> $context
	 * @param string $format
	 * @return string
	 */
	public function get_output( $format = 'html' ) {
		if ( 'html' === $format ) {
			return $this->get_html_output();
		}
		//  else if ( 'json' === $format ) {
			// return $this->get_json_output_for_context( $context );
		// }
	}

	private function get_html_output() {
		$output = '';

		foreach ( $this->groups as $group ) {
			$output .= $group->formatter->get_output( $group, $this->context );
		}

		return $output;
	}

	// private function get_json_output_for_context( $context ) {
	// 	$output = [
	// 		'format' => 'json',
	// 	];

	// 	return $output;
	// }
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
