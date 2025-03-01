<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Simple_Container;

/**
 * Logger for manually added events.
 */
class Manual_Events_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'ManualEventsLogger';

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name' => \__( 'Manual Events Logger', 'simple-history' ),
			'description' => \__( 'Logs manually added events', 'simple-history' ),
			'capability' => 'edit_posts',
			'messages' => [
				'added_manual_event' => \__( 'Added manual message: {message}', 'simple-history' ),
			],
			'labels' => [
				'search' => [
					'label' => \_x( 'Manual Events', 'Manual events logger: search', 'simple-history' ),
					'label_all' => \_x( 'All manual events', 'Manual events logger: search', 'simple-history' ),
					'options' => [
						\_x( 'Manual events added', 'Manual events logger: search', 'simple-history' ) => ['manual_event_added'],
					],
				],
			],
		];
	}

	/**
	 * Get the log row details output.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Container_Interface
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'] ?? null;

		// Bail if no message key.
		if ( empty( $message_key ) ) {
			return null;
		}

		// Add note if it exists.
		if ( ! empty( $context['note'] ) ) {
			return new Event_Details_Simple_Container( $context['note'] );
		}

		return null;
	}
} 