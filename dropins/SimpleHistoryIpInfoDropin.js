
(function($) {

	var $logItems = $(".SimpleHistoryLogitems");
	var $popup = $(".SimpleHistoryIpInfoDropin__popup");
	var $popupContent = $popup.find(".SimpleHistoryIpInfoDropin__popupContent");

	var templateLoading = wp.template("simple-history-ipinfodropin-popup-loading");
	var templateLoaded = wp.template("simple-history-ipinfodropin-popup-loaded");

	// Click on link with IP-number
	$logItems.on("click", ".SimpleHistoryLogitem__anonUserWithIp__theIp", function(e) {

		var $elm = $(this);
		var ipAddress = $elm.closest(".SimpleHistoryLogitem").data("ipAddress");

		if (! ipAddress) {
			return;
		}

		// since 24 sept 2016 ipinfo supports ssl/https for all users, so we can enable ipinfo for all
		// https://twitter.com/ipinfoio/status/779374440417103872
		showPopup($elm);

		return lookupIpAddress(ipAddress);


		/*
		// If we are on a HTTPS site we cant use ipinfo because lookups over https require pro account
		// Fallback to plain link
		var isHTTPS = document.location.protocol == "https:";

		if (isHTTPS) {
			
			return true;

		} else {

			showPopup($elm);

			return lookupIpAddress(ipAddress);
		}
		*/

	});

	// Close popup
	$popup.on("click", ".SimpleHistoryIpInfoDropin__popupCloseButton", hidePopup);
	$(window).on("click", maybeHidePopup);
	$(window).on("keyup", maybeHidePopup);
	$(document).on("SimpleHistory:logReloadStart", hidePopup);

	// Position and then show popup.
	// Content is not added yet
	function showPopup($elm) {

		var offset = $elm.offset();

		$popup.css({
			//top: offset.top + $elm.outerHeight(),
			top: offset.top,
			left: offset.left
		});

		$popupContent.html(templateLoading());

		$popup.addClass("is-visible");

	}

	function hidePopup(e) {

		$popup.removeClass("is-visible");

	}

	function maybeHidePopup(e) {

		// Make sure variable and properties exist before trying to work on them
		if (!e || !e.target) {
			return;
		}

		var $target = (e.target);

		// Don't hide if click inside popup
		if ($.contains($popup.get(0), $target) ) {
			return true;
		}

		// If initiated by keyboard but not esc, then don't close
		if (e.originalEvent && e.originalEvent.type == "keyup" && e.originalEvent.keyCode && e.originalEvent.keyCode != 27) {
			return;
		}

		// Else it should be ok to hide
		hidePopup();

	}

	// Init request to lookup address
	function lookupIpAddress(ipAddress) {

		//try {
		
			var ajax = $.get("https://ipinfo.io/" + ipAddress, onIpAddressLookupResponse, "jsonp");

			// If the ajax call fail, for example because of blocked connections using adblocker-similar software
			// err_blocked_by_client
			// ajax.fail(onIpAddressLookupResponseError);
			// I don't manage to get this to work, I can't find any way to detect err_blocked_by_client

			return false;
		//}

		//catch (error) {
		//	console.log("error", error);
		//}

	}

	// Function called when ip adress lookup succeeded
	function onIpAddressLookupResponse(d) {
		
		$popupContent.html(templateLoaded(d));

	}

	/*
	function onIpAddressLookupResponseError(d) {

		console.log("onIpAddressLookupResponseError", d);
		$popupContent.html(templateLoaded(d));

	}
	*/

})(jQuery);
