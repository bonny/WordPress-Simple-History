<?php

/*
Dropin Name: New Items Notifier
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryNewRowsNotifier {

	// Simple History instance
	private $sh;

	function __construct($sh) {
		
		$this->sh = $sh;

		add_action( 'admin_head', array($this, 'on_admin_head'));
		add_action( 'wp_ajax_SimpleHistoryNewRowsNotifier', array($this, 'ajax') );

	}

	public function ajax() {

		$since_id = isset( $_GET["since_id"] ) ? absint($_GET["since_id"]) : null;

		if ( ! $since_id ) {
			exit;
		}

		$logQuery = new SimpleHistoryLogQuery();
		$answer = $logQuery->query(array(
			"since_id" => $since_id
		));
		#sf_d($answer);

		// Append strings
		$numNewRows = isset( $answer["total_row_count"] ) ? $answer["total_row_count"] : 0;
		$textRowsFound = sprintf( _n( '1 new row found', '%d new rows found', $numNewRows, 'simple-history' ), $numNewRows );
		$answer["SimpleHistoryNewRowsNotifier"] = array(
			"strings" => array(
				"newRowsFound" => $textRowsFound
			)
		);

		wp_send_json_success( $answer );

	}

	public function on_admin_head() {

		?>
		<style>
			.SimpleHistoryDropin__NewRowsNotifier {
				max-height: 0;
				overflow: hidden;
				text-align: center;
				background: white;
				-webkit-transition: all 1s ease-in-out;
				        transition: all 1s ease-in-out;
			}
			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows {
				max-height: 100px;
			}
		</style>
		<script>
			
			(function($) {
				
				var elmWrapperClass = ".SimpleHhistoryLogitems-above";
				var $elmWrapper;
				var $elm;
				var ajaxurl = window.ajaxurl;

				var checkForUpdates = function() {

					var firstPageMaxID = simple_history2.logRowsCollection.max_id_first_page;
					
					$.get(ajaxurl, {
						action: "SimpleHistoryNewRowsNotifier",
						since_id: firstPageMaxID
					}).done(function(response) {

						// If new rows have been added then max_id is not 0 and larger than previos max id
						// Also total_row_count shows the number of added rows
						if (response.data.total_row_count) {
							// console.log("Found new rows!!!", response.data.total_row_count);
							$elm.html( response.data.SimpleHistoryNewRowsNotifier.strings.newRowsFound );
							$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
						}

					});

				};

				// WHen the log is loaded the first time
				$(document).on("SimpleHistory:logLoadedFirst", function() {
					
					if (!$elmWrapper) {
						$elmWrapper = $(elmWrapperClass);
						$elm = $("<div />",{
							class: "SimpleHistoryDropin__NewRowsNotifier"
						});
						$elm.appendTo($elmWrapper);
					}

					setInterval(checkForUpdates, 2000);

				});

			}(jQuery));

			
		</script>
		<?php

	}

}
