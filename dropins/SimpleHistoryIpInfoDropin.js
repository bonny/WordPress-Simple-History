
(function($) {

	var $logItems = $(".SimpleHistoryLogitems");

	$logItems.on("click", ".SimpleHistoryLogitem__anonUserWithIp__theIp", function(e) {

		var $elm = $(this);
		var ipAddress = $elm.closest(".SimpleHistoryLogitem").data("ipAddress");

		if (! ipAddress) {
			return;
		}

		return lookupIpAddress(ipAddress);

	});

	function lookupIpAddress(ipAddress) {

		$.get("http://ipinfo.io/" + ipAddress, onIpAddressLookupkResponse, "jsonp");

		return false;

	}

	function onIpAddressLookupkResponse(d) {

		console.log("got data", d);

	}

})(jQuery);
