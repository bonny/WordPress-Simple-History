/**
 *
 */

var SimpleHistoryFilterDropin = (function($) {

	var $elms = {};
	var isFilteringActive = false;
	var activeFilters = {};

	function init() {

		addFetchListener();

	}

	function onDomReadyInit() {

		$elms.filter_container = $(".SimpleHistory__filters");
		$elms.filter_user = $elms.filter_container.find(".SimpleHistory__filters__filter--user");
		$elms.filter_button = $elms.filter_container.find(".js-SimpleHistoryFilterDropin-doFilter");
		$elms.filter_form = $elms.filter_container.find(".js-SimpleHistory__filters__form");
		$elms.show_more_filters_button = $elms.filter_container.find(".js-SimpleHistoryFilterDropin-showMoreFilters");
		$elms.more_filters_container = $elms.filter_container.find(".js-SimpleHistory__filters__moreFilters");

		enhanceSelects();
		addListeners();

	}

	function addListeners() {

		$elms.filter_form.on("submit", onSubmitForm);
		$elms.show_more_filters_button.on("click", onClickMoreFilters);

	}

	function onClickMoreFilters() {
		
		//$elms.more_filters_container.toggleClass("is-visible");
		$elms.filter_container.toggleClass("is-showingMoreFilters");

	}

	function onSubmitForm(e) {

		e.preventDefault();

		// form serialize
		// search=apa&loglevels=critical&loglevels=alert&loggers=SimpleMediaLogger&loggers=SimpleMenuLogger&user=1&months=2014-09 SimpleHistoryFilterDropin.js?ver=2.0:40
		var $search = $elms.filter_form.find("[name='search']");
		var $loglevels = $elms.filter_form.find("[name='loglevels']");
		var $messages = $elms.filter_form.find("[name='messages']");
		var $user = $elms.filter_form.find("[name='user']");
		var $months = $elms.filter_form.find("[name='months']");

		// If any of our search boxes are filled in we consider ourself to be in search mode
		isFilteringActive = false;
		activeFilters = {};

		if ( $.trim( $search.val() )) {
			isFilteringActive = true;
			activeFilters.search = $search.val();
		}

		if ( $loglevels.val() && $loglevels.val().length ) {
			isFilteringActive = true;
			activeFilters.loglevels = $loglevels.val();
		}

		if ( $messages.val() && $messages.val().length ) {
			isFilteringActive = true;
			activeFilters.messages = $messages.val();
		}

		if ( $.trim( $user.val() )) {
			isFilteringActive = true;
			activeFilters.user = $user.val();
		}

		if ( $months.val() && $months.val().length ) {
			isFilteringActive = true;
			activeFilters.months = $months.val();
		}

		//console.log( "filtering is active:", isFilteringActive );
		// console.log($search.val(), $loglevels.val(), $loggers.val(), $user.val(), $months.val());

		// Reload the log rows collection
		simple_history.logRowsCollection.reload();

	}

	function addFetchListener() {

		$(document).on("SimpleHistory:mainViewInitBeforeLoadRows", function() {

			// Modify query string parameters before the log rows collection fetches/syncs
			simple_history.logRowsCollection.on("before_fetch", modifyFetchData);

		});

		// Alter api args used by new log rows notifier
		$(document).on("SimpleHistory:NewRowsNotifier:apiArgs", modifyNewRowsNotifierApiArgs);


	}

	function modifyNewRowsNotifierApiArgs(e, apiArgs) {

		if (isFilteringActive) {

			apiArgs = _.extend(apiArgs, activeFilters);

		}

	}

	function modifyFetchData(collection, url_data) {

		if (isFilteringActive) {

			url_data = _.extend(url_data, activeFilters);

		}

	}

	function enhanceSelects() {

		$elms.filter_user.select2({
			minimumInputLength: 2,
			allowClear: true,
			placeholder: "All users",
			ajax: {
				url: ajaxurl,
				dataType: "json",
				cache: true,
				data: function (term, page) {
					return {
						q: term, // search term
						page_limit: 10,
						action: "simple_history_filters_search_user"
					};
				},
				results: function (data, page) { // parse the results into the format expected by Select2.
					// since we are using custom formatting functions we do not need to alter remote JSON data
					return data.data;
				}
			},
			formatResult: formatUsers,
			formatSelection: formatUsers,
			escapeMarkup: function(m) { return m; }
		});

		$(".SimpleHistory__filters__filter--logger").select2({
		});

		$(".SimpleHistory__filters__filter--date").select2({
			//width: "element"
		});

		$(".SimpleHistory__filters__filter--loglevel").select2({
			formatResult: formatLoglevel,
			formatSelection: formatLoglevel,
		    escapeMarkup: function(m) { return m; }
		});

	}

	function formatUsers(userdata) {

		var html = "";
		html += "<div class='SimpleHistory__filters__userfilter__gravatar'>";
		html += userdata.gravatar;
		html += "</div>";
		html += "<div class='SimpleHistory__filters__userfilter__primary'>";
		html += userdata.user_email;
		html += "</div>";
		html += "<div class='SimpleHistory__filters__userfilter__secondary'>";
		html += userdata.user_login;
		html += "</div>";
		return html;

	}

	function formatLoglevel(loglevel) {

		var originalOption = loglevel.element;
		var $originalOption = $(originalOption);
		var color = $originalOption.data("color");

		var html = "<span style=\"border-radius: 50%; border: 1px solid rgba(0,0,0,.1); margin-right: 5px; width: .75em; height: .75em; line-height: 1; display: inline-block; background-color: " + $originalOption.data('color') + "; '\"></span>" + loglevel.text;
		return html;

	}

	return {
		init: init,
		onDomReadyInit: onDomReadyInit
	};

})(jQuery);

SimpleHistoryFilterDropin.init();

jQuery(document).ready(function() {
	SimpleHistoryFilterDropin.onDomReadyInit();
});
