
(function($) {

	var $logItems = $(".SimpleHistoryLogitems");

	$logItems.on("click", ".SimpleHistoryLogitem__anonUserWithIp", function(e) {

		var $elm = $(this);
		var ipAddress = $elm.data("ipAddress");

		if (! ipAddress) {
			return;
		}

		lookupIpAddress(ipAddress);

	});

	function lookupIpAddress(ipAddress) {

		$.get("http://ipinfo.io/" + ipAddress, onIpAddressLookupkResponse, "jsonp");

	}

	function onIpAddressLookupkResponse(d) {

		console.log("got data", d);

	}

})(jQuery);
