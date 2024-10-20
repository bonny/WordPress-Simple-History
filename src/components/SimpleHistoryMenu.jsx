import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { useInView } from 'react-intersection-observer';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';
import { EventInitiatorImage } from './EventInitiator';

import './style.css';
import clsx from 'clsx';

const CompactEvent = ( props ) => {
	const { event } = props;

	return (
		<MenuBarLiItem href={ event.link }>
			<EventInitiatorImage event={ event } />
			<EventDate event={ event } />
			<EventInitiatorName event={ event } />
			<p>{ event.message }</p>
		</MenuBarLiItem>
	);
};

const EventsCompactList = ( props ) => {
	const { events } = props;

	console.log( 'EventsCompactList', events );

	// Events not loaded yet.
	if ( events.length === 0 ) {
		return null;
	}

	return (
		<ul
			style={ {
				backgroundColor: '#4c4c4d',
			} }
		>
			{ events.map( ( event ) => (
				<li key={ event.id }>
					<CompactEvent event={ event } />
				</li>
			) ) }
		</ul>
	);
};

const MenuBarLiItem = ( props ) => {
	const { children, href } = props;

	const divClassNames = clsx( 'ab-item', {
		'ab-empty-item': ! href,
	} );

	const TagName = href ? 'a' : 'div';

	return (
		<li role="group" id="wp-admin-bar-simple-history-subnode-1">
			<TagName
				className={ divClassNames }
				role="menuitem"
				style={ {
					// Override wp admin bar fixed height of 26px.
					height: 'auto',
				} }
			>
				{ children }
			</TagName>
		</li>
	);
};

const SimpleHistoryMenu = () => {
	// True if Simple History admin bar menu is open/visible.
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isLoaded, setIsLoaded ] = useState( false );
	const [ events, setEvents ] = useState( [] );

	// https://www.npmjs.com/package/react-intersection-observer
	const { ref, inView, entry } = useInView( {} );

	useEffect( () => {
		// Admin bar submenu not visible yet.
		if ( inView === false ) {
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
	}, [ inView ] );

	return (
		<li ref={ ref }>
			<ul>
				{ isLoading ? (
					<MenuBarLiItem>
						{ __( 'Loading events…', 'simple-history' ) }
					</MenuBarLiItem>
				) : (
					<MenuBarLiItem>
						{ __( 'Events loaded', 'simple-history' ) }
					</MenuBarLiItem>
				) }

				<EventsCompactList events={ events } />

				<MenuBarLiItem href="#">View full history</MenuBarLiItem>
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

	const fetchEntries = () => {
		if ( isLoaded ) {
			setIsOpen( ! isOpen );
			return;
		}

		apiFetch( {
			url: sha_ajax_object.ajax_url,
			method: 'POST',
			data: {
				action: 'sha_get_log_entries',
				nonce: sha_ajax_object.nonce,
			},
		} )
			.then( ( response ) => {
				if ( response.success ) {
					setEvents( response.data );
					setIsLoaded( true );
					setIsOpen( true );
				} else {
					setError( response.data );
				}
			} )
			.catch( ( err ) => {
				setError( 'An error occurred while fetching log entries.' );
				console.error( err );
			} );
	};

	return (
		<div>
			<a
				href="#"
				onClick={ ( e ) => {
					e.preventDefault();
					fetchEntries();
				} }
			>
				Simple History
			</a>
			{ isOpen && (
				<div className="ab-sub-wrapper">
					<ul className="ab-submenu">
						{ events.map( ( entry ) => (
							<li key={ entry.id } className="ab-submenu-item">
								<a href={ entry.href }>{ entry.title }</a>
							</li>
						) ) }
					</ul>
				</div>
			) }
			{ error && (
				<div className="ab-sub-wrapper">
					<div className="ab-submenu">
						<div className="ab-submenu-item">{ error }</div>
					</div>
				</div>
			) }
		</div>
	);
};

export default SimpleHistoryMenu;
