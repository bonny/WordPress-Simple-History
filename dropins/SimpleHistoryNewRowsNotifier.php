<?php

/**
 * Dropin Name: New Items Notifier
 * Dropin Description: Checks for new rows and displays a info bar when new items are available
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
*/

class SimpleHistoryNewRowsNotifier {

	// Simple History instance
	private $sh;

	// How often we should check for new rows, in ms
	private $interval = 10000;

	function __construct($sh) {
		
		$this->sh = $sh;
		
		// How often the script checks for new rows
		$this->interval = (int) apply_filters("SimpleHistoryNewRowsNotifier/interval", $this->interval);

		add_action( 'admin_head', array($this, 'on_admin_head'));
		add_action( 'wp_ajax_SimpleHistoryNewRowsNotifier', array($this, 'ajax') );
		add_action( "simple_history/enqueue_admin_scripts", array($this, "enqueue_admin_scripts") );

	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url(__FILE__);
		wp_enqueue_script("simple_history_NewRowsNotifierDropin", $file_url . "SimpleHistoryNewRowsNotifierDropin.js", array("jquery"), SimpleHistory::VERSION, true);

		$arr_localize_data = array(
			"interval" => $this->interval
		);
		wp_localize_script( "simple_history_NewRowsNotifierDropin", "simple_history_NewRowsNotifierDropin", $arr_localize_data );
		

	}

	public function ajax() {

		$apiArgs = isset( $_GET["apiArgs"] ) ? $_GET["apiArgs"] : array();

		if ( ! $apiArgs ) {
			wp_send_json_error( array("error" => "MISSING_APIARGS") );
			exit;
		}

		if ( empty( $apiArgs["since_id"] ) || ! is_numeric( $apiArgs["since_id"] ) ) {
			wp_send_json_error( array("error" => "MISSING_SINCE_ID") );
			exit;
		}

		// $since_id = isset( $_GET["since_id"] ) ? absint($_GET["since_id"]) : null;

		$logQueryArgs = $apiArgs;

		$logQuery = new SimpleHistoryLogQuery();

		$answer = $logQuery->query( $logQueryArgs );

		// Use our own repsonse array instead of $answer to keep size down
		$json_data = array();
		
		$numNewRows = isset( $answer["total_row_count"] ) ? $answer["total_row_count"] : 0;
		$json_data["num_new_rows"] = $numNewRows;

		if ($numNewRows) {
	
			// We have new rows

			// Append strings
			$textRowsFound = sprintf( _n( '1 new row', '%d new rows', $numNewRows, 'simple-history' ), $numNewRows );
			$json_data["strings"] = array(
				"newRowsFound" => $textRowsFound
			);

		}

		wp_send_json_success( $json_data );

	}

	public function on_admin_head() {

		?>
		<style>
			.SimpleHistoryDropin__NewRowsNotifier {
				max-height: 0;
				overflow: hidden;
				text-align: center;
				background: white;
				line-height: 40px;
				background: rgba(0, 255, 30, 0.15);
				-webkit-transition: max-height .5s ease-out, background 0s;
				        transition: max-height .5s ease-out, background 0s;
			}
		
			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows {
				max-height: 50px;
				cursor: pointer;
			}

			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows:before {
				content: '\f463';
				font: 400 20px/1 dashicons;
				-webkit-font-smoothing: antialiased;
				display: inline-block;
				vertical-align: middle;
				margin-right: .5em;
			}

			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows:hover {
				/*text-decoration: underline;*/
				background: rgba(0, 255, 30, 0.5);
			}

			/* when there is a remote error or server down etc */
			.SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck {
				max-height: 50px;
				background: rgb(254, 247, 241);
			}

		</style>

		<?php

	}

} // class

