<?php

defined( 'ABSPATH' ) or die();

/*
Dropin Name: WP CLI
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryWPCLIDropin {

	// Simple History instance
	private $sh;

	function __construct($sh) {

		$this->sh = $sh;
		#add_action( 'admin_menu', array($this, 'add_settings'), 50 );
		#add_action( 'plugin_row_meta', array($this, 'action_plugin_row_meta'), 10, 2);

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_commands();
		}

	}

	private function register_commands() {
		WP_CLI::add_command( 'simple-history list', array($this, 'commandList') );
	}

	public function commandList($args) {

        // Override capability check: if you can run wp cli commands you can read all loggers
        add_action( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 3);

		WP_CLI::log( 'Showing the last 10 events in Simple History' );

		$query = new SimpleHistoryLogQuery();

		$query_args = array(
			"paged" => 1,
			"posts_per_page" => 10
		);

		$events = $query->query( $query_args );

		// A cleaned version of the events, formatted for wp cli table output
		$eventsCleaned = array();

		foreach ($events["log_rows"] as $row) {
		    $header_output = $this->sh->getLogRowHeaderOutput($row);
		    $text_output = $this->sh->getLogRowPlainTextOutput($row);
		    // $details_output = $this->sh->getLogRowDetailsOutput($row);

			$header_output = strip_tags( html_entity_decode( $header_output, ENT_QUOTES, 'UTF-8') );
			$header_output = trim(preg_replace('/\s\s+/', ' ', $header_output));

			$text_output = strip_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8') );

		    $eventsCleaned[] = array(
		    	"who_when" => $header_output,
		    	"what" => $text_output,
		    	"count" => $row->subsequentOccasions
		    	// "details" => $details_output
		    );
		}

		#print_r($events);
		#print_r($eventsCleaned);

		$fields = array(
			'who_when',
			'what',
			'count'
		);
		WP_CLI\Utils\format_items( "table", $eventsCleaned, $fields );

    	WP_CLI::success( "Done" );
	}

}
