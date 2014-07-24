
/*
V2 begins here
*/
var simple_history2 = (function($) {

	var api_base_url = window.ajaxurl + "?action=simple_history_api";

	var debug = function(what) {

		if (typeof what == "object") {
		
			var newWhat = "";
		
			_.each(what, function(val, key) {
				newWhat += key + ": " + val + "\n";
			});
		
			what = newWhat;
		
		}

		$(".simple-history-logitems-debug").append("<br>" + what);

	}

	var LogRowsCollection = Backbone.Collection.extend({

		initialize: function() {

			this.url = api_base_url + "&type=overview&format=html&posts_per_page=5";

			this.fetch({
				reset: true,
				data: {
					paged: 1
				}
			});

		},

		// Turn wp json respsonse into backbone format
		parse: function(resp, xhr) {

			this.api_args = resp.data.api_args;
			this.max_id = resp.data.max_id;
			this.min_id = resp.data.min_id;
			this.pages_count = resp.data.pages_count;
			this.total_row_count = resp.data.total_row_count;
			this.page_rows_from = resp.data.page_rows_from;
			this.page_rows_to = resp.data.page_rows_to;

			var arrRows = [];
			_.each(resp.data.log_rows, function(row) {
				arrRows.push({
					html: row
				});
			});
			
			// debug("Parsed fetch response");
			// debug( _.omit(resp.data, ["log_rows", "api_args"]) );
			// debug("api args");
			// debug( this.api_args );

			return arrRows;
		}

	});

	var RowsView = Backbone.View.extend({
	
		initialize: function() {
			
			this.collection.on("reset", this.render, this);

		},

		render: function() {

			console.log("Render RowsView");

			var html = "";
			this.collection.each(function(model) {
				html += model.get("html");
			});
			
			this.$el.html( html );

		}

	});

	var PaginationView = Backbone.View.extend({

		initialize: function() {
			
			this.template = $("#tmpl-simple-history-logitems-pagination").html();

			$(document).keydown({ view: this }, this.keyboardNav);

			this.collection.on("reset", this.render, this);

		},

		events: {
			"click .SimpleHistoryPaginationLink": "navigateArrow",
			"keyup .SimpleHistoryPaginationCurrentPage": "navigateToPage",
			"keydown": "keydown"
		},

		keyboardNav: function(e) {

			var paged;

			if (e.keyCode == 37) {
				// prev page
				paged = +e.data.view.collection.api_args.paged - 1;
			} else if (e.keyCode == 39) {
				// next page
				paged = +e.data.view.collection.api_args.paged + 1;
			}

			if (paged) {
				e.data.view.fetchPage(paged);
			}

		},

		navigateToPage: function(e) {

			// keycode 13 = enter
			if (e.keyCode == 13) {
				
				var $target = $(e.target);
				this.fetchPage( parseInt( $target.val() ) );

			}

		},

		navigateArrow: function(e) {
			
			e.preventDefault();
			var $target = $(e.target);

			// if link has class disabled then don't nav away
			if ($target.is(".disabled")) {
				return;
			}

			// direction = first|prev|next|last
			var direction = $target.data("direction");

			var paged;
			switch (direction) {

				case "first":
					paged = 1;
					break;

				case "last":
					paged = this.collection.pages_count;
					break;

				case "prev":
					paged = +this.collection.api_args.paged - 1;
					break;

				case "next":
					paged = +this.collection.api_args.paged + 1;
					break;

			}

			this.fetchPage(paged);
			
		},

		fetchPage: function(paged) {

			$("html").addClass("SimpleHistory-isLoadingPage");

			// nav = fetch collection items again
			this.collection.fetch({
				reset: true,
				data: {
					paged: paged
				},
				success: function() {
					$("html").removeClass("SimpleHistory-isLoadingPage");
				}
			});

			$("html, body").animate({
				scrollTop: 0
			}, 350);


		},

		render: function() {

			var compiled = _.template(this.template);
			
			this.$el.html( compiled({
				min_id: this.collection.min_id,
				max_id: this.collection.max_id,
				pages_count: this.collection.pages_count,
				total_row_count: this.collection.total_row_count,
				page_rows_from: this.collection.page_rows_from,
				page_rows_to: this.collection.page_rows_to,
				api_args: this.collection.api_args,
				strings: simple_history_script_vars.pagination
			}) );

		}

	});

	var MainView = Backbone.View.extend({
		
		el: ".simple-history-gui",

		initialize: function() {

			this.addNeededElements();

			this.logRowsCollection = new LogRowsCollection;
			
			this.rowsView = new RowsView({
				el: this.$el.find(".simple-history-logitems"),
				collection: this.logRowsCollection
			});

			this.paginationView = new PaginationView({
				el: this.$el.find(".simple-history-logitems-pagination"),
				collection: this.logRowsCollection
			});
				
			this.render();

		},

		/**
		 * Add the elements needed for the GUI
		 */
		addNeededElements: function() {

			var html = ' \
				<div class="simple-history-logitems-wrap"> \
					<div class="simple-history-logitems-pagination"></div> \
					<ul class="simple-history-logitems"></ul> \
					<div class="simple-history-logitems-pagination"></div> \
				</div> \
				<div class="simple-history-filters"></div> \
				<div class="simple-history-logitems-debug"></div> \
			';

			this.$el.html( html );

		},

		render: function() {

			//console.log(this.logRows);

		}

	});

	// The view with the log rows
	var LogRowsView = Backbone.View.extend({

	});

	var logRowsView = new LogRowsView();

	var mainView = new MainView();

})(jQuery);

