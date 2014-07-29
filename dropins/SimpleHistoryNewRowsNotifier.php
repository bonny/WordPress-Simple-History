<?php

/*
Dropin Name: New Items Notifier
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryNewrowsNotifier {

	// Simple History instance
	private $sh;

	function __construct($sh) {
		
		$this->sh = $sh;

		add_action( 'admin_head', array($this, 'on_admin_head'));
		add_action( 'wp_ajax_SimpleHistoryNewrowsNotifier', array($this, 'ajax') );

	}

	public function ajax() {

		$firstPageMaxID = isset( $_GET["firstPageMaxID"] ) ? absint($_GET["firstPageMaxID"]) : null;

		if ( ! $firstPageMaxID ) {
			exit;
		}

		$logQuery = new SimpleHistoryLogQuery();
		$answer = $logQuery->query(array(

		));

		sf_d($answer);


	}

	public function on_admin_head() {

		?>
		<script>
			
			(function($) {
				
				var elmClass = ".SimpleHhistoryLogitems-above";
				var $elm;
				var ajaxurl = window.ajaxurl;

				var checkForUpdates = function() {

					var firstPageMaxID = simple_history2.logRowsCollection.max_id_first_page;
					console.log("Checking for updates after maxID " + firstPageMaxID);
					
					$.get(ajaxurl, {
						action: "SimpleHistoryNewrowsNotifier",
						firstPageMaxID: firstPageMaxID
					}).done(function(data) {
						console.log("done");
					});

					//console.log(simple_history2.logRowsCollection);
					//$elm.append("<br>Checking for updates after maxID " + firstPageMaxID);

				};

				// WHen the log is loaded the first time
				$(document).on("SimpleHistory:logLoadedFirst", function() {
					
					// console.log("loaded first", simple_history2.logRowsCollection);
					$elm = $(elmClass);

					setInterval(checkForUpdates, 2000);

				});

			}(jQuery));

			
		</script>
		<?php

	}

}
