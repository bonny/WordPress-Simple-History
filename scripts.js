
/**
 * Object for Simple History
 */
var simple_history = (function($) {

	/**
	 * Get currently selected filters
	 * @return object with type, subtype, user_id
	 */
	function get_selected_filters() {

		var obj = {
			type:  $("select.simple-history-filter-type option:selected").data("simple-history-filter-type"),
			subtype: $("select.simple-history-filter-type option:selected").data("simple-history-filter-subtype"),
			user_id: $("select.simple-history-filter-user option:selected").data("simple-history-filter-user-id")
		};

		return obj;

	}

	return {
		"get_selected_filters": get_selected_filters
	};

})(jQuery);


/**
 *  load history items via ajax
 */
var simple_history_current_page = 0;

// search on enter
jQuery(document).on("keyup", ".simple-history-filter-search input[type='text'], .simple-history-tablenav .current-page", function(e) {
	var $target = jQuery(e.target);
	// Key is enter
	var extraParams = {};
	if (e.keyCode == 13) {
		if ($target.hasClass("current-page")) {
			// not search, but go to page
			extraParams.enterType = "goToPage";
		} else {
			extraParams.enterType = "search";
		}
		jQuery(".simple-history-filter input[type='button']").trigger("click", [ extraParams ]);
	}
});

// click on filter-link/change value is filter dropdowns = load new via ajax
// begin at position 0 unless click on pagination then check pagination page
jQuery("select.simple-history-filter, .simple-history-filter a, .simple-history-filter input[type='button'], .simple-history-tablenav a").live("click change", function(e, extraParams) {

	var $t = jQuery(this),
		$ol = jQuery("ol.simple-history"),
		$wrapper = jQuery(".simple-history-ol-wrapper"),
		num_added = $ol.find("> li").length,
		search = jQuery("p.simple-history-filter-search input[type='text']").val(),
		$target = jQuery(e.target),
		$tablenav = jQuery("div.simple-history-tablenav"),
		$current_page = $tablenav.find(".current-page"),
		$total_pages = $tablenav.find(".total-pages"),
		$next_page = $tablenav.find(".next-page"),
		$prev_page = $tablenav.find(".prev-page"),
		$first_page = $tablenav.find(".first-page"),
		$last_page = $tablenav.find(".last-page"),
		$displaying_num = $tablenav.find(".displaying-num"),
		filters = simple_history.get_selected_filters();

	e.preventDefault();
	
	// if target is a child of simple-history-tablenav then this is a click in pagination
	if ($t.closest("div.simple-history-tablenav").length > 0) {

		if ($target.hasClass("disabled")) {
			return;
		} else if ($target.hasClass("first-page")) {
			simple_history_current_page = 0;
		} else if ($target.hasClass("last-page")) {
			simple_history_current_page = parseInt($total_pages.text()-1, 10);
		} else if ($target.hasClass("prev-page")) {
			simple_history_current_page = parseInt(simple_history_current_page-1, 10);
		} else if ($target.hasClass("next-page")) {
			simple_history_current_page = parseInt(simple_history_current_page+1, 10);
		}
			
	} else {

		num_added = 0;

		if (extraParams && extraParams.enterType && extraParams.enterType == "goToPage") {
			// pressed enter on go to page-input
			simple_history_current_page = parseInt($current_page.val(), 10)-1; // -1 because we add one later on. feels kinda wierd, I know.
			if (isNaN(simple_history_current_page)) {
				simple_history_current_page = 0;
			}
		} else {
			// click on filter link, let's load from the beginning
			simple_history_current_page = 0;
			
		}
	}
	
	// so dashboard widget does not collapse when loading new items
	$wrapper.height($wrapper.height());

	$t.closest("ul").find("li").removeClass("selected");
	$t.closest("li").addClass("selected");

	jQuery(".simple-history-load-more").hide("fast");
	jQuery(".simple-history-no-more-items").hide();
	$ol.fadeOut("fast");
	
	// update current page
	$current_page.val(simple_history_current_page+1);
		
	var data = {
		"action": "simple_history_ajax",
		"type": filters.type,
		"subtype" : filters.subtype,
		"user_id": filters.user_id,
		"search": search,
		"num_added": num_added,
		"page": simple_history_current_page
	};

	jQuery.post(ajaxurl, data, function(data, textStatus, XMLHttpRequest){
		
		if (data.error == "noMoreItems") {
			// jQuery(".simple-history-load-more,.simple-history-load-more-loading").hide();
			jQuery(".simple-history-no-more-items").show();
			jQuery(".simple-history-ol-wrapper").height("auto");

			$displaying_num.html(0);
			$total_pages.text(1);
			
			$tablenav.hide();

		} else {

			// update number of existing items and total pages
			$displaying_num.html(data.filtered_items_total_count_string);
			$total_pages.text(data.filtered_items_total_pages);
		
			$tablenav.show();
			
			$ol.html(data.items_li);
			$wrapper.animate({
				height: $ol.height()
			}, "fast", "swing", function() {
				$ol.fadeIn("fast");
				jQuery(".simple-history-ol-wrapper").height("auto");
			});

		}

		// enable/disable next/prev-links
		
		// if we are getting the last page, set next+last to disabled
		if ($total_pages.text() == simple_history_current_page+1) {
			$last_page.addClass("disabled");
			$next_page.addClass("disabled");
		}
	
		// active next + last if there are more than one pages
		if ($total_pages.text() > 1 && $total_pages.text() != simple_history_current_page+1) {
			$last_page.removeClass("disabled");
			$next_page.removeClass("disabled");				
		}
	
		// if we are past page 1 then active prev + first
		if (simple_history_current_page > 0) {
			$prev_page.removeClass("disabled");
			$first_page.removeClass("disabled");
		}
		
		// if we are at first then disable first + prev
		if (simple_history_current_page === 0) {
			$prev_page.addClass("disabled");
			$first_page.addClass("disabled");
		}

	});
	
});

