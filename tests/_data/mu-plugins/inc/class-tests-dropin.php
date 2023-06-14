<?php

namespace Simple_History\Tests\Dropin;

use Simple_History\Dropins\Dropin;

/**
 * Dropin that adds a simple hello message to a new tab.
 */
class Tests_Dropin extends Dropin {
    /** @inheritDoc */
    function loaded()
    {
        add_action( 'init', [ $this, 'add_settings_tab' ] );
    }

    public function add_settings_tab() {
        $this->simple_history->register_settings_tab([
            'slug' => 'tests_dropin_settings_tab_slug',
            'name' => 'Namespaced dropin example tab',
            'function' => [$this, 'settings_tab_output'],
        ]);
    }

    public function settings_tab_output() {
        echo 'Namespaced dropin example page output';
    }
}