/* end v2 */



/**
 * Object for Simple History
 */
var simple_history = (function($) {

	var elms = {};

	function init() {

		// Only add JS things if Simple History exists on page
		if ( ! $("div.simple-history-ol-wrapper").length && pagenow != 'settings_page_simple_history_settings_menu_slug' ) {
			return;
		}

		// setup elements
		elms.wrap = $(".simple-history-wrap");
		elms.ol_wrapper = elms.wrap.find(".simple-history-ol-wrapper");

		// so wrapper does not collapse when loading new items
		elms.ol_wrapper.css("max-height", elms.ol_wrapper.height() );

		addListeners();

		elms.wrap.addClass("simple-history-is-ready simple-history-has-items");

	}

	function make_wrapper_expandable() {
		elms.ol_wrapper.css("max-height", "1000px");
	}

	/**
	 * Reload history, starting at page 1
	 */
	function reload(e) {

		e.preventDefault();
		jQuery(".simple-history-filter input[type='button']").trigger("click", [ {} ]);

	}

	function keyboardNav(e) {
				
		var link_to_click = null;

		if (e.keyCode == 37) {
			link_to_click = ".prev-page";
		} else if (e.keyCode == 39) {
			link_to_click = ".next-page";
		}

		if (link_to_click) {
			$(".simple-history-tablenav").find(link_to_click).trigger("click");
		}

	}

	/**
	 * Add listeners to enable keyboard navigation and to show/hide things
	 */
	function addListeners() {

		/*
			Character codes:
			37 - left
			38 - up
			39 - right
			40 - down
		*/

		// Reload history when clicking reload-button
		$(document).on("click", ".simple-fields-reload", reload);
		
		// Enable keyboard navigation if we are on Simple Historys own page
		if ( $(".dashboard_page_simple_history_page").length ) {
			
			$(document).keydown(keyboardNav);

		}

		// show occasions
		$(document).on("click", "a.simple-history-occasion-show", function(e) {

			$(this).closest("li").find("ul.simple-history-occasions").toggle();

			make_wrapper_expandable();

			e.preventDefault();

		});

		// show details for main entry
		$(document).on("click", ".simple-history-item-description-toggler", function(e) {
			e.preventDefault();
			var self = $(this);
			make_wrapper_expandable();
			self.closest("li").toggleClass("simple-history-item-description-wrap-is-open");
		});

		// show details for occasions
		$(document).on("click", ".simple-history-occasions-details-toggle", function(e) {
			e.preventDefault();
			var self = $(this);
			make_wrapper_expandable();
			self.closest("li").toggleClass("simple-history-occasions-one-description-is-open");
		});

		// Add a confirm-prompt to the clear log-button in settings
		$(".js-SimpleHistory-Settings-ClearLog").on("click", function(e) {
			if ( ! confirm(simple_history_script_vars.settingsConfirmClearLog) ) {
				e.preventDefault();
			}
		});


	} // function

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
		"init": init,
		"get_selected_filters": get_selected_filters
	};

})(jQuery);

