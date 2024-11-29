import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import clsx from 'clsx';
import { useInView } from 'react-intersection-observer';
import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';

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

const CompactEvent = ( props ) => {
	const { event } = props;

	return (
		<MenuBarLiItem
			href={ event.link }
			className="SimpleHistory-adminBarEventsList-item"
			title={ __( 'View event details', 'simple-history' ) }
		>
			<div className="SimpleHistory-adminBarEventsList-item-dot"></div>
			<div className="SimpleHistory-adminBarEventsList-item-content">
				<div className="SimpleHistory-adminBarEventsList-item-content-meta">
					<EventInitiatorName
						event={ event }
						eventVariant="compact"
					/>
					<EventDate event={ event } eventVariant="compact" />
				</div>
				<div className="SimpleHistory-adminBarEventsList-item-content-message">
					<p>{ event.message }</p>
				</div>
			</div>
		</MenuBarLiItem>
	);
};

const EventsCompactList = ( props ) => {
	const { events, isLoading } = props;

	if ( isLoading ) {
		return <EventsCompactListLoadingSkeleton />;
	}

	// Events not loaded yet.
	if ( events.length === 0 ) {
		return null;
	}

	return (
		<ul className="SimpleHistory-adminBarEventsList">
			{ events.map( ( event ) => (
				<CompactEvent key={ event.id } event={ event } />
			) ) }
		</ul>
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
	}, [ reloadTime ] );

	const viewHistoryURL = window.simpleHistoryAdminBar.adminPageUrl;
	const settingsURL = window.simpleHistoryAdminBar.viewSettingsUrl;

	const userCanViewHistory = Boolean(
		Number( window.simpleHistoryAdminBar.currentUserCanViewHistory )
	);
	const viewFullHistoryLink = userCanViewHistory ? (
		<a
			href={ viewHistoryURL }
			className="SimpleHistory-adminBarEventsList-actions-settings"
		>
			View full history
		</a>
	) : null;

	const handleReloadButtonClick = () => {
		setReloadTime( Date.now() );
	};

	return (
		<li ref={ ref }>
			<ul>
				<EventsCompactList events={ events } isLoading={ isLoading } />

				<footer className="SimpleHistory-adminBarEventsList-actions">
					{ viewFullHistoryLink }

					<a href={ settingsURL }>
						{ __( 'Settings', 'simple-history' ) }
					</a>

					<button
						className="button button-small SimpleHistory-adminBarEventsList-actions-reload"
						onClick={ handleReloadButtonClick }
						disabled={ isLoading }
					>
						<span className="dashicons dashicons-update-alt"></span>
						{ __( 'Reload', 'simple-history' ) }
					</button>
				</footer>
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
