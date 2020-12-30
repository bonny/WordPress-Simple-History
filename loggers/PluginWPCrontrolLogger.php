<?php

defined('ABSPATH') or die();

/**
 * Logs cron event management from the WP Crontrol plugin
 * Plugin URL: https://wordpress.org/plugins/wp-crontrol/
 *
 * @since x.x
 */
class PluginWPCrontrolLogger extends SimpleLogger
{

    public $slug = __CLASS__;

    /**
     * Get array with information about this logger
     *
     * @return array
     */
    public function getInfo()
    {

        $arr_info = array(
            'name' => _x('WP Crontrol Logger', 'PluginWPCrontrolLogger', 'simple-history'),
            'description' => _x('Logs management of cron events', 'PluginWPCrontrolLogger', 'simple-history'),
            'name_via' => _x('Using plugin WP Crontrol', 'PluginWPCrontrolLogger', 'simple-history'),
            'capability' => 'manage_options',
            'messages' => array(
                'added_new_event' => _x('Added cron event "{event_hook}"', 'PluginWPCrontrolLogger', 'simple-history'),
            ),
        );

        return $arr_info;
    }

    public function loaded()
    {

        add_action('crontrol/added_new_event', array( $this, 'added_new_event' ));
        // add_action('crontrol/added_new_php_event', array( $this, 'added_new_event' ));
    }

    /**
     * Fires when a new cron event is added.
     *
     * @param object $event {
     *     An object containing an event's data.
     *
     *     @type string       $hook      Action hook to execute when the event is run.
     *     @type int          $timestamp Unix timestamp (UTC) for when to next run the event.
     *     @type string|false $schedule  How often the event should subsequently recur.
     *     @type array        $args      Array containing each separate argument to pass to the hook's callback function.
     *     @type int          $interval  The interval time in seconds for the schedule. Only present for recurring events.
     * }
     */
    public function added_new_event($event)
    {
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
            $context['event_schedule_name'] = _x('None', 'PluginWPCrontrolLogger', 'simple-history');
        }

        $this->infoMessage(
            'added_new_event',
            $context
        );
    }

    public function getLogRowDetailsOutput($row) {
        // return '<pre>' . print_r($row,true) . '</pre>';

        $tmpl_row = '
            <tr>
                <td>%1$s</td>
                <td>%2$s</td>
            </tr>
        ';
        $context = $row->context;
        $output = '<table class="SimpleHistoryLogitem__keyValueTable">';

        switch ( $row->context_message_key ) {
            case 'added_new_event':
            case 'added_new_php_event':
                if ( '[]' !== $context['event_args'] ) {
                    $args = $context['event_args'];
                } else {
                    $args = _x('None', 'PluginWPCrontrolLogger', 'simple-history');
                }

                $output .= sprintf(
                    $tmpl_row,
                    _x('Arguments', 'PluginWPCrontrolLogger', 'simple-history'),
                    esc_html( $args )
                );

                $output .= sprintf(
                    $tmpl_row,
                    _x('Next Run', 'PluginWPCrontrolLogger', 'simple-history'),
                    esc_html( gmdate( 'Y-m-d H:i:s', $context['event_timestamp'] ) . ' UTC' )
                );

                $output .= sprintf(
                    $tmpl_row,
                    _x('Recurrence', 'PluginWPCrontrolLogger', 'simple-history'),
                    esc_html( $context['event_schedule_name'] )
                );

                break;
        }

        $output .= '</table>';

        return $output;
    }
}
