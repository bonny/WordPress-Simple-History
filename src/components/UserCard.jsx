import { Button, Icon, Popover, Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { close, external, people, wordpress } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { humanTimeDiff } from '@wordpress/date';
import { getTrackingUrl } from '../functions';

// Only one user card open at a time.
let closeActiveUserCard = null;

// Cache initiator card API responses keyed by type.
const initiatorCardCache = {};

// Terminal prompt icon for WP-CLI (no suitable icon in @wordpress/icons).
const terminalPrompt = (
	<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" aria-hidden="true">
		<path d="M6 7l5 5-5 5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
		<path d="M13 17h5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
	</svg>
);

/**
 * Get a display label for a role.
 *
 * @param {string} role Role slug.
 * @return {string} Capitalized role name.
 */
function formatRole( role ) {
	return role.charAt( 0 ).toUpperCase() + role.slice( 1 );
}

/**
 * Render a detail value based on its type.
 *
 * @param {Object} detail Detail item with key, label, value, and optional type.
 * @return {string|Element} Rendered value.
 */
function renderDetailValue( detail ) {
	if ( detail.type === 'date' ) {
		return humanTimeDiff( detail.value );
	}
	return detail.value;
}

/**
 * Premium link helper.
 *
 * @return {string} Premium URL with tracking.
 */
function getPremiumUrl() {
	return getTrackingUrl(
		'https://simple-history.com/add-ons/premium/',
		'premium_user_card'
	);
}

/**
 * Option A: Blurred placeholder values.
 * Shows premium items with blurred fake values to hint at what you'd get.
 */
function PremiumTeaserBlurred() {
	return (
		<div
			className="sh-UserCard__premiumTeaser sh-UserCard__premiumTeaser--blurred"
			role="group"
			aria-label={ __( 'Premium features', 'simple-history' ) }
		>
			<a
				href={ getPremiumUrl() }
				className="sh-UserCard__blurredPreview"
				target="_blank"
				rel="noopener noreferrer"
			>
				<ul className="sh-UserCard__meta" aria-hidden="true">
					<li className="sh-UserCard__detail sh-UserCard__detail--blurred">
						{ __( 'Logged in', 'simple-history' ) }
						{ ' ' }
						<span className="sh-UserCard__blurredValue">
							{ '3' }
						</span>
						{ ' ' }
						{ __( 'hours ago', 'simple-history' ) }
					</li>
					<li className="sh-UserCard__detail sh-UserCard__detail--blurred">
						{ __( 'Last activity', 'simple-history' ) }
						{ ' ' }
						<span className="sh-UserCard__blurredValue">
							{ '12' }
						</span>
						{ ' ' }
						{ __( 'minutes ago', 'simple-history' ) }
					</li>
				</ul>
				<div className="sh-UserCard__stats" aria-hidden="true">
					<span>
						<span className="sh-UserCard__statValue sh-UserCard__blurredValue">
							{ '8' }
						</span>
						{ ' ' }
						{ __( 'events today', 'simple-history' ) }
					</span>
					<span className="sh-UserCard__statSeparator">
						{ ' · ' }
					</span>
					<span>
						<span className="sh-UserCard__statValue sh-UserCard__blurredValue">
							{ '34' }
						</span>
						{ ' ' }
						{ __( 'events last 7 days', 'simple-history' ) }
					</span>
				</div>
				<span className="sh-UserCard__blurredAction" aria-hidden="true">
					<Icon icon={ external } size={ 16 } />
					{ __( 'View all user activity', 'simple-history' ) }
				</span>
				<span className="sh-UserCard__premiumBadge">
					{ __( 'Available with Premium', 'simple-history' ) }
				</span>
			</a>
		</div>
	);
}

/**
 * Card content for a WordPress user.
 *
 * Uses the data-driven `details` and `actions` arrays from the REST API,
 * so add-ons can extend the card via server-side filters.
 *
 * @param {Object}  props
 * @param {Object}  props.event     The event object.
 * @param {Object}  props.cardData  Data from the REST API (or null).
 * @param {boolean} props.isLoading Whether API data is loading.
 */
function WPUserCardContent( { event, cardData, isLoading } ) {
	const { initiator_data: initiatorData } = event;

	const displayName =
		cardData?.display_name ||
		initiatorData.user_display_name ||
		initiatorData.user_login;
	const email = cardData?.user_email || initiatorData.user_email;
	const avatarUrl = cardData?.avatar_url || initiatorData.user_avatar_url;
	const roles = cardData?.roles;
	const hasPremium = cardData?.has_premium_add_on;

	const allDetails = cardData?.details || [];
	const textDetails = allDetails.filter( ( d ) => d.type !== 'stat' );
	const statDetails = allDetails.filter( ( d ) => d.type === 'stat' );
	const actions = cardData?.actions || [];

	return (
		<div className="sh-UserCard__content">
			<div className="sh-UserCard__identity">
				{ avatarUrl && (
					<img
						className="sh-UserCard__avatar"
						src={ avatarUrl }
						alt=""
					/>
				) }
				<div className="sh-UserCard__info">
					<h4 className="sh-UserCard__name">
						{ displayName }
					</h4>
					<ul className="sh-UserCard__meta">
						{ roles && roles.length > 0 && (
							<li className="sh-UserCard__role">
								{ roles.map( formatRole ).join( ', ' ) }
							</li>
						) }
						{ email && (
							<li>
								<a
									href={ `mailto:${ email }` }
									className="sh-UserCard__email"
								>
									{ email }
								</a>
							</li>
						) }
						{ isLoading && (
							<li className="sh-UserCard__loading">
								<Spinner />
							</li>
						) }
						{ ! isLoading &&
							textDetails.map( ( detail ) => (
								<li
									key={ detail.key }
									className="sh-UserCard__detail"
								>
									{ detail.label
										? sprintf(
												'%s %s',
												detail.label,
												renderDetailValue( detail )
										  )
										: renderDetailValue( detail ) }
								</li>
							) ) }
					</ul>
				</div>
			</div>

			{ ! isLoading && statDetails.length > 0 && (
				<div className="sh-UserCard__stats">
					{ statDetails.map( ( stat, index ) => (
						<span key={ stat.key }>
							{ index > 0 && (
								<span className="sh-UserCard__statSeparator">
									{ ' · ' }
								</span>
							) }
							<span className="sh-UserCard__statValue">
								{ stat.value }
							</span>
							{ ' ' }
							{ sprintf(
								_n(
									'event %s',
									'events %s',
									Number( stat.value ),
									'simple-history'
								),
								stat.label.toLowerCase()
							) }
						</span>
					) ) }
				</div>
			) }

			{ ! isLoading && cardData && ! hasPremium && (
				<PremiumTeaserBlurred />
			) }

			{ actions.length > 0 && (
				<nav
					className="sh-UserCard__actions"
					aria-label={ __( 'User actions', 'simple-history' ) }
				>
					<ul>
						{ actions.map( ( action ) => (
							<li key={ action.key }>
								<a
									href={ action.url }
									className="sh-UserCard__actionLink"
								>
									<Icon
										icon={
											action.key === 'view_profile'
												? people
												: external
										}
										size={ 16 }
									/>
									{ action.label }
								</a>
							</li>
						) ) }
					</ul>
				</nav>
			) }
		</div>
	);
}

/**
 * Card content for non-WP-user initiators (web_user, wp_cli, wp, other).
 *
 * Uses the data-driven `actions` array from the REST API,
 * so add-ons can extend the card via server-side filters.
 *
 * @param {Object}  props
 * @param {Object}  props.event    The event object.
 * @param {Object}  props.cardData Data from the REST API (or null).
 * @param {boolean} props.isLoading Whether API data is loading.
 */
function NonUserCardContent( { event, cardData, isLoading } ) {
	const { initiator, initiator_data: initiatorData } = event;

	const hasPremium = cardData?.has_premium_add_on;
	const actions = cardData?.actions || [];

	let label;
	let description;
	let activityLabel;

	switch ( initiator ) {
		case 'web_user':
			label = __( 'Anonymous web user', 'simple-history' );
			description = __(
				'A visitor to your site who was not logged in.',
				'simple-history'
			);
			activityLabel = __(
				'View all anonymous activity',
				'simple-history'
			);
			break;
		case 'wp_cli':
			label = __( 'WP-CLI', 'simple-history' );
			description = __(
				'Action performed via the WP-CLI command line tool.',
				'simple-history'
			);
			activityLabel = __( 'View all WP-CLI activity', 'simple-history' );
			break;
		case 'wp':
			label = __( 'WordPress', 'simple-history' );
			description = __(
				'An automatic action by WordPress, such as a scheduled task or auto-update.',
				'simple-history'
			);
			activityLabel = __(
				'View all WordPress activity',
				'simple-history'
			);
			break;
		case 'other':
			label = __( 'Other', 'simple-history' );
			description = __(
				'Action triggered by a plugin, theme, or external process.',
				'simple-history'
			);
			activityLabel = __(
				'View all activity from other sources',
				'simple-history'
			);
			break;
		default:
			label = initiator;
			description = null;
			activityLabel = null;
	}

	return (
		<div className="sh-UserCard__content">
			<div className="sh-UserCard__identity">
				{ initiator === 'wp' ? (
					<div className="sh-UserCard__avatar sh-UserCard__avatar--placeholder sh-UserCard__avatar--wp">
						<Icon icon={ wordpress } size={ 36 } />
					</div>
				) : initiator === 'wp_cli' ? (
					<div className="sh-UserCard__avatar sh-UserCard__avatar--placeholder sh-UserCard__avatar--cli">
						{ terminalPrompt }
					</div>
				) : initiatorData?.user_avatar_url ? (
					<img
						className="sh-UserCard__avatar"
						src={ initiatorData.user_avatar_url }
						alt=""
					/>
				) : (
					<div className="sh-UserCard__avatar sh-UserCard__avatar--placeholder" />
				) }
				<div className="sh-UserCard__info">
					<h4 className="sh-UserCard__name">{ label }</h4>
					{ description && (
						<p className="sh-UserCard__description">
							{ description }
						</p>
					) }
					{ isLoading && (
						<Spinner />
					) }
				</div>
			</div>
			{ actions.length > 0 && (
				<nav
					className="sh-UserCard__actions"
					aria-label={ __( 'User actions', 'simple-history' ) }
				>
					<ul>
						{ actions.map( ( action ) => (
							<li key={ action.key }>
								<a
									href={ action.url }
									className="sh-UserCard__actionLink"
								>
									<Icon icon={ external } size={ 16 } />
									{ action.label }
								</a>
							</li>
						) ) }
					</ul>
				</nav>
			) }
			{ ! isLoading && cardData && activityLabel && ! hasPremium && (
				<div className="sh-UserCard__premiumTeaser sh-UserCard__premiumTeaser--blurred">
					<a
						href={ getPremiumUrl() }
						className="sh-UserCard__blurredPreview"
						target="_blank"
						rel="noopener noreferrer"
					>
						<span className="sh-UserCard__blurredAction">
							<Icon icon={ external } size={ 16 } />
							{ activityLabel }
						</span>
						<span className="sh-UserCard__premiumBadge">
							{ __( 'Available with Premium', 'simple-history' ) }
						</span>
					</a>
				</div>
			) }
		</div>
	);
}

/**
 * Wraps children (avatar or username) in a clickable element that opens a user card popover.
 *
 * @param {Object} props
 * @param {Object} props.event    The event object.
 * @param {Object} props.children The content to make clickable (avatar image or username text).
 */
export function UserCard( { event, children } ) {
	const [ showPopover, setShowPopover ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ cardData, setCardData ] = useState( null );
	const buttonRef = useRef( null );

	const isWPUser = event.initiator === 'wp_user';
	const userId = event.initiator_data?.user_id;

	// Close on Escape key.
	useEffect( () => {
		if ( ! showPopover ) {
			return;
		}

		const handleKeyDown = ( keyEvt ) => {
			if ( keyEvt.key === 'Escape' ) {
				setShowPopover( false );
				buttonRef.current?.focus();
			}
		};

		document.addEventListener( 'keydown', handleKeyDown );
		return () => document.removeEventListener( 'keydown', handleKeyDown );
	}, [ showPopover ] );

	const handleClick = ( clickEvt ) => {
		// Ignore clicks inside the popover.
		if ( clickEvt.target.closest( '.sh-UserCard__popover' ) ) {
			return;
		}

		if ( showPopover ) {
			setShowPopover( false );
			return;
		}

		// Close any other open user card.
		if ( closeActiveUserCard ) {
			closeActiveUserCard();
		}
		closeActiveUserCard = () => setShowPopover( false );

		setShowPopover( true );

		// Don't refetch if we already have data.
		if ( cardData ) {
			return;
		}

		// Determine the API path based on initiator type.
		let apiPath;
		if ( isWPUser ) {
			if ( ! userId ) {
				return;
			}
			apiPath = `/simple-history/v1/users/${ userId }/card`;
		} else {
			// Use cached response for non-user initiators.
			if ( initiatorCardCache[ event.initiator ] ) {
				setCardData( initiatorCardCache[ event.initiator ] );
				return;
			}
			apiPath = `/simple-history/v1/initiators/${ event.initiator }/card`;
		}

		setIsLoading( true );

		apiFetch( { path: apiPath } )
			.then( ( data ) => {
				if ( ! isWPUser ) {
					initiatorCardCache[ event.initiator ] = data;
				}
				setCardData( data );
				setIsLoading( false );
			} )
			.catch( () => {
				setIsLoading( false );
			} );
	};

	return (
		<span style={ { position: 'relative', display: 'inline-block' } }>
			<Button
				ref={ buttonRef }
				onClick={ handleClick }
				variant="link"
				className="sh-UserCard__trigger"
			>
				{ children }
			</Button>

			{ showPopover && (
				<Popover
					anchorRef={ buttonRef }
					noArrow={ false }
					offset={ 10 }
					placement="top"
					animate={ false }
					shift={ true }
					className="sh-UserCard__popover"
					onFocusOutside={ () => setShowPopover( false ) }
				>
					<div className="sh-UserCard">
						<Button
							icon={ close }
							iconSize={ 20 }
							size="small"
							onClick={ () => setShowPopover( false ) }
							label={ __( 'Close', 'simple-history' ) }
							className="sh-UserCard__close"
						/>
						{ isWPUser ? (
							<WPUserCardContent
								event={ event }
								cardData={ cardData }
								isLoading={ isLoading }
							/>
						) : (
							<NonUserCardContent
								event={ event }
								cardData={ cardData }
								isLoading={ isLoading }
							/>
						) }
					</div>
				</Popover>
			) }
		</span>
	);
}
