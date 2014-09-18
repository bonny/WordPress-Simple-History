<?php

/*
Dropin Name: New Items Notifier
Dropin Description: Checks for new rows and displays a info bar when new items are available
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
		<script>
			
			(function($) {
				
				var elmWrapperClass = ".SimpleHistoryLogitems__above";
				var $elmWrapper;
				var $elm;
				var ajaxurl = window.ajaxurl;
				var intervalID;

				var strings = {
					"errorCheck": "<?php _ex('An error occured while checking for new log rows', 'New rows notifier: error while checking for new rows', 'simple-history') ?>"
				};

				var checkForUpdates = function() {

					var firstPageMaxID = simple_history2.logRowsCollection.max_id_first_page;
					
					$.get(ajaxurl, {
						action: "SimpleHistoryNewRowsNotifier",
						since_id: firstPageMaxID
					}, function() {}, "json").done(function(response) {

						// Always remove possible error class
						$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck");

						// If new rows have been added then max_id is not 0 and larger than previos max id
						// Also total_row_count shows the number of added rows
						if (response && response.data && response.data.num_new_rows) {
							$elm.html( response.data.strings.newRowsFound );
							$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
						}

					}).fail(function(jqXHR, textStatus, errorThrown) {

						console.log("strings", strings);
						$elm.html( strings.errorCheck );
						$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck");

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
					simple_history2.logRowsCollection.reload();

				});

				$(document).on("SimpleHistory:logRowsCollectionReload", function() {
					
					if (!$elm) {
						return;
					}

					$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
				});

			}(jQuery));
			
		</script>

		<?php

	}

} // class

