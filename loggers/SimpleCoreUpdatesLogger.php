<?php

/**
 * Logs WordPress core updates
 */
class SimpleCoreUpdatesLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	public function loaded() {
		
		add_action( '_core_updated_successfully', array( $this, "on_core_updated" ) );

	}

	public function on_core_updated($wp_version_old) {
		
		global $pagenow, $wp_version;

		$auto_update = true;		
		if ( $pagenow == 'update-core.php' ) {
			$auto_update = false;
		}

		if ($auto_update) {
			$message = "core_auto_updated";
		} else {
			$message = "core_updated";
		}

		$this->noticeMessage(
			$message,
			array(
				"prev_version" => $wp_version_old,
				"new_version" => $wp_version
			)
		);

	}

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "Core Updates Logger",
			"description" => "Logs the update of WordPress (manual and automatic updates)",
			"capability" => "update_core",
			"messages" => array(
				'core_updated' => __('Updated WordPress from {prev_version} to {new_version}', 'simple-history'),
				'core_auto_updated' => __('WordPress updated from {prev_version} to {new_version}', 'simple-history')
			)
		);
		
		return $arr_info;

	}

}
