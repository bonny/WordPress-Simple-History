import { Button, Icon, Popover, Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	chartBar,
	close,
	external,
	globe,
	key,
	people,
	seen,
	wordpress,
} from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { humanTimeDiff } from '@wordpress/date';
import { getTrackingUrl } from '../functions';

// Only one user card open at a time.
let closeActiveUserCard = null;

// Cache API responses to avoid duplicate fetches. Entries are stored as
// `{ data, fetchedAt }` and expire after CACHE_TTL_MS so dynamic fields
// (last_event, events_today) don't freeze for the whole SPA session.
const CACHE_TTL_MS = 60_000;
const userCardCache = {};
const initiatorCardCache = {};

function readCache( cache, cacheKey ) {
	const entry = cache[ cacheKey ];
	if ( ! entry ) {
		return null;
	}
	if ( Date.now() - entry.fetchedAt > CACHE_TTL_MS ) {
		delete cache[ cacheKey ];
		return null;
	}
	return entry.data;
}

function writeCache( cache, cacheKey, data ) {
	cache[ cacheKey ] = { data, fetchedAt: Date.now() };
}

// Terminal prompt icon for WP-CLI (no suitable icon in @wordpress/icons).
const terminalPrompt = (
	<svg
		viewBox="0 0 24 24"
		xmlns="http://www.w3.org/2000/svg"
		width="24"
		height="24"
		aria-hidden="true"
	>
		<path
			d="M6 7l5 5-5 5"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
		<path
			d="M13 17h5"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
		/>
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
 * @param {string} [content] utm_content value — lets us tell apart clicks
 *                           from the screenshot vs the CTA vs other surfaces.
 * @return {string} Premium URL with tracking.
 */
function getPremiumUrl( content = '' ) {
	return getTrackingUrl(
		'https://simple-history.com/features/user-card/',
		'premium_user_card',
		'wpadmin',
		'plugin',
		content
	);
}

// Icon mapped per detail key so the meta block reads at a glance.
// Centralized so both card variants render the same way.
const DETAIL_ICONS = {
	last_activity: seen,
	last_login: key,
	last_session: globe,
};

/**
 * Render the "below stats" meta block (Last login / Last event / IP·Browser).
 *
 * Shared between the WP-user card and the non-user initiator card so the two
 * variants stay in sync — adding a new detail key + icon happens in one place.
 *
 * @param {Object} props
 * @param {Array}  props.details textDetails array (everything not type='stat').
 */
function MetaDetailsList( { details } ) {
	if ( details.length === 0 ) {
		return null;
	}

	return (
		<ul className="sh-UserCard__meta sh-UserCard__meta--belowStats">
			{ details.map( ( detail ) => {
				const iconForKey = DETAIL_ICONS[ detail.key ];
				return (
					<li key={ detail.key } className="sh-UserCard__detail">
						{ iconForKey && (
							<Icon
								icon={ iconForKey }
								size={ 14 }
								className="sh-UserCard__detailIcon"
							/>
						) }
						<span>
							{ detail.label ? (
								<>
									{ detail.label }{ ' ' }
									{ renderDetailValue( detail ) }
								</>
							) : (
								renderDetailValue( detail )
							) }
						</span>
					</li>
				);
			} ) }
		</ul>
	);
}

/**
 * Premium upsell block shown inside the user card for free users.
 *
 * Visually walled off (cream background + border + corner badge) so the
 * eye reads it as marketing, not as more card content. Contains an
 * embedded screenshot of the same popup with premium active, a caption
 * listing what premium adds, and a clearly marketing-framed CTA — the
 * label deliberately doesn't mirror any real in-app action so it can't
 * be mistaken for one.
 *
 * Screenshot regen: npm run screenshots:teaser-user-card
 * (see .claude/skills/teaser-screenshots/SKILL.md).
 */
function PremiumTeaserBlurred() {
	const screenshotUrl =
		'/wp-content/plugins/simple-history/assets/images/user-card-with-premium.png';

	return (
		<div
			className="sh-UserCard__premiumTeaser"
			role="group"
			aria-label={ __( 'Premium preview', 'simple-history' ) }
		>
			<div className="sh-UserCard__teaserHeader">
				<span className="sh-Badge sh-Badge--premium">
					{ __( 'Premium', 'simple-history' ) }
				</span>
			</div>

			<figure className="sh-UserCard__teaserScreenshot">
				<a
					href={ getPremiumUrl( 'screenshot_click' ) }
					target="_blank"
					rel="noopener noreferrer"
					className="sh-UserCard__teaserScreenshotLink"
					aria-label={ __(
						'See the premium user card on simple-history.com',
						'simple-history'
					) }
				>
					<img
						src={ screenshotUrl }
						alt={ __(
							'Preview of the user card with Simple History Premium.',
							'simple-history'
						) }
						width={ 640 }
						height={ 580 }
					/>
				</a>
				<figcaption className="sh-UserCard__teaserCaption">
					{ __(
						'With Premium, this card also shows event counts, last login, IP, and browser — plus a direct link to everything they’ve done.',
						'simple-history'
					) }
				</figcaption>
			</figure>

			<a
				href={ getPremiumUrl( 'cta_click' ) }
				className="sh-UserCard__teaserCta"
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __( 'Learn more about Premium', 'simple-history' ) }
				<Icon icon={ external } size={ 16 } />
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
	const userId = initiatorData?.user_id;

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
						{ userId && (
							<span
								className="sh-UserCard__userId"
								aria-label={ sprintf(
									/* translators: %s: user ID number */
									__( 'User ID %s', 'simple-history' ),
									userId
								) }
							>
								{ `#${ userId }` }
							</span>
						) }
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
					</ul>
				</div>
			</div>

			{ isLoading && (
				<div className="sh-UserCard__loading">
					<Spinner />
				</div>
			) }

			{ ! isLoading && statDetails.length > 0 && (
				<div className="sh-UserCard__stats">
					<h5 className="sh-UserCard__statsHeading">
						<Icon
							icon={ chartBar }
							size={ 14 }
							className="sh-UserCard__statsHeadingIcon"
						/>
						{ __( 'Events', 'simple-history' ) }
					</h5>
					{ statDetails.map( ( stat ) => (
						<div key={ stat.key } className="sh-UserCard__stat">
							<span className="sh-UserCard__statValue">
								{ stat.value }
							</span>
							<span className="sh-UserCard__statLabel">
								{ stat.label }
							</span>
						</div>
					) ) }
				</div>
			) }

			{ ! isLoading && <MetaDetailsList details={ textDetails } /> }

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
	const allDetails = cardData?.details || [];
	const statDetails = allDetails.filter( ( d ) => d.type === 'stat' );
	const textDetails = allDetails.filter( ( d ) => d.type !== 'stat' );

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
				</div>
			</div>
			{ isLoading && (
				<div className="sh-UserCard__loading">
					<Spinner />
				</div>
			) }

			{ ! isLoading && statDetails.length > 0 && (
				<div className="sh-UserCard__stats">
					<h5 className="sh-UserCard__statsHeading">
						<Icon
							icon={ chartBar }
							size={ 14 }
							className="sh-UserCard__statsHeadingIcon"
						/>
						{ __( 'Events', 'simple-history' ) }
					</h5>
					{ statDetails.map( ( stat ) => (
						<div key={ stat.key } className="sh-UserCard__stat">
							<span className="sh-UserCard__statValue">
								{ stat.value }
							</span>
							<span className="sh-UserCard__statLabel">
								{ stat.label }
							</span>
						</div>
					) ) }
				</div>
			) }

			{ ! isLoading && <MetaDetailsList details={ textDetails } /> }

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

	const closeThis = () => setShowPopover( false );

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

	// Clear module-level closer on unmount to avoid stale references.
	useEffect( () => {
		return () => {
			if ( closeActiveUserCard === closeThis ) {
				closeActiveUserCard = null;
			}
		};
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

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
		closeActiveUserCard = closeThis;

		setShowPopover( true );

		// Don't refetch if we already have data.
		if ( cardData ) {
			return;
		}

		// Determine the API path and check cache based on initiator type.
		let apiPath;
		if ( isWPUser ) {
			if ( ! userId ) {
				return;
			}
			const cached = readCache( userCardCache, userId );
			if ( cached ) {
				setCardData( cached );
				return;
			}
			apiPath = `/simple-history/v1/users/${ userId }/card`;
		} else {
			const cached = readCache( initiatorCardCache, event.initiator );
			if ( cached ) {
				setCardData( cached );
				return;
			}
			apiPath = `/simple-history/v1/initiators/${ event.initiator }/card`;
		}

		setIsLoading( true );

		apiFetch( { path: apiPath } )
			.then( ( data ) => {
				if ( isWPUser ) {
					writeCache( userCardCache, userId, data );
				} else {
					writeCache( initiatorCardCache, event.initiator, data );
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
					placement="top-start"
					animate={ false }
					shift={ true }
					className="sh-UserCard__popover"
					onFocusOutside={ () => setShowPopover( false ) }
				>
					<div
						className={ `sh-UserCard${
							isWPUser ? ' sh-UserCard--wp-user' : ''
						}${ isLoading ? ' sh-UserCard--loading' : '' }` }
					>
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
