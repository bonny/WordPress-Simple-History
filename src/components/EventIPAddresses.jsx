import {
	Button,
	ExternalLink,
	Flex,
	Popover,
	__experimentalText as Text,
} from '@wordpress/components';
import {
	Fragment,
	createInterpolateElement,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n } from '@wordpress/i18n';
import { close } from '@wordpress/icons';
import { EventHeaderItem } from './EventHeaderItem';
import { getTrackingUrl } from '../functions';

const keysAndValues = [
	{
		key: 'hostname',
		label: __( 'Hostname:', 'simple-history' ),
	},
	{
		key: 'org',
		label: __( 'Org:', 'simple-history' ),
		render: ( value ) => {
			const asMatch = value.match( /^(AS\d+)\s+(.+)$/ );
			if ( asMatch ) {
				return (
					<>
						<ExternalLink
							href={ `https://ipinfo.io/${ asMatch[ 1 ] }` }
						>
							{ asMatch[ 1 ] }
						</ExternalLink>{ ' ' }
						{ asMatch[ 2 ] }
					</>
				);
			}
			return value;
		},
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
		render: ( value ) => {
			return (
				<>
					{ value }
					<br />
					<ExternalLink
						href={ `https://www.google.com/maps/place/${ value }` }
					>
						Google Maps
					</ExternalLink>
					{ ' | ' }
					<ExternalLink
						href={ `https://www.openstreetmap.org/?mlat=${
							value.split( ',' )[ 0 ]
						}&mlon=${ value.split( ',' )[ 1 ] }#map=10/${
							value.split( ',' )[ 0 ]
						}/${ value.split( ',' )[ 1 ] }` }
					>
						OpenStreetMap
					</ExternalLink>
				</>
			);
		},
	},
];

// Module-level callback to close the currently open popover.
// Only one IP popover should be visible at a time.
let closeActivePopover = null;

/**
 * Renders a link to an IP address.
 *
 * @param {Object} ipAddressProps
 */
