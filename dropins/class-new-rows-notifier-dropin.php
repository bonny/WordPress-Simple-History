<?php

namespace Simple_History\Dropins;

use Simple_History\Log_Query;

/**
 * Dropin Name: New Items Notifier
 * Dropin Description: Checks for new rows and displays a info bar when new items are available
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class New_Rows_Notifier_Dropin extends Dropin {
	// How often we should check for new rows, in ms.
	private $interval = 10000;

	public function loaded() {

		/**
		 * How often the script checks for new rows.
		 *
		 * @example Change the interval from the default 10000 ms (10 seconds) to a minute (300000 ms).
		 * // Change the interval from the default 10000 ms (10 seconds) to a minute (300000 ms).
		 * ```php
		 * add_filter(
		 *   'SimpleHistoryNewRowsNotifier/interval',
		 *   function( $interval ) {
		 *     $interval = 300000;
		 *     return $interval;
		 *   }
		 * );
		 * ```
		 *
		 * @param int $interval Interval in MS.
		 */
		$this->interval = (int) apply_filters( 'SimpleHistoryNewRowsNotifier/interval', $this->interval );

		add_action( 'wp_ajax_SimpleHistoryNewRowsNotifier', array( $this, 'ajax' ) );
		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url( __FILE__ );

		$arr_localize_data = array(
			'interval' => $this->interval,
			'errorCheck' => _x( 'An error occurred while checking for new events', 'New rows notifier: error while checking for new rows', 'simple-history' ),
		);

		wp_enqueue_script( 'simple_history_NewRowsNotifierDropin', $file_url . 'new-rows-notifier-dropin.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION, true );
		wp_localize_script( 'simple_history_NewRowsNotifierDropin', 'simple_history_NewRowsNotifierDropin', $arr_localize_data );
		wp_enqueue_style( 'simple_history_NewRowsNotifierDropin', $file_url . 'new-rows-notifier-dropin.css', null, SIMPLE_HISTORY_VERSION );
	}

	public function ajax() {

		$apiArgs = $_GET['apiArgs'] ?? array();

		if ( ! $apiArgs ) {
			wp_send_json_error(
				array(
					'error' => 'MISSING_APIARGS',
				)
			);
		}

		if ( empty( $apiArgs['since_id'] ) || ! is_numeric( $apiArgs['since_id'] ) ) {
			wp_send_json_error(
				array(
					'error' => 'MISSING_SINCE_ID',
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

		// $since_id = isset( $_GET["since_id"] ) ? absint($_GET["since_id"]) : null;
		$logQueryArgs = $apiArgs;

		$logQuery = new Log_Query();
		$answer = $logQuery->query( $logQueryArgs );

		// Use our own response array instead of $answer to keep size down
		$json_data = array();

		$numNewRows = $answer['total_row_count'] ?? 0;
		$json_data['num_new_rows'] = $numNewRows;
		$json_data['num_mysql_queries'] = get_num_queries();
		$json_data['cached_result'] = $answer['cached_result'] ?? false;

		if ( $numNewRows ) {
			// We have new rows
			// Append strings
			$textRowsFound = sprintf(
				// translators: %s is the number of new events
				_n( '%s new event', '%s new events', $numNewRows, 'simple-history' ),
				$numNewRows
			);

			$json_data['strings'] = array(
				'newRowsFound' => $textRowsFound,
			);
		}

		wp_send_json_success( $json_data );
	}
}
