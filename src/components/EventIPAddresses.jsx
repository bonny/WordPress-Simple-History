import { _n } from '@wordpress/i18n';

/**
 * Renders a list of IP addresses.
 *
 * @param {Object} props
 */
export function EventIPAddresses( props ) {
	const { event } = props;
	const { ip_addresses: ipAddresses } = event;

	if ( ! ipAddresses ) {
		return null;
	}

	const ipAddressesCount = Object.keys( ipAddresses ).length;

	if ( ipAddressesCount === 0 ) {
		return null;
	}

	const text = _n(
		'IP address:',
		'IP addresses:',
		ipAddressesCount,
		'simple-history'
	);

	const IPAddressesText = [];
	for ( const [ header, ipAddress ] of Object.entries( ipAddresses ) ) {
		// The ipadress may be anonymized. In that case we need to change the last ".x" to ".0".
		// This is because the IP address is anonymized by setting the last octet to "x".
		// We need to change that to "0" to make the IP address valid.
		const ipAdressUnanonymized = ipAddress.replace( /\.x$/, '.0' );
		const ipInfoURL = `https://ipinfo.io/${ ipAdressUnanonymized }`;

		IPAddressesText.push(
			<a
				href={ ipInfoURL }
				title={ header }
				target="_blank"
				rel="noreferrer"
			>
				{ ipAddress }{ ' ' }
			</a>
		);
	}

	return (
		<span className="SimpleHistoryLogitem__inlineDivided">
			{ text } { IPAddressesText }
		</span>
	);
}
