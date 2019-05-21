<?php

defined('ABSPATH') or die();

/**
 * Logger for events stored earlier than v2
 * and for events added via simple_history_add
 *
 * @since 2.0
 */
class SimpleLegacyLogger extends SimpleLogger
{


    /**
     * Unique slug for this logger
     * Will be saved in DB and used to associate each log row with its logger
     */
    public $slug = 'SimpleLegacyLogger';

    public function __construct()
    {

        // $this->info(__CLASS__ . " construct()");
    }

    /**
     * Get array with information about this logger
     *
     * @return array
     */
    function getInfo()
    {

        $arr_info = array(
            'name' => 'Legacy Logger',
            'description' => 'Formats old events',
            'capability' => 'edit_pages',
            'messages' => array(),
            /*
             "labels" => array(
                "search" => array(
                    "label" => _x("Export", "Export logger: search", "simple-history"),
                    "options" => array(
                        _x("Exports created", "Core updates logger: search", "simple-history") => array(
                            "created_export"
                        ),
                    )
                ) // end search array
            ) // end labels
            */

        );

        return $arr_info;
    }

    public function getLogRowPlainTextOutput($row)
    {

        $message = $row->message;
        $context = $row->context;

        $out = '';

        global $wpdb;

        // Get old columns for this event
        $sql = sprintf(
            '
			SELECT * FROM %1$s
			WHERE id = %2$d
			',
            $wpdb->prefix . SimpleHistory::DBTABLE,
            $row->id
        );

        $one_item = $wpdb->get_row($sql);

        // $out .= print_r($row, true);
        // Code mostly from version 1.x
        $object_type = ucwords($one_item->object_type);
        $object_name = esc_html($one_item->object_name);
        $user = get_user_by('id', $one_item->user_id);
        $user_nicename = esc_html(@$user->user_nicename);
        $user_email = esc_html(@$user->user_email);
        $description = '';

        if ($user_nicename) {
            $description .= sprintf(__('By %s', 'simple-history'), $user_nicename);
        }

        if (isset($one_item->occasions)) {
            $description .= sprintf(__('%d occasions', 'simple-history'), sizeof($one_item->occasions));
        }

        $item_title = esc_html($object_type) . ' "' . esc_html($object_name) . "\" {$one_item->action}";
        $item_title = html_entity_decode($item_title, ENT_COMPAT, 'UTF-8');

        $out .= "$item_title";
        $out .= "<br>$description";

        return $out;
    }
}