function IPAddressLink( ipAddressProps ) {
	const { header, ipAddress, mapsApiKey, hasPremiumAddOn } = ipAddressProps;
	const [ showPopover, setShowPopover ] = useState( false );
	const [ isLoadingIpInfo, setIsLoadingIpInfo ] = useState( false );
	const [ ipInfoResult, setIpInfoResult ] = useState();
	const buttonRef = useRef( null );

	// The ip address may be anonymized. In that case we need to change the last ".x" to ".0".
	// This is because the IP address is anonymized by setting the last octet to "x".
	// We need to change that to "0" to make the IP address valid.
	const ipAddressUnanonymized = ipAddress.replace( /\.x$/, '.0' );
	const ipInfoURL = `https://ipinfo.io/${ ipAddressUnanonymized }`;

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
		// Seems like the click event is also triggered when clicking anywhere inside the popover,
		// even though the popover is rendered outside the button.
		if ( isClickOnPopoverArea ) {
			return;
		}

		// If we are already showing the popover, then hide it.
		if ( showPopover ) {
			setShowPopover( false );
			return;
		}

		// Close any other open popover before opening this one.
		if ( closeActivePopover ) {
			closeActivePopover();
		}
		closeActivePopover = () => setShowPopover( false );

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
			<a
				href={ `https://www.google.com/maps/place/${ ipInfoResult.loc }/@${ ipInfoResult.loc },6z` }
				target="_blank"
				rel="noopener noreferrer"
			>
				<img
					src={ `https://maps.googleapis.com/maps/api/staticmap?center=${ ipInfoResult.loc }&zoom=7&size=350x150&scale=2&sensor=false&key=${ mapsApiKey }` }
					width="350"
					height="150"
					alt="Google Map"
				/>
			</a>
		) : null;

	const upsellText = hasPremiumAddOn ? null : (
		<>
			<div
				style={ {
					display: 'grid',
					placeItems: 'center',
					width: '100%',
					// More padding on the right to compensate for the close button.
					paddingRight: '40px',
					paddingLeft: '20px',
					height: 100,
					// TODO: Path to image must know about wp path.
					backgroundImage:
						'url("/wp-content/plugins/simple-history/assets/images/map-img-blur.jpg")',
					backgroundSize: 'cover',
				} }
			>
				<Text>
					{ createInterpolateElement(
						__(
							'See the location of the IP address on a map with <a>Simple History Premium</a> add-on.',
							'simple-history'
						),
						{
							a: (
								<ExternalLink
									href={ getTrackingUrl(
										'https://simple-history.com/add-ons/premium/#GoogleMaps',
										'premium_events_ipaddress'
									) }
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

	const loadingIpInfoText = (
		<p
			style={ {
				textAlign: 'center',
				height: 150,
				lineHeight: '150px',
				background: 'var(--sh-color-gray-4)',
				margin: 0,
			} }
		>
			{ __( 'Getting IP info…', 'simple-history' ) }
		</p>
	);

	const handleFilterByIP = ( ip ) => {
		window.dispatchEvent(
			new CustomEvent( 'SimpleHistory:filterByIPAddress', {
				detail: { ipAddress: ip },
			} )
		);
		setShowPopover( false );
	};

	const subnetIP = ipAddress.replace( /\.\d+$/, '.x' );

	const loadedIpInfoText = ipInfoResult ? (
		<>
			{ upsellText }
			{ map }

			<div
				style={ {
					padding: 'var(--sh-spacing-medium)',
				} }
			>
				<Text variant="muted" size="small">
					{ __( 'IP address', 'simple-history' ) }{ ' ' }
					<code
						style={ {
							fontSize: '11px',
						} }
					>
						{ header }
					</code>
				</Text>
				<div
					style={ {
						fontSize: '1.5em',
						fontWeight: 700,
						fontFamily: 'monospace',
						lineHeight: 1.3,
					} }
				>
					{ ipAddress }
				</div>
			</div>

			<div style={ {} }>
				<table className="SimpleHistoryIpInfoDropin__ipInfoTable">
					<tbody>
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
									<td>
										{ keyAndValue.render
											? keyAndValue.render( value )
											: value }
									</td>
								</tr>
							);
						} ) }
						<tr>
							<td className="SimpleHistoryIpInfoDropin__ipInfoTable__key">
								{ __( 'Filter events:', 'simple-history' ) }
							</td>
							<td>
								<Button
									variant="link"
									onClick={ () =>
										handleFilterByIP( ipAddress )
									}
									style={ { fontSize: 'inherit' } }
								>
									{ __( 'This IP', 'simple-history' ) }
								</Button>
								{ ! ipAddress.endsWith( '.x' ) && (
									<>
										{ ' | ' }
										<Button
											variant="link"
											onClick={ () =>
												handleFilterByIP( subnetIP )
											}
											style={ {
												fontSize: 'inherit',
											} }
										>
											{ __(
												'This subnet',
												'simple-history'
											) }
											{ ` (${ subnetIP })` }
										</Button>
									</>
								) }
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<Text
				align="right"
				isBlock
				variant="muted"
				style={ {
					padding: 'var(--sh-spacing-medium)',
				} }
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
		<div style={ { position: 'relative', display: 'inline-block' } }>
			<Button
				ref={ buttonRef }
				title={ header }
				onClick={ handleClick }
				variant="link"
			>
				{ ipAddress }
			</Button>

			{ showPopover ? (
				<Popover
					anchorRef={ buttonRef }
					noArrow={ false }
					offset={ 10 }
					placement="top"
					animate={ false }
					shift={ true }
				>
					<div
						style={ {
							minWidth: 350,
							minHeight: 300,
							overflow: 'hidden',
						} }
					>
						<Button
							icon={ close }
							onClick={ () => setShowPopover( false ) }
							style={ {
								position: 'absolute',
								top: 0,
								right: 0,
							} }
						/>

						<div>
							{ isLoadingIpInfo ? (
								<>{ loadingIpInfoText }</>
							) : (
								<>{ loadedIpInfoText }</>
							) }
						</div>
					</div>
				</Popover>
			) : null }
		</div>
	);
}

/**
 * Renders a list of IP addresses.
 *
 * @param {Object} props
 */
export function EventIPAddresses( props ) {
	const { event, mapsApiKey, hasPremiumAddOn } = props;
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
			<Fragment key={ header }>
				<IPAddressLink
					header={ header }
					ipAddress={ ipAddress }
					mapsApiKey={ mapsApiKey }
					hasPremiumAddOn={ hasPremiumAddOn }
				/>{ ' ' }
				{ /* Add comma to separate IP addresses, but not after the last one */ }
				{ loopCount < ipAddressesCount - 1 ? ', ' : '' }
			</Fragment>
		);

		loopCount++;
	}

	return (
		<EventHeaderItem>
			{ ipAddressesLabel } { IPAddressesText }
		</EventHeaderItem>
	);
}
