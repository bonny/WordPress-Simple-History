<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logger for custom entries added manually through WP-CLI or REST API.
 *
 * Slug: custom_entry_logger
 */
class Custom_Entry_Logger extends Logger {
	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = 'CustomEntryLogger';

	/**
	 * Get array with information about this logger.
	 *
	 * @return array Array with logger info.
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => _x( 'Custom Entry Logger', 'Logger: Custom Entry', 'simple-history' ),
			'description' => _x( 'Logs custom entries added through WP-CLI or REST API', 'Logger: Custom Entry', 'simple-history' ),
			'capability'  => 'edit_pages',
			'messages'    => array(
				'custom_entry_added' => _x( 'Added a custom entry: {message}', 'Logger: Custom Entry', 'simple-history' ),
			),
			'labels'      => [
				'search' => [
					'label'   => _x( 'Custom entries', 'Custom entry logger: search', 'simple-history' ),
					'options' => [
						_x( 'Custom entry added', 'Custom entry logger: search', 'simple-history' ) => [
							'custom_entry_added',
						],
					],
				],
			],
		);

		return $arr_info;
	}

	/**
	 * Get the log row details output,
	 * display the message note in the details area, if it exists.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Container_Interface|null
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;

		// Bail if no message key.
		if ( empty( $message_key ) ) {
			return null;
		}

		if ( empty( $context['note'] ) ) {
			return null;
		}

		$event_details_group = new Event_Details_Group();
		$event_details_group->add_item(
			new Event_Details_Item(
				'note',
				__( 'Entry notes', 'simple-history' ),
			)
		);

		return $event_details_group;
	}
}
