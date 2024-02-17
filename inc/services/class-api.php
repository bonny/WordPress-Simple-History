<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Query;

/**
 * Setup API Ajax support.
 */
class API extends Service {
	/**
	 * @inheritdoc
	 */
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
		$args = $_GET;
		unset( $args['action'] );

		// Type = overview | ...
		$type = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );

		if ( empty( $args ) || ! $type ) {
			wp_send_json_error(
				array(
					_x( 'Not enough args specified', 'API: not enough arguments passed', 'simple-history' ),
				)
			);
		}

		// User must have capability to view the history page.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
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
				// API use SimpleHistoryLogQuery, so simply pass args on to that.
				$logQuery = new Log_Query();

				$data = $logQuery->query( $args );

				$data['api_args'] = $args;

				// $data['log_rows_full_group_by'] = array_values( $data_full_group_by );

				// Output can be array or HTML.
				if ( isset( $args['format'] ) && 'html' === $args['format'] ) {
					foreach ( $data['log_rows'] as $key => $oneLogRow ) {
						$format_args = [];
						if ( $type == 'single' ) {
							$format_args['type'] = 'single';
						}

						$data['log_rows'][ $key ] = $this->simple_history->get_log_row_html_output( $oneLogRow, $format_args );
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
