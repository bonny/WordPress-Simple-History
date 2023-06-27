<?php

namespace Simple_History\Tests\Logger;

use Simple_History\Loggers\Logger;

/**
 * Logger that adds a simple message in the footer when loaded.
 * Used to test that logger is registered and that loaded() method is called.
 */
class Tests_Logger extends Logger {
    protected $slug = 'Tests';

    /** @inheritDoc */
    function get_info() {
        return [
            'name' => 'Tests logger',
        ];
    }

    /** @inheritDoc */
    function loaded()
    {
        add_action( 'admin_footer', [ $this, 'on_admin_footer' ] );
    }

    public function on_admin_footer() {
        echo "<p>Output in footer from the logger with slug tests.</p>";
    }
}
