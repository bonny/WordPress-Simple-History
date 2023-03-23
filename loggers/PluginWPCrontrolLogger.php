<?php

defined( 'ABSPATH' ) || die();

/**
 * Logs cron event management from the WP Crontrol plugin
 * Plugin URL: https://wordpress.org/plugins/wp-crontrol/
 *
 * Requires WP Crontrol 1.9.0 or later.
 *
 * @since x.x
 */
class PluginWPCrontrolLogger extends SimpleLogger {


	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function getInfo() {

		$arr_info = array(
			'name'        => _x( 'Plugin: WP Crontrol Logger', 'PluginWPCrontrolLogger', 'simple-history' ),
			'description' => _x( 'Logs management of cron events', 'PluginWPCrontrolLogger', 'simple-history' ),
			'name_via'    => _x( 'Using plugin WP Crontrol', 'PluginWPCrontrolLogger', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'added_new_event'       => _x( 'Added cron event "{event_hook}"', 'PluginWPCrontrolLogger', 'simple-history' ),
				'ran_event'             => _x( 'Manually ran cron event "{event_hook}"', 'PluginWPCrontrolLogger', 'simple-history' ),
				'deleted_event'         => _x( 'Deleted cron event "{event_hook}"', 'PluginWPCrontrolLogger', 'simple-history' ),
				'deleted_all_with_hook' => _x( 'Deleted all "{event_hook}" cron events', 'PluginWPCrontrolLogger', 'simple-history' ),
				'paused_hook'           => _x( 'Paused the "{event_hook}" cron event hook', 'PluginWPCrontrolLogger', 'simple-history' ),
				'resumed_hook'          => _x( 'Resumed the "{event_hook}" cron event hook', 'PluginWPCrontrolLogger', 'simple-history' ),
				'edited_event'          => _x( 'Edited cron event "{event_hook}"', 'PluginWPCrontrolLogger', 'simple-history' ),
				'added_new_schedule'    => _x( 'Added cron schedule "{schedule_name}"', 'PluginWPCrontrolLogger', 'simple-history' ),
				'deleted_schedule'      => _x( 'Deleted cron schedule "{schedule_name}"', 'PluginWPCrontrolLogger', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	public function loaded() {

		add_action( 'crontrol/added_new_event', array( $this, 'added_new_event' ) );
		add_action( 'crontrol/added_new_php_event', array( $this, 'added_new_event' ) );
		add_action( 'crontrol/ran_event', array( $this, 'ran_event' ) );
		add_action( 'crontrol/deleted_event', array( $this, 'deleted_event' ) );
		add_action( 'crontrol/deleted_all_with_hook', array( $this, 'deleted_all_with_hook' ), 10, 2 );
		add_action( 'crontrol/paused_hook', array( $this, 'paused_hook' ) );
		add_action( 'crontrol/resumed_hook', array( $this, 'resumed_hook' ) );
		add_action( 'crontrol/edited_event', array( $this, 'edited_event' ), 10, 2 );
		add_action( 'crontrol/edited_php_event', array( $this, 'edited_event' ), 10, 2 );
		add_action( 'crontrol/added_new_schedule', array( $this, 'added_new_schedule' ), 10, 3 );
		add_action( 'crontrol/deleted_schedule', array( $this, 'deleted_schedule' ) );
	}

	/**
	 * Fires after a new cron event is added.
	 *
	 * @param object $event {
	 *     An object containing the event's data.
	 *
	 *     @type string       $hook      Action hook to execute when the event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
	 *     @type string|false $schedule  How often the event should subsequently recur.
	 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
	 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
	 * }
	 */
	public function added_new_event( $event ) {
		$context = array(
			'event_hook' => $event->hook,
			'event_timestamp' => $event->timestamp,
			'event_args' => $event->args,
		);

		if ( $event->schedule ) {
			$context['event_schedule_name'] = $event->schedule;

			if ( function_exists( '\Crontrol\Event\get_schedule_name' ) ) {
				$context['event_schedule_name'] = \Crontrol\Event\get_schedule_name( $event );
			}
		} else {
			$context['event_schedule_name'] = _x( 'None', 'PluginWPCrontrolLogger', 'simple-history' );
		}

		$this->infoMessage(
			'added_new_event',
			$context
		);
	}

	/**
	 * Fires after a cron event is ran manually.
	 *
	 * @param object $event {
	 *     An object containing the event's data.
	 *
	 *     @type string       $hook      Action hook to execute when the event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
	 *     @type string|false $schedule  How often the event should subsequently recur.
	 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
	 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
	 * }
	 */
	public function ran_event( $event ) {
		$context = array(
			'event_hook' => $event->hook,
			'event_args' => $event->args,
		);

		$this->infoMessage(
			'ran_event',
			$context
		);
	}

	/**
	 * Fires after a cron event is deleted.
	 *
	 * @param object $event {
	 *     An object containing the event's data.
	 *
	 *     @type string       $hook      Action hook to execute when the event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
	 *     @type string|false $schedule  How often the event should subsequently recur.
	 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
	 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
	 * }
	 */
	public function deleted_event( $event ) {
		$context = array(
			'event_hook' => $event->hook,
			'event_timestamp' => $event->timestamp,
			'event_args' => $event->args,
		);

		if ( $event->schedule ) {
			$context['event_schedule_name'] = $event->schedule;

			if ( function_exists( '\Crontrol\Event\get_schedule_name' ) ) {
				$context['event_schedule_name'] = \Crontrol\Event\get_schedule_name( $event );
			}
		} else {
			$context['event_schedule_name'] = _x( 'None', 'PluginWPCrontrolLogger', 'simple-history' );
		}

		$this->infoMessage(
			'deleted_event',
			$context
		);
	}

	/**
	 * Fires after all cron events with the given hook are deleted.
	 *
	 * @param string $hook    The hook name.
	 * @param int    $deleted The number of events that were deleted.
	 */
	public function deleted_all_with_hook( $hook, $deleted ) {
		$context = array(
			'event_hook' => $hook,
			'events_deleted' => $deleted,
		);

		$this->infoMessage(
			'deleted_all_with_hook',
			$context
		);
	}

	/**
	 * Fires after a cron event hook is paused.
	 *
	 * @param string $hook The hook name.
	 */
	public function paused_hook( $hook ) {
		$context = array(
			'event_hook' => $hook,
		);

		$this->infoMessage(
			'paused_hook',
			$context
		);
	}

	/**
	 * Fires after a cron event hook is resumed (unpaused).
	 *
	 * @param string $hook The hook name.
	 */
	public function resumed_hook( $hook ) {
		$context = array(
			'event_hook' => $hook,
		);

		$this->infoMessage(
			'resumed_hook',
			$context
		);
	}

	/**
	 * Fires after a cron event is edited.
	 *
	 * @param object $event {
	 *     An object containing the new event's data.
	 *
	 *     @type string       $hook      Action hook to execute when the event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
	 *     @type string|false $schedule  How often the event should subsequently recur.
	 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
	 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
	 * }
	 * @param object $original {
	 *     An object containing the original event's data.
	 *
	 *     @type string       $hook      Action hook to execute when the event is run.
	 *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
	 *     @type string|false $schedule  How often the event should subsequently recur.
	 *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
	 *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
	 * }
	 */
	public function edited_event( $event, $original ) {
		$context = array(
			'event_hook' => $event->hook,
			'event_timestamp' => $event->timestamp,
			'event_args' => $event->args,
			'event_original_hook' => $original->hook,
			'event_original_timestamp' => $original->timestamp,
			'event_original_args' => $original->args,
		);

		if ( $event->schedule ) {
			$context['event_schedule_name'] = $event->schedule;

			if ( function_exists( '\Crontrol\Event\get_schedule_name' ) ) {
				$context['event_schedule_name'] = \Crontrol\Event\get_schedule_name( $event );
			}
		} else {
			$context['event_schedule_name'] = _x( 'None', 'PluginWPCrontrolLogger', 'simple-history' );
		}

		if ( $original->schedule ) {
			$context['event_original_schedule_name'] = $original->schedule;

			if ( function_exists( '\Crontrol\Event\get_schedule_name' ) ) {
				$context['event_original_schedule_name'] = \Crontrol\Event\get_schedule_name( $original );
			}
		} else {
			$context['event_original_schedule_name'] = _x( 'None', 'PluginWPCrontrolLogger', 'simple-history' );
		}

		$this->infoMessage(
			'edited_event',
			$context
		);
	}

	/**
	 * Fires after a new cron schedule is added.
	 *
	 * @param string $name     The internal name of the schedule.
	 * @param int    $interval The interval between executions of the new schedule.
	 * @param string $display  The display name of the schedule.
	 */
	public function added_new_schedule( $name, $interval, $display ) {
		$context = array(
			'schedule_name' => $name,
			'schedule_interval' => $interval,
			'schedule_display' => $display,
		);

		$this->infoMessage(
			'added_new_schedule',
			$context
		);
	}

	/**
	 * Fires after a cron schedule is deleted.
	 *
	 * @param string $name     The internal name of the schedule.
	 */
	public function deleted_schedule( $name ) {
		$context = array(
			'schedule_name' => $name,
		);

		$this->infoMessage(
			'deleted_schedule',
			$context
		);
	}

	public function getLogRowDetailsOutput( $row ) {
		switch ( $row->context_message_key ) {
			case 'added_new_event':
			case 'ran_event':
			case 'deleted_event':
			case 'deleted_all_with_hook':
			case 'edited_event':
				return $this->cronEventDetailsOutput( $row );
				break;
			case 'added_new_schedule':
			case 'deleted_schedule':
				return $this->cronScheduleDetailsOutput( $row );
				break;
		}

		return '';
	}

	protected function cronEventDetailsOutput( $row ) {
		$tmpl_row = '
            <tr>
                <td>%1$s</td>
                <td>%2$s</td>
            </tr>
        ';
		$context = $row->context;
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';

		if ( isset( $context['event_original_hook'] ) && ( $context['event_original_hook'] !== $context['event_hook'] ) ) {
			$key_text_diff = simple_history_text_diff(
				$context['event_original_hook'],
				$context['event_hook']
			);

			if ( $key_text_diff ) {
				$output .= sprintf(
					$tmpl_row,
					_x( 'Hook', 'PluginWPCrontrolLogger', 'simple-history' ),
					$key_text_diff
				);
			}
		}

		if ( isset( $context['event_original_args'] ) && ( $context['event_original_args'] !== $context['event_args'] ) ) {
			$key_text_diff = simple_history_text_diff(
				$context['event_original_args'],
				$context['event_args']
			);

			if ( $key_text_diff ) {
				$output .= sprintf(
					$tmpl_row,
					_x( 'Arguments', 'PluginWPCrontrolLogger', 'simple-history' ),
					$key_text_diff
				);
			}
		} else if ( isset( $context['event_args'] ) ) {
			if ( '[]' !== $context['event_args'] ) {
				$args = $context['event_args'];
			} else {
				$args = _x( 'None', 'PluginWPCrontrolLogger', 'simple-history' );
			}

			$output .= sprintf(
				$tmpl_row,
				_x( 'Arguments', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( $args )
			);
		}

		if ( isset( $context['event_original_timestamp'] ) && ( $context['event_original_timestamp'] !== $context['event_timestamp'] ) ) {
			$key_text_diff = simple_history_text_diff(
				gmdate( 'Y-m-d H:i:s', $context['event_original_timestamp'] ),
				gmdate( 'Y-m-d H:i:s', $context['event_timestamp'] )
			);

			if ( $key_text_diff ) {
				$output .= sprintf(
					$tmpl_row,
					_x( 'Next Run', 'PluginWPCrontrolLogger', 'simple-history' ),
					$key_text_diff
				);
			}
		} else if ( isset( $context['event_timestamp'] ) ) {
			$output .= sprintf(
				$tmpl_row,
				_x( 'Next Run', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( gmdate( 'Y-m-d H:i:s', $context['event_timestamp'] ) . ' UTC' )
			);
		}

		if ( isset( $context['event_original_schedule_name'] ) && ( $context['event_original_schedule_name'] !== $context['event_schedule_name'] ) ) {
			$key_text_diff = simple_history_text_diff(
				$context['event_original_schedule_name'],
				$context['event_schedule_name']
			);

			if ( $key_text_diff ) {
				$output .= sprintf(
					$tmpl_row,
					_x( 'Recurrence', 'PluginWPCrontrolLogger', 'simple-history' ),
					$key_text_diff
				);
			}
		} else if ( isset( $context['event_schedule_name'] ) ) {
			$output .= sprintf(
				$tmpl_row,
				_x( 'Recurrence', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( $context['event_schedule_name'] )
			);
		}

		$output .= '</table>';

		return $output;
	}

	protected function cronScheduleDetailsOutput( $row ) {
		$tmpl_row = '
            <tr>
                <td>%1$s</td>
                <td>%2$s</td>
            </tr>
        ';
		$context = $row->context;
		$output = '<table class="SimpleHistoryLogitem__keyValueTable">';

		if ( isset( $context['schedule_name'] ) ) {
			$output .= sprintf(
				$tmpl_row,
				_x( 'Name', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( $context['schedule_name'] )
			);
		}

		if ( isset( $context['schedule_interval'] ) ) {
			$output .= sprintf(
				$tmpl_row,
				_x( 'Interval', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( $context['schedule_interval'] )
			);
		}

		if ( isset( $context['schedule_display'] ) ) {
			$output .= sprintf(
				$tmpl_row,
				_x( 'Display Name', 'PluginWPCrontrolLogger', 'simple-history' ),
				esc_html( $context['schedule_display'] )
			);
		}

		$output .= '</table>';

		return $output;
	}
}
