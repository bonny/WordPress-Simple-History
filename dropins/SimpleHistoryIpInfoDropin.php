<?php

/*
Dropin Name: IP Info
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryIpInfoDropin {

	private $sh;

	function __construct($sh) {

		$this->sh = $sh;

		// Since it's not quite done yet, it's for da devs only for now
		if ( ! defined("SIMPLE_HISTORY_DEV") || ! SIMPLE_HISTORY_DEV ) {
			return;
		}

		add_action("simple_history/enqueue_admin_scripts", array($this, "enqueue_admin_scripts"));
		add_action("simple_history/admin_footer", array($this, "add_js_template"));


	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url(__FILE__);
		
		wp_enqueue_script("simple_history_IpInfoDropin", $file_url . "SimpleHistoryIpInfoDropin.js", array("jquery"), SimpleHistory::VERSION, true);

		wp_enqueue_style("simple_history_IpInfoDropin", $file_url . "SimpleHistoryIpInfoDropin.css", null, SimpleHistory::VERSION);

	}

	public function add_js_template() {
		?>

		<div class="SimpleHistoryIpInfoDropin__popup">
			<div class="SimpleHistoryIpInfoDropin__popupArrow"></div>
			<div class="SimpleHistoryIpInfoDropin__popupClose"><button class="SimpleHistoryIpInfoDropin__popupCloseButton">×</button></div>
			<div class="SimpleHistoryIpInfoDropin__popupContent"></div>
		</div>

		<script type="text/html" id="tmpl-simple-history-ipinfodropin-popup-loading">
			<!-- <p>Getting IP info ...</p> -->
		</script>

		<script type="text/html" id="tmpl-simple-history-ipinfodropin-popup-loaded">
			<!--
			{
			  "ip": "8.8.8.8",
			  "hostname": "google-public-dns-a.google.com",
			  "city": "Mountain View",
			  "region": "California",
			  "country": "US",
			  "loc": "37.3860,-122.0838",
			  "org": "AS15169 Google Inc.",
			  "postal": "94035"
			}
			-->
			<% if ( typeof(bogon) != "undefined" ) { %>

				<p><?php _ex("That IP address does not seem like a public one.", "IP Info Dropin", "simple-history"); ?></p>
				
			<% } else { %>
				
				<table class="SimpleHistoryIpInfoDropin__ipInfoTable">

					<tr class="SimpleHistoryIpInfoDropin__ipInfoTable__mapRow">
						<td colspan="2">
							<% if ( typeof(loc) != "undefined" && loc ) { %>
								<a href="https://www.google.com/maps/place/<%= loc %>/@<%= loc %>,6z" target="_blank">
									<img src="https://maps.googleapis.com/maps/api/staticmap?center=<%= loc %>&zoom=7&size=350x100&sensor=false" width="350" height="100" alt="Google Map">
								</a>
							<% } %>
						</td>
					</tr>

					<% if ( typeof(ip) != "undefined" && ip ) { %>
					<tr>
						<td>
							<?php _ex("IP address", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= ip %>
						</td>
					</tr>
					<% } %>

					<% if ( typeof(hostname) != "undefined" && hostname ) { %>
					<tr>
						<td>
							<?php _ex("Hostname", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= hostname %>
						</td>
					</tr>
					<% } %>

					<% if ( typeof(org) != "undefined" && org ) { %>
					<tr>
						<td>
							<?php _ex("Network", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= org %>
						</td>
					</tr>
					<% } %>

					<% if ( typeof(network) != "undefined" && network ) { %>
					<tr>
						<td>
							<?php _ex("Network", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= network %>
						</td>
					</tr>
					<% } %>

					<% if ( typeof(city) != "undefined" && city ) { %>
					<tr>
						<td>
							<?php _ex("City", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= city %>
						</td>
					</tr>
					<% } %>			

					<% if ( typeof(region) != "undefined" && region ) { %>
					<tr>
						<td>
							<?php _ex("Region", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= region %>
						</td>
					</tr>
					<% } %>

					<% if ( typeof(country) != "undefined" && country ) { %>
					<tr>
						<td>
							<?php _ex("Country", "IP Info Dropin", "simple-history"); ?>
						</td>
						<td>
							<%= country %>
						</td>
					</tr>
					<% } %>

					<!--
					<% if ( typeof(loc) != "undefined" ) { %>
					<tr>
						<td>
							loc
						</td>
						<td>
							<%= loc %>
						</td>
					</tr>
					<% } %>
					-->

				</table>

				<p class="SimpleHistoryIpInfoDropin__provider">
					<?php printf( _x('IP info provided by %1$s ipinfo.io %2$s', "IP Info Dropin", "simple-history"), "<a href='http://ipinfo.io/<%= ip %>' target='_blank'>", "</a>" ); ?>
				</p>

			<% } %>

		</script>
		<?php
	}

} // end class

