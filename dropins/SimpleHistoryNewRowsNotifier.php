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
				-webkit-transition: max-height 1s ease-in-out, background 0s;
				        transition: max-height 1s ease-in-out, background 0s;
			}
			
			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows {
				max-height: 100px;
				cursor: pointer;
			}

			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows:hover {
				/*text-decoration: underline;*/
				background: rgba(0, 255, 30, 0.5);
			}

			.SimpleHistoryDropin__NewRowsNotifier--haveNewRows:before {
				content: "";
				background-image: url(data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0ZWQgYnkgSWNvTW9vbi5pbyAtLT4KPCFET0NUWVBFIHN2ZyBQVUJMSUMgIi0vL1czQy8vRFREIFNWRyAxLjEvL0VOIiAiaHR0cDovL3d3dy53My5vcmcvR3JhcGhpY3MvU1ZHLzEuMS9EVEQvc3ZnMTEuZHRkIj4KPHN2ZyB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDMyIDMyIj4KPGcgaWQ9Imljb21vb24taWdub3JlIj4KCTxsaW5lIHN0cm9rZS13aWR0aD0iMSIgeDE9IiIgeTE9IiIgeDI9IiIgeTI9IiIgc3Ryb2tlPSIjNDQ5RkRCIiBvcGFjaXR5PSIiPjwvbGluZT4KPC9nPgoJPHBhdGggZD0iTTMwLjU0NSAxNS4yNzNsLTEwLjE4Mi00LjM2NCA0LjA3MC0yLjkwOGMtMi4xMjEtMi4yMzQtNS4xMS0zLjYzOC04LjQzMy0zLjYzOC01LjYzNiAwLTEwLjMzMyA0LjAwNy0xMS40MDUgOS4zMjdsLTIuNzE2LTEuMTI0YzEuNTQ1LTYuMzcyIDcuMjczLTExLjExMSAxNC4xMjEtMTEuMTExIDQuMzAxIDAgOC4xNTEgMS44NzkgMTAuODE2IDQuODQ3bDMuNzI5LTIuNjY1djExLjYzNnpNNy41NjcgMjMuOTk5YzIuMTE5IDIuMjM0IDUuMTEgMy42MzggOC40MzMgMy42MzggNS42NTggMCAxMC4zNjgtNC4wMzggMTEuNDE1LTkuMzg5bDIuNzEzIDEuMTYxYy0xLjUzNiA2LjM4NS03LjI3IDExLjEzNy0xNC4xMjggMTEuMTM3LTQuMzAxIDAtOC4xNTMtMS44NzktMTAuODE2LTQuODQ3bC0zLjcyOSAyLjY2NXYtMTEuNjM2bDEwLjE4MiA0LjM2NC00LjA3MCAyLjkwOHoiIGZpbGw9InVuZGVmaW5lZCI+PC9wYXRoPgo8L3N2Zz4K);
				background-repeat: no-repeat;
				background-size: 13px;
				width: 13px;
				height: 13px;
				display: inline-block;
				vertical-align: middle;
				margin-right: .5em;
			}
		</style>
		<script>
			
			(function($) {
				
				var elmWrapperClass = ".SimpleHhistoryLogitems-above";
				var $elmWrapper;
				var $elm;
				var ajaxurl = window.ajaxurl;
				var intervalID;

				var checkForUpdates = function() {

					var firstPageMaxID = simple_history2.logRowsCollection.max_id_first_page;
					
					$.get(ajaxurl, {
						action: "SimpleHistoryNewRowsNotifier",
						since_id: firstPageMaxID
					}).done(function(response) {

						// If new rows have been added then max_id is not 0 and larger than previos max id
						// Also total_row_count shows the number of added rows
						if (response.data.num_new_rows) {
							$elm.html( response.data.strings.newRowsFound );
							$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
						}

					});

				};

				// When the log is loaded the first time
				$(document).on("SimpleHistory:logRowsCollectionFirstLoad", function() {
					
					if (!$elmWrapper) {

						$elmWrapper = $(elmWrapperClass);
						$elm = $("<div />",{
							class: "SimpleHistoryDropin__NewRowsNotifier"
						});
						$elm.appendTo($elmWrapper);

					}

					intervalID = setInterval(checkForUpdates, 5000);

				});

				// When we click on the div 
				$(document).on("click", ".SimpleHistoryDropin__NewRowsNotifier", function(e) {

					// Just re-init the logcollection?
					clearInterval(intervalID);
					simple_history2.logRowsCollection.initialize();

				});

				$(document).on("SimpleHistory:logRowsCollectionInitialize", function() {
					
					if (!$elm) {
						return;
					}

					$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
				});

			}(jQuery));

			
		</script>
		<?php

	}

}
