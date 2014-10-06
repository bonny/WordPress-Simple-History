<?php

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
			"name" => "Export Logger",
			"search_label" => "WordPress data exports",
			"description" => "Logs updates to WordPress export",
			"capability" => "export",
			"messages" => array(
				'created_export' => __('Created XML export', "simple-history"),
			)
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
				"args" => $this->simpleHistory->json_encode($args)
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
