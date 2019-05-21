<?php

defined('ABSPATH') or die();

/*
Dropin Name: Sidebar with link to settings
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySidebarSettings
{

    /**
     * Simple History isntance
     *
     * @var object $sh Simple History instance.
     */
    private $sh;

    /**
     * Constructor.
     *
     * @param object $sh Simple History instance.
     */
    function __construct($sh)
    {

        $this->init($sh);
    }

    /**
     * Init
     *
     * @param object $sh Simple History instance.
     */
    function init($sh)
    {

        $this->sh = $sh;

        add_action('simple_history/dropin/sidebar/sidebar_html', array( $this, 'on_sidebar_html' ), 5);
    }

    /**
     * Output HTML
     */
    function on_sidebar_html()
    {

        ?>

        <div class="postbox">

            <h3 class="hndle"><?php esc_html_e('Settings', 'simple-history') ?></h3>

            <div class="inside">

                <p>
                    <?php

                    /*
                    Visit the settings page to change the number of items to show and
                    where to show
                    rss feed
                    clear log

                    - Visit the settings page to change the number of events to show, to get
                    - Visit the settings page
                    */
                    printf(
                        wp_kses(
                            /* translators: 1: URL to settings page */
                            __('<a href="%1$s">Visit the settings page</a> to change things like the number of events to show and to get access to the RSS feed with all events, and more.', 'simple-history'),
                            array(
                                'a' => array(
                                    'href' => array(),
                                ),
                            )
                        ),
                        esc_url(menu_page_url(SimpleHistory::SETTINGS_MENU_SLUG, false))
                    );
                    ?>
                </p>

            </div>
        </div>

        <?php
    }
}
