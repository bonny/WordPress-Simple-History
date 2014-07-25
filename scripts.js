
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

	};

	var LogRowsCollection = Backbone.Collection.extend({

		initialize: function() {

			this.url = api_base_url + "&type=overview&format=html&posts_per_page=20";

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

			return arrRows;
		}

	});

	var OccasionsLogRowsCollection = Backbone.Collection.extend({

		initialize: function(models, options) {

			console.log("init OccasionsLogRowsCollection this", this, options);

			this.url = api_base_url + "&type=occasions&format=html";

			this.fetch({
				reset: true,
				data: {
					logRowID: options.logRowID,
					occasionsID: options.occasionsID,
					occasionsCount: options.occasionsCount
				}
			});

		},

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

			return arrRows;
		}

	});

	var OccasionsView = Backbone.View.extend({

		initialize: function() {

			var logRowID = this.attributes.logRow.data("rowId");
			var occasionsCount = this.attributes.logRow.data("occasionsCount");
			var occasionsID = this.attributes.logRow.data("occasionsId");
			
			this.attributes.logRow.addClass("simple-history-logitem--occasionsOpening");
			
			this.logRows = new OccasionsLogRowsCollection([], {
				logRow: this.attributes.logRow,
				logRowID: logRowID,
				occasionsID: occasionsID,
				occasionsCount: occasionsCount
			});

			this.logRows.on("reset", this.render, this);

			// Trigger event for plugins
			this.logRows.on("reset", function() {
				$(document).trigger("SimpleHistory:occasionsLoaded");
			}, this);

		},

		render: function() {
			
			var $html = $([]);
			
			this.logRows.each(function(model) {
				var $li = $(model.get("html"));
				$li.addClass("simple-history-logitem--occasion");
				$html = $html.add($li);
			});

			this.$el.html($html);
			
			this.attributes.logRow.removeClass("simple-history-logitem--occasionsOpening").addClass("simple-history-logitem--occasionsOpened");

			this.$el.addClass("haveOccasionsAdded");

		}

	});

	var DetailsModel = Backbone.Model.extend({
		url: api_base_url + "&type=single&format=html"
	});

	/**
	 * DetailsView is a modal popup thingie with all info about a LogRow
	 */
	var DetailsView = Backbone.View.extend({

		initialize: function(attributes) {

			this.model.fetch({
				data: {
					id: this.model.get("id")
				}
			});

			this.template = $("#tmpl-simple-history-logitems-modal").html();
			this.show();
			
			this.listenTo(this.model, "change", this.render);

			// also close on esc
			var view = this;
			$(document).on("keydown.simplehistory.modal", function(e) {
				if (e.keyCode == 27) {
					view.close();
				}
			});

		},

		events: {
			"click .SimpleHistory-modal__background": "close",
			"click .SimpleHistory-modal__contentClose": "close"
		},

		show: function() {

			var $modalEl = $(".SimpleHistory-modal");
			
			if (!$modalEl.length) {
				$modalEl = $(this.template);
				$modalEl.appendTo("body");
			}

			this.setElement($modalEl);
	
			var $modalContentEl = $modalEl.find(".SimpleHistory-modal__content");
			$modalContentEl.addClass("SimpleHistory-modal__content--enter");

			// Force repaint before adding active class
			var offsetHeight = $modalContentEl.get(0).offsetHeight;
			$modalContentEl.addClass("SimpleHistory-modal__content--enter-active");

		},

		close: function() {
			
			var $modalContentEl = this.$el.find(".SimpleHistory-modal__content");
			$modalContentEl.addClass("SimpleHistory-modal__content--leave");
			
			// Force repaint before adding active class
			var offsetHeight = $modalContentEl.get(0).offsetHeight;
			
			$modalContentEl.addClass("SimpleHistory-modal__content--leave-active");
			this.$el.addClass("SimpleHistory-modal__leave-active");

			// Cleanup
			var view = this;
			setTimeout(function() {
				view.$el.remove();
				$(document).off("keyup.simplehistory.modal");
				view.remove();
			}, 400);

		},

		render: function() {
			
			var $modalContentInnerEl = this.$el.find(".SimpleHistory-modal__contentInner");
			var logRowLI = this.model.get("data").log_rows[0];
			$modalContentInnerEl.html(logRowLI);

		}

	});

	var RowsView = Backbone.View.extend({
	
		initialize: function() {
			
			this.collection.on("reset", this.render, this);

			// Trigger event for plugins
			this.collection.on("reset", function() {
				$(document).trigger("SimpleHistory:logLoaded");
			}, this);

		},

		events: {
			"click .simple-history-logitem__occasions a": "showOccasions",
			"click .simple-history-logitem__permalink": "permalink"
		},

		permalink: function(e) {

			e.preventDefault();

			var $target = $(e.target);
			var $logRow = $target.closest(".simple-history-logitem");
			var logRowID = $logRow.data("rowId");
		
			var detailsModel = new DetailsModel({
				id: logRowID
			});

			var detailsView = new DetailsView({
				model: detailsModel,
				attributes: {
					logRow: $logRow
				}
			});

		},

		showOccasions: function(e) {

			e.preventDefault();

			var $target = $(e.target);
			var $logRow = $target.closest(".simple-history-logitem");	
			var $occasionsElm = $("<li class='simple-history-logitem__occasionsItemsWrap'><ul class='simple-history-logitem__occasionsItems'/></li>");
			
			$logRow.after($occasionsElm);

			this.occasionsView = new OccasionsView({
				el: $occasionsElm.find(".simple-history-logitem__occasionsItems"),
				attributes: {
					logRow: $logRow
				}
			});

		},

		render: function() {

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

			this.logRouter = new LogRouter();
			Backbone.history.start();


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

	var LogRouter = Backbone.Router.extend({

		routes: {
			"help": "help"
		},

		help: function() {
			console.log("help");
		}

	});

	var mainView = new MainView();

	return mainView;

})(jQuery);
