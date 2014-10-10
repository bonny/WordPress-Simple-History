jQuery(function($) {

	$(".SimpleHistory__filters__filter--user").select2({
		minimumInputLength: 2,
		allowClear: true,
		placeholder: "All users",
		ajax: {
			url: ajaxurl,
			dataType: "json",
			data: function (term, page) {
				return {
					q: term, // search term
					page_limit: 10,
					action: "simple_history_filters_search_user"
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				//console.log("resuts", data.data);
				return data.data;
			}
		},
		formatResult: formatUsers,
		formatSelection: formatUsers,
		escapeMarkup: function(m) { return m; }
	});

	function formatUsers(userdata) {
		
		console.log("userdata", userdata);
		
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


	$(".SimpleHistory__filters__filter--logger").select2({
	});

	$(".SimpleHistory__filters__filter--date").select2({
	});

	$(".SimpleHistory__filters__filter--loglevel").select2({
		formatResult: format,
		formatSelection: format,
	    escapeMarkup: function(m) { return m; }
	});


	function format(loglevel) {
		
		var originalOption = loglevel.element;
		var $originalOption = $(originalOption);
		var color = $originalOption.data("color");
		console.log("color", color);
		
		var html = "<span style=\"border: 1px solid rgba(0,0,0,.1); margin-right: 10px; width: 1em; height: 1em; line-height: 1; display: inline-block; background-color: " + $originalOption.data('color') + "; '\"></span>" + loglevel.text;
		return html;

	}

});
