<?php

/**
 * This is the class that does the main work!
 */
class Example_Dropin {
    // This will hold a reference to the simple history instance.
    private $sh;

    // Simple History will pass itself to the constructor.
    public function __construct( $sh ) {
        $this->sh = $sh;
        $this->init();
    }

    public function init() {
        add_action( 'init', array( $this, 'add_settings_tab' ) );
    }

    public function add_settings_tab() {
        $this->sh->registerSettingsTab(
            array(
                'slug' => 'dropin_example_tab_slug',
                'name' => __( 'Dropin example tab', 'simple-history' ),
                'function' => array( $this, 'settings_tab_output' ),
            )
        );
    }

    public function settings_tab_output() {
        ?>
        <h3>Hi there!</h3>
        <p>I'm the output from on settings tab.</p>
        <?php
    }
}
