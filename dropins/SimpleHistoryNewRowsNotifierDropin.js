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

		var firstPageMaxID = simple_history.logRowsCollection.max_id_first_page;
		var apiArgs = {
			since_id: firstPageMaxID
		};
		
		// Let plugins filter the API args
		$(document).trigger("SimpleHistory:NewRowsNotifier:apiArgs", apiArgs);

		$.get(ajaxurl, {
			action: "SimpleHistoryNewRowsNotifier",
			apiArgs: apiArgs
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

		intervalID = setInterval(checkForUpdates, simple_history_NewRowsNotifierDropin.interval);

	});

	// When we click on the div 
	$(document).on("click", ".SimpleHistoryDropin__NewRowsNotifier", function(e) {

		// Just re-init the logcollection?
		clearInterval(intervalID);
		simple_history.logRowsCollection.reload();

	});

	$(document).on("SimpleHistory:logRowsCollectionReload", function() {
		
		if (!$elm) {
			return;
		}

		$elm.removeClass("SimpleHistoryDropin__NewRowsNotifier--haveNewRows");
	});

}(jQuery));