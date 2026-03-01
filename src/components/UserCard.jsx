import { Button, ExternalLink, Icon, Popover, Spinner } from '@wordpress/components';
import { createInterpolateElement, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { close, external, people } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import apiFetch from '@wordpress/api-fetch';
import { humanTimeDiff } from '@wordpress/date';
import { getTrackingUrl } from '../functions';

// Only one user card open at a time.
let closeActiveUserCard = null;

/**
 * Get the admin page base URL for Simple History.
 *
 * @return {string} Admin page URL.
 */
function getAdminPageURL() {
	return (
		window.location.origin +
		window.location.pathname.replace( /\/[^/]*$/, '/admin.php' )
	);
}

/**
 * Get the "view all activity" URL for a non-user initiator type.
 *
 * @param {string} initiator The initiator type (wp_cli, wp, web_user, other).
 * @return {string} URL to filter events by this initiator type.
 */
function getViewInitiatorActivityURL( initiator ) {
	const initiatorJsonString = JSON.stringify( [
		{ value: initiator, initiator_key: initiator },
	] );

	return addQueryArgs( getAdminPageURL(), {
		page: 'simple_history_admin_menu_page',
		initiator: initiatorJsonString,
	} );
}

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
 * Premium upsell teaser shown when premium add-on is not active.
 */
function PremiumTeaser() {
	return (
		<div className="sh-UserCard__premiumTeaser">
			{ createInterpolateElement(
				__(
					'Get login history and activity insights with <a>Simple History Premium</a>.',
					'simple-history'
				),
				{
					a: (
						<ExternalLink
							href={ getTrackingUrl(
								'https://simple-history.com/add-ons/premium/',
								'premium_user_card'
							) }
						/>
					),
				}
			) }
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
	const userId = cardData?.user_id || initiatorData.user_id;
	const username = cardData?.user_login || initiatorData.user_login;
	const email = cardData?.user_email || initiatorData.user_email;
	const avatarUrl = cardData?.avatar_url || initiatorData.user_avatar_url;
	const roles = cardData?.roles;
	const hasPremium = cardData?.has_premium_add_on;

	const details = cardData?.details || [];
	const actions = cardData?.actions || [];

	// Show username only when it differs from the display name.
	const showUsername = username && username !== displayName;

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
					<strong className="sh-UserCard__name">
						{ displayName }
					</strong>
					{ roles && roles.length > 0 && (
						<span className="sh-UserCard__role">
							{ roles.map( formatRole ).join( ', ' ) }
						</span>
					) }
					{ email && (
						<a
							href={ `mailto:${ email }` }
							className="sh-UserCard__email"
						>
							{ email }
						</a>
					) }
					{ showUsername && (
						<span className="sh-UserCard__username">
							@{ username }
						</span>
					) }
					{ isLoading && (
						<span className="sh-UserCard__loading">
							<Spinner />
						</span>
					) }
					{ ! isLoading &&
						details.map( ( detail ) => (
							<span
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
							</span>
						) ) }
				</div>
			</div>

			{ actions.length > 0 && (
				<div className="sh-UserCard__actions">
					{ actions.map( ( action ) => (
						<a
							key={ action.key }
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
					) ) }
				</div>
			) }

			{ ! isLoading && cardData && ! hasPremium && <PremiumTeaser /> }
		</div>
	);
}

/**
 * Card content for non-WP-user initiators (web_user, wp_cli, wp, other).
 *
 * @param {Object} props
 * @param {Object} props.event The event object.
 */
function NonUserCardContent( { event } ) {
	const { initiator, initiator_data: initiatorData } = event;

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

	const activityURL = getViewInitiatorActivityURL( initiator );

	return (
		<div className="sh-UserCard__content">
			<div className="sh-UserCard__identity">
				{ initiatorData?.user_avatar_url ? (
					<img
						className="sh-UserCard__avatar"
						src={ initiatorData.user_avatar_url }
						alt=""
					/>
				) : (
					<div className="sh-UserCard__avatar sh-UserCard__avatar--placeholder" />
				) }
				<div className="sh-UserCard__info">
					<strong className="sh-UserCard__name">{ label }</strong>
					{ description && (
						<span className="sh-UserCard__description">
							{ description }
						</span>
					) }
				</div>
			</div>
			{ activityLabel && (
				<div className="sh-UserCard__actions">
					<a href={ activityURL } className="sh-UserCard__actionLink">
						{ activityLabel }
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

		// Fetch enhanced data from REST API for WP users.
		if ( isWPUser && userId && ! cardData ) {
			setIsLoading( true );
			apiFetch( {
				path: `/simple-history/v1/users/${ userId }/card`,
			} )
				.then( ( data ) => {
					setCardData( data );
					setIsLoading( false );
				} )
				.catch( () => {
					// API may not be available (experimental features off).
					setIsLoading( false );
				} );
		}
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
							<NonUserCardContent event={ event } />
						) }
					</div>
				</Popover>
			) }
		</span>
	);
}
