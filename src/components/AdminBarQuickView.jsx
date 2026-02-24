import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import clsx from 'clsx';
import { useInView } from 'react-intersection-observer';
import { EventsCompactList } from './EventsCompactList';
import RefreshImage from '../../css/icons/refresh_24dp_5F6368_FILL0_wght400_GRAD0_opsz48.svg';
import './AdminBarQuickView.scss';

const EventsCompactListLoadingSkeleton = () => {
	return (
		<>
			<ul className="SimpleHistory-adminBarEventsList SimpleHistory-adminBarEventsList--skeleton">
				{ [ 1, 2, 3, 4, 5 ].map( ( index ) => (
					<MenuBarLiItem
						key={ index }
						className="SimpleHistory-adminBarEventsList-item"
					>
						<div className="SimpleHistory-adminBarEventsList-item-dot"></div>
						<div className="SimpleHistory-adminBarEventsList-item-content">
							<div className="SimpleHistory-adminBarEventsList-item-content-meta">
								<div className="SimpleHistory-adminBarEventsList-item-content-meta-skeleton"></div>
							</div>
							<div className="SimpleHistory-adminBarEventsList-item-content-message">
								<div className="SimpleHistory-adminBarEventsList-item-content-message-skeleton"></div>
							</div>
						</div>
					</MenuBarLiItem>
				) ) }
			</ul>
		</>
	);
};

const MenuBarLiItem = ( props ) => {
	const { children, href, className, title } = props;

	const divClassNames = clsx( 'ab-item', {
		'ab-empty-item': ! href,
		[ className ]: className,
	} );

	const TagName = href ? 'a' : 'div';

	return (
		<li role="group" id="wp-admin-bar-simple-history-subnode-1">
			<TagName
				className={ divClassNames }
				role="menuitem"
				href={ href }
				title={ title }
			>
				{ children }
			</TagName>
		</li>
	);
};

