<?php

/**
 * Logger class that uses old Logger format, without namespace.
 */
class Example_Logger extends SimpleLogger {
	public $slug = 'FourOhFourLogger';

	public function getInfo() {
		$arr_info = array(
			'name'        => __( '404 Logger', 'simple-history' ),
			'description' => __( 'Logs access to pages that result in page not found errors (error code 404)', 'simple-history' ),
			'capability'  => 'edit_pages',
			'messages'    => array(
				'page_not_found' => __( 'Got a 404-page when trying to visit "{request_uri}"', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Pages not found (404 errors)', 'User logger: 404', 'simple-history' ),
					'options' => array(
						_x( 'Pages not found', 'User logger: 404', 'simple-history' ) => array(
							'page_not_found',
						),
					),
				), // end search
			), // end labels
		);

		return $arr_info;
	}

	public function loaded() {
		add_action( '404_template', array( $this, 'handle_404_template' ), 10, 1 );
	}

	public function handle_404_template( $template ) {
		$context = array(
			'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',
			'http_referer' => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
		);

		$this->warningMessage( 'page_not_found', $context );

		return $template;
	}
}
