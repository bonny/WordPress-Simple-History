(function($) {
	
	var elmWrapperClass = ".SimpleHistoryLogitems__above";
	var $elmWrapper;
	var $elm;
	var ajaxurl = window.ajaxurl;
	var intervalID;
	var ajax_jqXHR;

	var checkForUpdates = function() {

		var firstPageMaxID = simple_history.logRowsCollection.max_id_first_page;
		var apiArgs = {
			since_id: firstPageMaxID
		};
		
		// Let plugins filter the API args
		$(document).trigger("SimpleHistory:NewRowsNotifier:apiArgs", apiArgs);

		ajax_jqXHR = $.get(ajaxurl, {
			action: "SimpleHistoryNewRowsNotifier",
			apiArgs: apiArgs
		}, function() {}, "json").done(function(response) {

			// Always remove possible error class
			$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck");

			// If new rows have been added then max_id is not 0 and larger than previous max id
			// Also total_row_count shows the number of added rows
			if (response && response.data && response.data.num_new_rows) {
			
				$elm.html( response.data.strings.newRowsFound );
				$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");

			}

		}).fail(function(jqXHR, textStatus, errorThrown) {

			$elm.html( simple_history_NewRowsNotifierDropin.errorCheck );
			$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck");

		});

	};

	// When the log is loaded the first time
	// Actually it's also called when log is reloaded
	$(document).on("SimpleHistory:logRowsCollectionFirstLoad", function() {
		
		if (!$elmWrapper) {

			$elmWrapper = $(elmWrapperClass);
			$elm = $("<div />",{
				class: "SimpleHistoryDropin__NewRowsNotifier"
			});
			$elm.appendTo($elmWrapper);

		}

		intervalID = setInterval(checkForUpdates, simple_history_NewRowsNotifierDropin.interval);

	});

	// Reload the log When we click on the div with info about new rows
	$(document).on("click", ".SimpleHistoryDropin__NewRowsNotifier", function(e) {

		// Stop polling and stop any outgoing ajax request
		clearInterval(intervalID);
		ajax_jqXHR.abort();

		var prev_max_id = simple_history.rowsView.collection.max_id;
		
		simple_history.rowsView.once("renderDone", function() {
			
			var new_max_id = this.collection.max_id;

			var $logItems = jQuery(".SimpleHistoryLogitems li");
			var $newLogItems = $logItems.filter(function(i, elm) {
				var $elm = $(elm);
				var rowID = parseInt( $elm.data("row-id"), 10 );
				return (rowID > prev_max_id);
			});
			
			$newLogItems.addClass("SimpleHistoryLogitem--newRowSinceReload");

		});
		
		simple_history.logRowsCollection.reload();

	});

	$(document).on("SimpleHistory:logRowsCollectionReload", function() {
		
		if (!$elm) {
			return;
		}

		$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
	});

}(jQuery));