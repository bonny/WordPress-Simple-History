import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
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

	const isThisPageMode = filterMode === 'this-page' && currentPostId > 0;

	/**
	 * Filter whether to show the premium teaser for "This page" mode.
	 * Premium add-on returns false to show filtered events instead.
	 *
	 * @param {boolean} showTeaser Whether to show the teaser. Default true when in this-page mode.
	 * @param {Object}  context    Context object with currentPostId and currentPostTitle.
	 */
	const showThisPageTeaser = applyFilters(
		'simpleHistory.adminBar.showThisPageTeaser',
		isThisPageMode,
		{ currentPostId, currentPostTitle }
	);

	/**
	 * Filter the "View full history" URL.
	 * Premium add-on can add context filter parameters.
	 *
	 * @param {string} url     The default history page URL.
	 * @param {Object} context Context object with filterMode, currentPostId, and currentPostTitle.
	 */
	const viewFullHistoryHref = applyFilters(
		'simpleHistory.adminBar.viewFullHistoryUrl',
		viewHistoryURL,
		{ filterMode, currentPostId, currentPostTitle }
	);

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

	// Re-fetch when filterMode changes (only when not showing teaser).
	useEffect( () => {
		if ( reloadTime !== null && ! showThisPageTeaser ) {
			setReloadTime( Date.now() );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ filterMode ] );

	// Load events when the reloadTime is set or updated.
	useEffect( () => {
		if ( reloadTime === null ) {
			return;
		}

		async function fetchEntries() {
			setIsLoading( true );

			const defaultParams = {
				per_page: 5,
				dates: 'lastdays:7',
			};

			/**
			 * Filter the events query parameters.
			 * Premium add-on can modify these to add context filtering.
			 *
			 * @param {Object} params  Default query parameters.
			 * @param {Object} context Context object with filterMode and currentPostId.
			 */
			const eventsQueryParams = applyFilters(
				'simpleHistory.adminBar.eventsQueryParams',
				defaultParams,
				{ filterMode, currentPostId }
			);

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

	/**
	 * Filter the info text shown above the events list.
	 * Premium add-on can change this for "this page" mode.
	 *
	 * @param {string} text    Default info text.
	 * @param {Object} context Context object with filterMode, currentPostId, and currentPostTitle.
	 */
	const infoText = applyFilters(
		'simpleHistory.adminBar.infoText',
		__( 'Events from the last 7 days', 'simple-history' ),
		{ filterMode, currentPostId, currentPostTitle }
	);

	const showEmptyState = ! isLoading && events.length === 0;

	const thisPageTeaser = (
		<li>
			<div className="SimpleHistory-adminBarQuickView-premiumTeaser">
				<p className="SimpleHistory-adminBarQuickView-premiumTeaser-heading">
					{ __( 'History for this page', 'simple-history' ) }
				</p>
				<p className="SimpleHistory-adminBarQuickView-premiumTeaser-description">
					{ __(
						'Filter the log to show only events for the page you\'re viewing.',
						'simple-history'
					) }
				</p>
				<a
					href="https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=adminbar&utm_campaign=this-page-filter"
					className="SimpleHistory-adminBarQuickView-premiumTeaser-link"
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Available with Premium', 'simple-history' ) }
					<span aria-hidden="true"> &rarr;</span>
				</a>
			</div>
		</li>
	);

	return (
		<li ref={ ref }>
			<ul>
				{ currentPostId > 0 ? (
					<li>
						<div className="SimpleHistory-adminBarQuickView-tabs">
							{ filterToggle }
						</div>
					</li>
				) : null }

				{ showThisPageTeaser ? (
					thisPageTeaser
				) : (
					<>
						<li>
							<div className="SimpleHistory-adminBarQuickView-infoText">
								{ infoText }
							</div>
						</li>

						{ isLoading ? (
							<EventsCompactListLoadingSkeleton />
						) : (
							<>
								<EventsCompactList
									events={ events }
									isLoading={ isLoading }
								/>
								{ showEmptyState && (
									<li>
										<div className="SimpleHistory-adminBarQuickView-emptyState">
											{ __(
												'No events found.',
												'simple-history'
											) }
										</div>
									</li>
								) }
							</>
						) }
					</>
				) }

				<li>
					<div className="SimpleHistory-adminBarEventsList-actions">
						{ viewFullHistoryLink }
						<div className="SimpleHistory-adminBarEventsList-actions-right">
							{ reloadButton }
							{ settingsLink }
						</div>
					</div>
				</li>
			</ul>
		</li>
	);
};

export default AdminBarQuickView;
