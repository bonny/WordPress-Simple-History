<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs WordPress exports
 */
class Export_Logger extends Logger {
	/** @var string Logger slug */
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
				),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded
	 */
	public function loaded() {
		add_action( 'export_wp', array( $this, 'on_export_wp' ), 10, 1 );
	}

	/**
	 * Called when export is created.
	 * Fired from filter "export_wp".
	 *
	 * @param array $args Arguments passed to export_wp().
	 */
	public function on_export_wp( $args ) {
		$content = $args['content'] ?? '';

		$this->info_message(
			'created_export',
			array(
				'export_content' => $content,
				'export_args'    => Helpers::json_encode( $args ),
			)
		);
	}
}
