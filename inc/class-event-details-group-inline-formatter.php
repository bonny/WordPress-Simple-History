<?php

namespace Simple_History;

// Format a group of items.
abstract class Event_Details_Group_Formatter {
	/**
	 * @param Event_Details_Group $group
	 * @return string|array<mixed>
	 */
	abstract public function get_output( $group );
}

/**
 * Format a group of items as an inline list.
 */
class Event_Details_Group_Inline_Formatter extends Event_Details_Group_Formatter {
	public function get_output( $group ) {
		$output = '<p>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Default_Formatter( $item ) );
			$output .= $item_formatter->get_output();
		}

		$output .= '</p>';

		return $output;
	}
}

class Event_Details_Group_Table_Formatter extends Event_Details_Group_Formatter {
	public function get_output( $group ) {
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';
		$output .= '<tbody>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Table_Row_Formatter( $item ) );
			$output .= $item_formatter->get_output();
		}

		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}
}

class Event_Details_Group_Diff_Table_Formatter extends Event_Details_Group_Formatter {
	public function get_output( $group ) {
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';
		$output .= '<tbody>';

		foreach ( $group->items as $item ) {
			$item_formatter = $item->get_formatter( new Event_Details_Item_Diff_Table_Row_Formatter( $item ) );
			$output .= $item_formatter->get_output();
		}

		$output .= '</tbody>';
		$output .= '</table>';

		return $output;
	}
}

/**
 * A group with a single item, just plain output, no table or inline or similar.
 * They are added to the details group without a group first (group is generated in add function).
 * TODO: How to handle values? Placeholders?, {} or %s-format?
 */
class Event_Details_Group_Single_Item_Formatter extends Event_Details_Group_Formatter {
	public function get_output( $group ) {
		$output = '';

		foreach ( $group->items as $item ) {
			if ( $item->has_formatter() ) {
				$formatter = $item->get_formatter();
			} else {
				$formatter = new Event_Details_Item_Default_Formatter( $item );
			}

			$output .= $formatter->get_output();
		}

		return $output;
	}
}

// Format a single item in a group,
// i.e. output current value and previous value, if any.
abstract class Event_Details_Item_Formatter {
	/**
	 * @var Event_Details_Item $item
	 */
	protected ?Event_Details_Item $item;

	/**
	 * @param Event_Details_Item $item
	 * @return Event_Details_Item_Formatter $this
	 */
	public function __construct( $item = null ) {
		$this->item = $item;

		return $this;
	}

	/**
	 * @param Event_Details_Item $item
	 * @return Event_Details_Item_Formatter $this
	 */
	public function set_item( $item ) {
		$this->item = $item;

		return $this;
	}

	/**
	 * @return string
	 */
	abstract public function get_output();

	/**
	 * @return string
	 */
	protected function get_value_diff_output() {
		$value_output = '';

		if ( $this->item->is_changed ) {
			$value_output = sprintf(
				'
				<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>
				<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>	
				',
				esc_html( $this->item->new_value ),
				esc_html( $this->item->prev_value )
			);
		} else {
			$value_output = sprintf(
				'<span class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</span>',
				esc_html( $this->item->new_value )
			);
		}

		return $value_output;
	}
}

class Event_Details_Item_Default_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		$name = '';
		if ( ! empty( $this->item->name ) ) {
			$name = sprintf( '<em>%1$s:</em> ', esc_html( $this->item->name ) );
		}

		return sprintf(
			'<span class="SimpleHistoryLogitem__inlineDivided">%1$s%2$s</span> ',
			$name,
			$this->get_value_diff_output(),
		);
	}
}

class Event_Details_Item_Table_Row_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		// Skip output of items with empty values.
		if ( is_null( $this->item->new_value ) ) {
			return '';
		}

		return sprintf(
			'
                <tr>
                    <td>%1$s</td>
                    <td>%2$s</td>
                </tr>
            ',
			esc_html( $this->item->name ),
			$this->get_value_diff_output()
		);
	}
}

class Event_Details_Item_Diff_Table_Row_Formatter extends Event_Details_Item_Formatter {
	public function get_output() {
		// Skip output of items with empty values.
		// if ( is_null( $this->item->new_value ) ) {
		// 	return '';
		// }

		$value_with_diff = helpers::Text_Diff(
			$this->item->prev_value,
			$this->item->new_value,
		);

		return sprintf(
			'
                <tr>
                    <td>%1$s</td>
                    <td>%2$s</td>
                </tr>
            ',
			esc_html( $this->item->name ),
			$value_with_diff,
		);
	}
}

class Event_Details_Item_RAW_Formatter extends Event_Details_Item_Formatter {

	/** @var string */
	protected $html_output = '';

	/** @var array<mixed> */
	protected $json_output = [];

	public function get_output() {
		return $this->html_output;
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