/**
 * Click on load more = load more items via AJAX
 */
jQuery(".simple-history-load-more a, .simple-history-load-more input[type='button']").live("click", function() {

	simple_history_current_page++;

	// the number of new history items to get
	var num_to_get = jQuery(this).prev("select").find(":selected").val();
	
	// the number of added li-items = the number of added history items
	var num_added = jQuery("ol.simple-history > li").length,
		search = jQuery("p.simple-history-filter-search input[type='text']").val();

	jQuery(".simple-history-load-more,.simple-history-load-more-loading").toggle();
	
	var $ol = jQuery("ol.simple-history:last");
	
	var filters = simple_history.get_selected_filters(),
		type = filters.type,
		subtype = filters.subtype,
		user_id = filters.user_id;

	var data = {
		"action": "simple_history_ajax",
		"type": filters.type,
		"user": filters.user_id,
		"page": simple_history_current_page,
		"items": num_to_get,
		"num_added": num_added,
		"search": search
	};
	jQuery.post(ajaxurl, data, function(data, textStatus, XMLHttpRequest){
	
		// if data = simpleHistoryNoMoreItems then no more items found, so hide load-more-link
		if (data == "simpleHistoryNoMoreItems") {
			jQuery(".simple-history-load-more,.simple-history-load-more-loading").hide();
			jQuery(".simple-history-no-more-items").show();
		} else {
			var $new_lis = jQuery(data);
			$new_lis.hide();
			$ol.append($new_lis);
			$new_lis.fadeIn("fast");
			jQuery(".simple-history-load-more,.simple-history-load-more-loading").toggle();
		}

	});
	return false;
});

jQuery("ol.simple-history .when").live("mouseover", function() {
	jQuery(this).closest("li").find(".when_detail").fadeIn("fast");
});
jQuery("ol.simple-history .when").live("mouseout", function() {
	jQuery(this).closest("li").find(".when_detail").fadeOut("fast");
});

// show occasions
jQuery("a.simple-history-occasion-show").live("click", function() {
	jQuery(this).closest("li").find("ul.simple-history-occasions").toggle("fast");
	return false;
});
