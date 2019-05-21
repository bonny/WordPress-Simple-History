<?php

// No external calls allowed to test file
exit;


/**
 * This example shows how to create a simple dropin
 * that will add a tab to the simple history settings page
 */

// We use the function "register_logger" to tell tell SimpleHistory that our custom logger exists.
// We call it from inside the filter "simple_history/add_custom_logger".
add_action('simple_history/add_custom_dropin', function ($simpleHistory) {

    $simpleHistory->register_dropin('AddSettingsPageTab');
});


/**
 * This is the class that does the main work!
 */
class AddSettingsPageTab
{

    // This will hold a reference to the simple history instance
    private $sh;

    // simple history will pass itself to the constructor
    function __construct($sh)
    {

        $this->sh = $sh;

        $this->init();
    }

    function init()
    {

                add_action('init', array( $this, 'add_settings_tab' ));
    }

    function add_settings_tab()
    {

        $this->sh->registerSettingsTab(array(
            'slug' => 'my_unique_settings_tab_slug',
            'name' => __('Example tab', 'simple-history'),
            'function' => array( $this, 'settings_tab_output' ),
        ));
    }

    function settings_tab_output()
    {

        ?>

        <h3>Hi there!</h3>

        <p>I'm the output from on settings tab.</p>

                <?php
    }
} // end class