jQuery(function() {
	simple_history.init();
});


// the current page
var simple_history_current_page = 0,
	simple_history_jqXHR = null;

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

/**
 * Load page with history items when click on seach on when selecting someting in the dropdowns
 */
jQuery(document).on("click change", "select.simple-history-filter, .simple-history-filter a, .simple-history-filter input[type='button'], .simple-history-tablenav a", function(e, extraParams) {

	var $t = jQuery(this),
		$ol = jQuery("ol.simple-history"),
		$wrapper = jQuery(".simple-history-ol-wrapper"),
		num_added = $ol.find("> li").length,
		search = jQuery("p.simple-history-filter-search input[type='text']").val(),
		$target = jQuery(e.target),
		$target_link = $target.closest("a"),
		$tablenav = jQuery("div.simple-history-tablenav"),
		$current_page = $tablenav.find(".current-page"),
		$total_pages = $tablenav.find(".total-pages"),
		$next_page = $tablenav.find(".next-page"),
		$prev_page = $tablenav.find(".prev-page"),
		$first_page = $tablenav.find(".first-page"),
		$last_page = $tablenav.find(".last-page"),
		$displaying_num = $tablenav.find(".displaying-num"),
		filters = simple_history.get_selected_filters(),
		$simple_history_wrap = jQuery(".simple-history-wrap");

	e.preventDefault();

	// If event is of type click and called form dropdown then don't continue (only go on when dropdown is changed)
	if ( "click" === e.type && "SELECT" === e.target.nodeName ) return;
	
	// if target is a child of simple-history-tablenav then this is a click in pagination
	if ($t.closest("div.simple-history-tablenav").length > 0) {
	
		var prev_current_page = simple_history_current_page;

		if ($target_link.hasClass("disabled")) {
			return;
		} else if ($target_link.hasClass("first-page")) {
			simple_history_current_page = 0;
		} else if ($target_link.hasClass("last-page")) {
			simple_history_current_page = parseInt($total_pages.text(), 10) - 1;
		} else if ($target_link.hasClass("prev-page")) {
			simple_history_current_page = simple_history_current_page - 1;
		} else if ($target_link.hasClass("next-page")) {
			simple_history_current_page = simple_history_current_page + 1;
		}
		
		// Don't go before page 0 or after total pages. Could happend if you navigated quickly with keyboard.
		if ( simple_history_current_page < 0 || simple_history_current_page >= $total_pages.text() ) {
			simple_history_current_page = prev_current_page;
			return;
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
	
	$simple_history_wrap.addClass("simple-history-is-loading simple-history-has-items");
	
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

	// If a previous ajax call is ongoing: cancel it
	if (simple_history_jqXHR) {
		simple_history_jqXHR.abort();
	}

	simple_history_jqXHR = jQuery.post(ajaxurl, data, function(data, textStatus, XMLHttpRequest){

		// If no more can be loaded show message about that
		if (data.error == "noMoreItems") {

			jQuery(".simple-history-ol-wrapper").height("auto");
			$simple_history_wrap.removeClass("simple-history-has-items simple-history-is-loading");
			$simple_history_wrap.addClass("simple-history-no-items-found");

			$displaying_num.html(0);
			$total_pages.text(1);

		} else {

			// Items found, add and show
			$simple_history_wrap.removeClass("simple-history-is-loading simple-history-no-items-found");

			// update number of existing items and total pages
			$displaying_num.html(data.filtered_items_total_count_string);
			$total_pages.text(data.filtered_items_total_pages);
				
			$ol.html(data.items_li);

			// set wrapper to the height required to show items
			//$wrapper.height( $ol.height() );
			$wrapper.css( "max-height", $ol.height() );
			$simple_history_wrap.removeClass("simple-history-is-loading");

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

		$wrapper.removeClass("simple-history-is-loading");

	});
	
});

jQuery("ol.simple-history .when").live("mouseover", function() {
	jQuery(this).closest("li").find(".when_detail").fadeIn("fast");
});
jQuery("ol.simple-history .when").live("mouseout", function() {
	jQuery(this).closest("li").find(".when_detail").fadeOut("fast");
});
