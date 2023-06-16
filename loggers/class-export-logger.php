<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs WordPress exports
 */
class Export_Logger extends Logger {
	public $slug = 'SimpleExportLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => __( 'Export Logger', 'simple-history' ),
			'description' => __( 'Logs updates to WordPress export', 'simple-history' ),
			'capability'  => 'export',
			'messages'    => array(
				'created_export' => __( 'Created XML export', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Export', 'Export logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Created exports', 'Export logger: search', 'simple-history' ) => array(
							'created_export',
						),
					),
				), // end search array
			), // end labels
		);

		return $arr_info;
	}

	public function loaded() {
		add_action( 'export_wp', array( $this, 'on_export_wp' ), 10, 1 );
	}

	public function on_export_wp( $args ) {
		$content = $args['content'] ?? '';

		$this->info_message(
			'created_export',
			array(
				'export_content' => $content,
				'export_args' => Helpers::json_encode( $args ),
			)
		);
	}
}
