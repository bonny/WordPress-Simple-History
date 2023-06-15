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

								$(document).trigger("SimpleHistory:NewRowsNotifier:newRowsFound", response);

						} else {

								$(document).trigger("SimpleHistory:NewRowsNotifier:noNewRowsFound", response);

			}

		}).fail(function(jqXHR, textStatus, errorThrown) {

			$elm.html( simple_history_NewRowsNotifierDropin.errorCheck );
			$elm.addClass("SimpleHistoryDropin__NewRowsNotifier--haveErrorCheck");

						$(document).trigger("SimpleHistory:NewRowsNotifier:newRowsError");

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

		// Start interval if prev interval was cleared
		if (!intervalID) {
			intervalID = setInterval(checkForUpdates, simple_history_NewRowsNotifierDropin.interval);
		}

	});

	// Reload the log When we click on the div with info about new rows
	$(document).on("click", ".SimpleHistoryDropin__NewRowsNotifier", function(e) {

		// Stop polling and stop any outgoing ajax request
		//clearInterval(intervalID);
		//intervalID = false;
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
				$(document).trigger("SimpleHistory:NewRowsNotifier:afterReload");

	});

}(jQuery));

// Change page title
(function($) {

		var $document = $(document);

		function onNewRowsFound(e, response) {
			setTitle(response.data.num_new_rows);
		}

		function onNoNewRowsFound(e, response) {
			setTitle(0);
		}

		function onNewRowsError(e) {
			setTitle("!");
		}

		function onNewRowsReload(e) {
			setTitle("");
		}

		function setTitle(newNum) {

			var title = document.title;

			// Remove any existing number first or !, like (123) Regular title => Regular title
			title = title.replace(/^\([\d!]+\) /, "");

			if ( newNum ) {
				title = "("+newNum+") " + title;
			}

			document.title = title;

		}

		$document.on("SimpleHistory:NewRowsNotifier:newRowsFound", onNewRowsFound);
		$document.on("SimpleHistory:NewRowsNotifier:noNewRowsFound", onNoNewRowsFound);
		$document.on("SimpleHistory:NewRowsNotifier:newRowsError", onNewRowsError);
		$document.on("SimpleHistory:NewRowsNotifier:afterReload", onNewRowsReload);

}(jQuery));
