<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs WordPress exports
 */
class SimpleExportLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => __("Export Logger", "simple-history"),
			"description" => __("Logs updates to WordPress export", "simple-history"),
			"capability" => "export",
			"messages" => array(
				'created_export' => __('Created XML export', "simple-history"),
			),
			"labels" => array(
				"search" => array(
					"label" => _x("Export", "Export logger: search", "simple-history"),
					"options" => array(
						_x("Created exports", "Export logger: search", "simple-history") => array(
							"created_export"
						),						
					)
				) // end search array
			) // end labels
		);
		
		return $arr_info;

	}

	function loaded() {

		add_action( 'export_wp', array($this, "on_export_wp"), 10, 1 );
 
	}

	function on_export_wp($args) {

		$this->infoMessage(
			"created_export",
			array(
				"args" => $this->simpleHistory->json_encode( $args )
			)
		);

	}

	/**
	 * Get detailed output
	 */
	/*
	function getLogRowDetailsOutput($row) {
	
		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		return $output;

	}
	*/

}