const AdminBarQuickView = () => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ events, setEvents ] = useState( [] );
	const [ reloadTime, setReloadTime ] = useState( null );
	const [ filterMode, setFilterMode ] = useState( 'all' );

	const viewHistoryURL = window.simpleHistoryAdminBar.adminPageUrl;
	const settingsURL = window.simpleHistoryAdminBar.viewSettingsUrl;

	const userCanViewHistory = Boolean(
		Number( window.simpleHistoryAdminBar.currentUserCanViewHistory )
	);

	const currentPostId = Number(
		window.simpleHistoryAdminBar.currentPostId || 0
	);
	const currentPostTitle =
		window.simpleHistoryAdminBar.currentPostTitle || '';

	const viewFullHistoryHref = ( () => {
		if ( filterMode === 'this-page' && currentPostId > 0 ) {
			const contextFilter = encodeURIComponent(
				`post_id:${ currentPostId }`
			);
			return `${ viewHistoryURL }&context=${ contextFilter }&date=allDates`;
		}
		return viewHistoryURL;
	} )();

	const viewFullHistoryLink = userCanViewHistory ? (
		<a
			href={ viewFullHistoryHref }
			className="SimpleHistory-adminBarEventsList-actions-settings"
		>
			{ __( 'View full history', 'simple-history' ) }
		</a>
	) : null;

	// https://www.npmjs.com/package/react-intersection-observer
	const { ref, inView } = useInView( {} );

	// Load events the first time the submenu becomes visible.
	useEffect( () => {
		if ( inView === false ) {
			return;
		}

		if ( reloadTime !== null ) {
			return;
		}

		setReloadTime( Date.now() );
	}, [ inView, reloadTime ] );

	// Re-fetch when filterMode changes.
	useEffect( () => {
		if ( reloadTime !== null ) {
			setReloadTime( Date.now() );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ filterMode ] );

	// Load events when the reloadTime is set or updated.
	// For example when submenu becomes visible or when reload button is pressed.
	useEffect( () => {
		if ( reloadTime === null ) {
			return;
		}

		async function fetchEntries() {
			setIsLoading( true );

			const eventsQueryParams = {
				per_page: 5,
			};

			if ( filterMode === 'this-page' && currentPostId > 0 ) {
				eventsQueryParams[ 'context_filters[post_id]' ] =
					String( currentPostId );
				eventsQueryParams.ungrouped = true;
			} else {
				eventsQueryParams.dates = 'lastdays:7';
			}

			try {
				const eventsResponse = await apiFetch( {
					path: addQueryArgs(
						'/simple-history/v1/events',
						eventsQueryParams
					),
					// Skip parsing to be able to retrieve headers.
					parse: false,
				} );

				const eventsJson = await eventsResponse.json();
				setEvents( eventsJson );
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Error loading events:', error );
			} finally {
				setIsLoading( false );
			}
		}

		fetchEntries();
	}, [ reloadTime, currentPostId, filterMode ] );

	const handleReloadButtonClick = () => {
		setReloadTime( Date.now() );
	};

	const reloadButton = (
		<button
			className="SimpleHistory-adminBarEventsList-actions-reload"
			onClick={ handleReloadButtonClick }
			disabled={ isLoading }
		>
			<img src={ RefreshImage } alt="" />
			{ __( 'Reload', 'simple-history' ) }
		</button>
	);

	const settingsLink = (
		<a href={ settingsURL }>{ __( 'Settings', 'simple-history' ) }</a>
	);

	const filterToggle =
		currentPostId > 0 ? (
			<div className="SimpleHistory-adminBarQuickView-filterToggle">
				<button
					className={ clsx(
						'SimpleHistory-adminBarQuickView-filterToggle-button',
						{ 'is-active': filterMode === 'all' }
					) }
					onClick={ () => setFilterMode( 'all' ) }
				>
					{ __( 'All history', 'simple-history' ) }
				</button>
				<button
					className={ clsx(
						'SimpleHistory-adminBarQuickView-filterToggle-button',
						{ 'is-active': filterMode === 'this-page' }
					) }
					onClick={ () => setFilterMode( 'this-page' ) }
				>
					{ __( 'This page', 'simple-history' ) }
				</button>
			</div>
		) : null;

	const isThisPageMode = filterMode === 'this-page' && currentPostId > 0;

	const infoText = isThisPageMode
		? /* translators: %s: post title */
		  sprintf( __( 'Events for "%s"', 'simple-history' ), currentPostTitle )
		: null;

	const showEmptyState = ! isLoading && events.length === 0;

	return (
		<li ref={ ref }>
			<ul>
				{ currentPostId > 0 ? (
					<div className="SimpleHistory-adminBarQuickView-tabs">
						{ filterToggle }
					</div>
				) : null }

				{ infoText ? (
					<div className="SimpleHistory-adminBarQuickView-infoText">
						{ infoText }
					</div>
				) : null }

				{ isLoading ? (
					<EventsCompactListLoadingSkeleton />
				) : (
					<>
						<EventsCompactList
							events={ events }
							isLoading={ isLoading }
						/>
						{ showEmptyState && (
							<div className="SimpleHistory-adminBarQuickView-emptyState">
								{ isThisPageMode
									? __(
											'No events found for this page.',
											'simple-history'
									  )
									: __(
											'No events found.',
											'simple-history'
									  ) }
							</div>
						) }
					</>
				) }

				<div className="SimpleHistory-adminBarEventsList-actions">
					{ viewFullHistoryLink }
					<div className="SimpleHistory-adminBarEventsList-actions-right">
						{ reloadButton }
						{ settingsLink }
					</div>
				</div>
			</ul>
		</li>
	);
	/* 
	// Admin bar can't handle multiple lines of text, so we need to use a submenu.
	// We render the react app to the ul items and then we can add li items in the React render.
	<ul
		role="menu"
		id="wp-admin-bar-simple-history-react-root-group"
		class="ab-submenu"
		>
		<li role="group" id="wp-admin-bar-simple-history-subnode-3">
			<div class="ab-item ab-empty-item" role="menuitem">
			This is a subnode to the group
			</div>
		</li>
		<li role="group" id="wp-admin-bar-simple-history-subnode-4">
			<div class="ab-item ab-empty-item" role="menuitem">
			This is another subnode to the group
			</div>
		</li>
		</ul>
	*/
};

export default AdminBarQuickView;
