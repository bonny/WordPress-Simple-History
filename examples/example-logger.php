<?php

// No external calls allowed to test file
exit;


/**
 * This example shows how to create a simple logger that will
 * log 404-errors on your website.
 */

// We use the function "register_logger" to tell tell SimpleHistory that our custom logger exists.
// We call it from inside the filter "simple_history/add_custom_logger".
add_action("simple_history/add_custom_logger", function($simpleHistory) {

    $simpleHistory->register_logger("FourOhFourLogger");

});

// We make sure that the SimpleLogger class exists before trying to extend it.
// This prevents error if the Simple History plugin gets inactivated.
if (class_exists("SimpleLogger")) {

	/**
	 * This is the class that does the main work!
	 */
    class FourOhFourLogger extends SimpleLogger {

        /**
         * The slug is ised to identify this logger in various places.
         * We use the name of the class too keep it simple.
         */
        public $slug = __CLASS__;

        /**
    	 * Return information about this logger.
         * Used to show info about the logger at various places.
    	 */
    	function getInfo() {

    		$arr_info = array(
    			"name" => "404 Logger",
    			"description" => "Logs access to pages that result in page not found errors (error code 404)",
    			"capability" => "edit_pages",
    			"messages" => array(
    				'page_not_found' => __('Got a 404-page when trying to visit "{request_uri}"', "simple-history"),
    			),
    			"labels" => array(
    				"search" => array(
    					"label" => _x("Pages not found (404 errors)", "User logger: 404", "simple-history"),
    					"options" => array(
    						_x("Pages not found", "User logger: 404", "simple-history") => array(
    							"page_not_found",
    						),
    					),
    				), // end search
    			), // end labels
    		);

    		return $arr_info;

    	}

        /**
         * When Simple History has loaded this logger it automagically
         * calls a loaded() function. This is where you add your actions
         * and other logger functionality.
         */
        function loaded() {

            // Call a function when WordPress finds a 404 page
            add_action("404_template", array($this, "on_404_template"), 10, 1);

        }

        /**
         * Function that is called when WordPress finds a 404 page.
         * It collects some info and then it logs a warning message
         * to the log.
         */
        function on_404_template($template) {

            $context = array(
                "_initiator" => SimpleLoggerLogInitiators::WEB_USER,
                'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : "",
                'http_referer' => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : "",
            );

            $this->warningMessage("page_not_found", $context);

            return $template;

        }

    }
}
