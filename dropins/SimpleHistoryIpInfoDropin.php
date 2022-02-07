<?php

defined( 'ABSPATH' ) || die();

/*
Dropin Name: IP Info
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryIpInfoDropin {
	private $sh;

	public function __construct( $sh ) {

		$this->sh = $sh;

		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'simple_history/admin_footer', array( $this, 'add_js_template' ) );

		add_filter(
			'simple_history/row_header_output/display_ip_address',
			array( $this, 'row_header_display_ip_address_filter' ),
			10,
			2
		);
	}

	/**
	 * Display IP Addresses for login related messages.
	 *
	 * @param bool $bool
	 * @param object $row
	 * @return bool
	 */
	public function row_header_display_ip_address_filter( $bool, $row ) {
		// Bail if log row in not from our logger.
		if ( 'SimpleUserLogger' !== $row->logger ) {
			return $bool;
		}

		// Message keys to show IP Addresses for.
		$arr_keys_to_log = array(
			'user_logged_in',
			'user_login_failed',
			'user_unknown_login_failed',
			'user_unknown_logged_in',
		);

		// Bail if not correct message key.
		if ( ! in_array( $row->context_message_key, $arr_keys_to_log ) ) {
			return $bool;
		}

		return true;
	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url( __FILE__ );

		wp_enqueue_script( 'simple_history_IpInfoDropin', $file_url . 'SimpleHistoryIpInfoDropin.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION, true );

		wp_enqueue_style( 'simple_history_IpInfoDropin', $file_url . 'SimpleHistoryIpInfoDropin.css', null, SIMPLE_HISTORY_VERSION );
	}

	public function add_js_template() {
		?>

		<div class="SimpleHistoryIpInfoDropin__popup">
			<div class="SimpleHistoryIpInfoDropin__popupArrow"></div>
			<div class="SimpleHistoryIpInfoDropin__popupClose"><button class="SimpleHistoryIpInfoDropin__popupCloseButton">×</button></div>
			<div class="SimpleHistoryIpInfoDropin__popupContent"></div>
		</div>

		<script type="text/html" id="tmpl-simple-history-ipinfodropin-popup-loading">
			<p><?php echo esc_html_x( 'Getting IP info ...', 'IP Info Dropin', 'simple-history' ); ?></p>
		</script>

		<script type="text/html" id="tmpl-simple-history-ipinfodropin-popup-error">
			<p><?php echo esc_html_x( 'Could not get info about IP address.', 'IP Info Dropin', 'simple-history' ); ?></p>
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
			<# if ( typeof(data.bogon) != "undefined" ) { #>

				<p><?php echo esc_html_x( 'That IP address does not seem like a public one.', 'IP Info Dropin', 'simple-history' ); ?></p>

			<# } else { #>

				<table class="SimpleHistoryIpInfoDropin__ipInfoTable">

					<tr class="SimpleHistoryIpInfoDropin__ipInfoTable__mapRow">
						<td colspan="2">
							<!--
							<# if ( typeof(data.loc) != "undefined" && data.loc ) { #>
								<a href="https://www.google.com/maps/place/{{ data.loc }}/@{{ data.loc }},6z" target="_blank">
									<img src="https://maps.googleapis.com/maps/api/staticmap?center={{ data.loc }}&zoom=7&size=350x100&sensor=false" width="350" height="100" alt="Google Map">
								</a>
							<# } #>
							-->
						</td>
					</tr>

					<# if ( typeof(data.ip) != "undefined" && data.ip ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'IP address', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.ip }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.hostname) != "undefined" && data.hostname ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'Hostname', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.hostname }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.org) != "undefined" && data.org ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'Network', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.org }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.network) != "undefined" && data.network ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'Network', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.network }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.city) != "undefined" && data.city ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'City', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.city }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.region) != "undefined" && data.region ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'Region', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.region }}
						</td>
					</tr>
					<# } #>

					<# if ( typeof(data.country) != "undefined" && data.country ) { #>
					<tr>
						<td>
							<?php echo esc_html_x( 'Country', 'IP Info Dropin', 'simple-history' ); ?>
						</td>
						<td>
							{{ data.country }}
						</td>
					</tr>
					<# } #>

				</table>

				<p class="SimpleHistoryIpInfoDropin__provider">
					<?php printf( esc_html_x( 'IP info provided by %1$s ipinfo.io %2$s', 'IP Info Dropin', 'simple-history' ), "<a href='https://ipinfo.io/{{ data.ip }}' target='_blank'>", '</a>' ); ?>
				</p>

			<# } #>

		</script>
		<?php
	}
}
