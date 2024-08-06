import {
	Button,
	ExternalLink,
	Flex,
	Popover,
	__experimentalText as Text,
} from '@wordpress/components';
import {
	createInterpolateElement,
	useEffect,
	useState,
} from '@wordpress/element';
import { __, _n } from '@wordpress/i18n';
import { close } from '@wordpress/icons';
import { EventHeaderItem } from './EventHeaderItem';

const keysAndValues = [
	{
		key: 'hostname',
		label: __( 'Hostname:', 'simple-history' ),
	},
	{
		key: 'org',
		label: __( 'Org:', 'simple-history' ),
	},
	{
		key: 'city',
		label: __( 'City:', 'simple-history' ),
	},
	{
		key: 'region',
		label: __( 'Region:', 'simple-history' ),
	},
	{
		key: 'country',
		label: __( 'Country:', 'simple-history' ),
	},
	{
		key: 'loc',
		label: __( 'Location:', 'simple-history' ),
	},
];

/**
 * Renders a link to an IP address.
 *
 * @param {Object} ipAdressProps
 */
function IPAddressLink( ipAdressProps ) {
	const { header, ipAddress, mapsApiKey, hasExtendedSettingsAddOn } =
		ipAdressProps;
	const [ showPopover, setShowPopover ] = useState( false );
	const [ isLoadingIpInfo, setIsLoadingIpInfo ] = useState( false );
	const [ ipInfoResult, setIpInfoResult ] = useState();

	// The ip adress may be anonymized. In that case we need to change the last ".x" to ".0".
	// This is because the IP address is anonymized by setting the last octet to "x".
	// We need to change that to "0" to make the IP address valid.
	const ipAdressUnanonymized = ipAddress.replace( /\.x$/, '.0' );
	const ipInfoURL = `https://ipinfo.io/${ ipAdressUnanonymized }`;

	/**
	 * Load ip info from ipinfo.io.
	 */
	useEffect( () => {
		async function fetchData() {
			const response = await fetch( ipInfoURL, {
				method: 'GET',
				headers: {
					Accept: 'application/json',
				},
			} );

			const json = await response.json();

			setIpInfoResult( json );
			setIsLoadingIpInfo( false );
		}

		// Bail if we are not loading ip info.
		if ( ! isLoadingIpInfo ) {
			return;
		}

		fetchData();
	}, [ isLoadingIpInfo, ipAddress, ipInfoURL ] );

	/**
	 * Show the popover when the user clicks the IP address.
	 *
	 * @param {MouseEvent} clickEvt
	 */
	const handleClick = ( clickEvt ) => {
		const isClickOnPopoverArea = clickEvt.target.nodeName !== 'BUTTON';

		// Bail if already showing popover and we click inside the popover.
		// Seems like the click event is also trigged when clicking anywhere inside the popover,
		// even though the popover is rendered outside the button.
		if ( isClickOnPopoverArea ) {
			return;
		}

		// If we are already showing the popover, then hide it.
		if ( showPopover ) {
			setShowPopover( false );
			return;
		}

		// Show the popover and start loading ip info.
		setShowPopover( true );
		setIsLoadingIpInfo( true );
	};

	const bogonAddressText = createInterpolateElement(
		__(
			'That IP address does not seem like a public one. It is probably a <a>bogon ip address</a>.',
			'simple-history'
		),
		{
			a: (
				<ExternalLink
					href="https://ipinfo.io/bogon"
					target="_blank"
					rel="noopener noreferrer"
				/>
			),
		}
	);

	const map =
		mapsApiKey && ! ipInfoResult?.bogon && ipInfoResult?.loc ? (
			<tr>
				<td colSpan={ 2 }>
					<a
						href={ `https://www.google.com/maps/place/${ ipInfoResult.loc }/@${ ipInfoResult.loc },6z` }
						target="_blank"
						rel="noopener noreferrer"
					>
						<img
							src={ `https://maps.googleapis.com/maps/api/staticmap?center=${ ipInfoResult.loc }&zoom=7&size=350x100&scale=2&sensor=false&key=${ mapsApiKey }` }
							width="350"
							height="100"
							alt="Google Map"
						/>
					</a>
				</td>
			</tr>
		) : null;

	const upsellText = hasExtendedSettingsAddOn ? null : (
		<>
			<div
				style={ {
					display: 'grid',
					placeItems: 'center',
					width: '100%',
					height: 100,
					// TODO: Path to image.
					backgroundImage:
						'url("/wp-content/plugins/simple-history/assets/images/map-img-blur.jpg")',
					backgroundSize: 'cover',
					padding: '1rem',
				} }
			>
				<Text>
					{ createInterpolateElement(
						__(
							'See the location of the IP adress on a map with the <a>Extended Settings</a> add-on.',
							'simple-history'
						),
						{
							a: (
								<ExternalLink
									href="https://simple-history.com/add-ons/extended-settings/?utm_source=plugin&utm_medium=link&utm_campaign=ipinfo#GoogleMaps"
									target="_blank"
									rel="noopener noreferrer"
								/>
							),
						}
					) }
				</Text>
			</div>
		</>
	);

	const loadedIpInfoText = ipInfoResult ? (
		<>
			{ upsellText }
			<table className="SimpleHistoryIpInfoDropin__ipInfoTable">
				<tbody>
					{ map }
					<tr>
						<td className="SimpleHistoryIpInfoDropin__ipInfoTable__key">
							{ __( 'IP address:', 'simple-history' ) }
						</td>
						<td>{ ipAddress }</td>
					</tr>

					<tr>
						<td className="SimpleHistoryIpInfoDropin__ipInfoTable__key">
							{ __( 'Header:', 'simple-history' ) }
						</td>
						<td>
							<code>{ header }</code>
						</td>
					</tr>

					{ ipInfoResult.bogon ? (
						<tr>
							<td className="SimpleHistoryIpInfoDropin__ipInfoTable__key">
								{ __( 'Error:', 'simple-history' ) }
							</td>
							<td>{ bogonAddressText }</td>
						</tr>
					) : null }

					{ /* Show values from ipinfo.io */ }
					{ keysAndValues.map( ( keyAndValue ) => {
						const { key, label } = keyAndValue;
						const value = ipInfoResult[ key ];

						if ( ! value ) {
							return null;
						}

						return (
							<tr key={ key }>
								<td className="SimpleHistoryIpInfoDropin__ipInfoTable__key">
									{ label }
								</td>
								<td>{ value }</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
			<Text
				align="right"
				isBlock
				variant="muted"
				style={ { marginTop: 10 } }
			>
				{ createInterpolateElement(
					__(
						'IP info provided by <a>ipinfo.io</a>',
						'simple-history'
					),
					{
						a: (
							<ExternalLink
								href="https://ipinfo.io/"
								target="_blank"
								rel="noopener noreferrer"
							/>
						),
					}
				) }
			</Text>
		</>
	) : null;

	return (
		<Button title={ header } onClick={ handleClick } variant="link">
			{ ipAddress }

			{ showPopover ? (
				<Popover
					noArrow={ false }
					offset={ 10 }
					placement="top"
					animate={ true }
					shift={ true }
				>
					<div
						style={ {
							minWidth: 350,
							minHeight: 100,
							padding: 10,
							overflow: 'hidden',
						} }
					>
						<Flex align="start">
							<div>
								{ isLoadingIpInfo ? (
									<p>
										{ __(
											'Getting IP info…',
											'simple-history'
										) }
									</p>
								) : (
									<>{ loadedIpInfoText }</>
								) }
							</div>

							<Button
								icon={ close }
								onClick={ () => setShowPopover( false ) }
							/>
						</Flex>
					</div>
				</Popover>
			) : null }
		</Button>
	);
}

/**
 * Renders a list of IP addresses.
 *
 * @param {Object} props
 */
export function EventIPAddresses( props ) {
	const { event, mapsApiKey, hasExtendedSettingsAddOn } = props;
	const { ip_addresses: ipAddresses } = event;

	if ( ! ipAddresses ) {
		return null;
	}

	const ipAddressesCount = Object.keys( ipAddresses ).length;

	if ( ipAddressesCount === 0 ) {
		return null;
	}

	const ipAddressesLabel = _n(
		'IP address:',
		'IP addresses:',
		ipAddressesCount,
		'simple-history'
	);

	const IPAddressesText = [];
	let loopCount = 0;
	for ( const [ header, ipAddress ] of Object.entries( ipAddresses ) ) {
		IPAddressesText.push(
			<>
				<IPAddressLink
					key={ header }
					header={ header }
					ipAddress={ ipAddress }
					mapsApiKey={ mapsApiKey }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				/>{ ' ' }
				{ /* Add comma to separate IP addresses, but not after the last one */ }
				{ loopCount < ipAddressesCount - 1 ? ', ' : '' }
			</>
		);

		loopCount++;
	}

	return (
		<EventHeaderItem>
			{ ipAddressesLabel } { IPAddressesText }
		</EventHeaderItem>
	);
}
