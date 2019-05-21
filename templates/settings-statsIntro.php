<?php

defined('ABSPATH') or die();

// Number of rows the last n days
function get_num_rows_last_n_days($period_days)
{

    global $wpdb;

    $sql = sprintf(
        'select count(*) FROM %1$s WHERE UNIX_TIMESTAMP(date) >= %2$d',
        $wpdb->prefix . SimpleHistory::DBTABLE,
        strtotime("-$period_days days")
    );

    return $wpdb->get_var($sql);
}

printf(
    __('<b>%1$s rows</b> have been logged the last <b>%2$s days</b>', 'simple-history'),
    get_num_rows_last_n_days($period_days),
    $period_days
);
