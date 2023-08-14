<?php

namespace Simple_History\Tests;

use Simple_History\Loggers\Logger;

/**
 * Test that logging works during cron jobs.
 * Adds a cron that logs things and then we can check that the log was created.
 */
add_action('init', function () {
    //wp_schedule_event( time(), 'daily', 'simple_history/maybe_purge_db' );
    add_action('simple_history/tests/cron', function () {
        SimpleLogger()->info('This is a log from a cron job');
    });

    wp_schedule_single_event(time(), 'simple_history/tests/cron');
});
