<?php

namespace Simple_History\Services;

use Simple_History\Log_Query;

/**
 * Class for core services to extend,
 * i.e. services that are loaded early and are required for Simple History to work.
 */
class API extends Service {
	public function loaded() {
		if ( is_admin() ) {
			add_action( 'wp_ajax_simple_history_api', array( $this, 'api' ) );
		}
	}

	/**
	 * Base url is:
	 * /wp-admin/admin-ajax.php?action=simple_history_api
	 *
	 * Examples:
	 * http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&posts_per_page=5&paged=1&format=html
	 */
	public function api() {
		// Fake slow answers
		// sleep(2);
		// sleep(rand(0,3));
		$args = $_GET;
		unset( $args['action'] );

		// Type = overview | ...
		$type = $_GET['type'] ?? null;

		if ( empty( $args ) || ! $type ) {
			wp_send_json_error(
				array(
					_x( 'Not enough args specified', 'API: not enough arguments passed', 'simple-history' ),
				)
			);
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->simple_history->get_view_history_capability() ) ) {
			wp_send_json_error(
				array(
					'error' => 'CAPABILITY_ERROR',
				)
			);
		}

		if ( isset( $args['id'] ) ) {
			$args['post__in'] = array( $args['id'] );
		}

		$data = [];

		switch ( $type ) {
			case 'overview':
			case 'occasions':
			case 'single':
				// API use SimpleHistoryLogQuery, so simply pass args on to that
				$logQuery = new Log_Query();

				$data = $logQuery->query( $args );

				$data['api_args'] = $args;

				// Output can be array or HTML.
				if ( isset( $args['format'] ) && 'html' === $args['format'] ) {
					$data['log_rows_raw'] = [];

					foreach ( $data['log_rows'] as $key => $oneLogRow ) {
						$args = [];
						if ( $type == 'single' ) {
							$args['type'] = 'single';
						}

						$data['log_rows'][ $key ] = $this->simple_history->get_log_row_html_output( $oneLogRow, $args );
					}

					$data['num_queries'] = get_num_queries();
					$data['cached_result'] = $data['cached_result'] ?? false;
				}

				break;

			default:
				$data[] = 'Nah.';
		} // End switch().

		wp_send_json_success( $data );
	}

}
