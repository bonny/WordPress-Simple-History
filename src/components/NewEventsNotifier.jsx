import apiFetch from '@wordpress/api-fetch';
import { Button } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { update } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import { clsx } from 'clsx';

// How often to check for new events, in milliseconds.
const UPDATE_CHECK_INTERVAL = 30000;

// Maximum number of new events to display before stopping polling.
const MAX_NEW_EVENTS_BEFORE_STOP = 10;

// Event a view fills the events Slot with can dispatch on `window` to say it
// is keeping itself up to date, and that this notifier should stand down.
//
// Premium's table view has a Live toggle that polls on its own and puts new
// events straight at the top. With both running, a reader who turned Live on
// watched rows arrive and was then told by this notifier that there were N
// new events to load — counting the very rows already on screen, badging the
// tab title for events they were looking at, and offering a button whose
// reload would throw away their scroll position, their selection and their
// expanded rows.
//
// A window event rather than a prop because the two components are in
// different plugins with no shared parent: state flows down through the Slot
// and there is nothing to carry it back up. It also degrades on its own —
// a premium too old to dispatch this leaves the notifier exactly as it was,
// and a core too old to listen ignores a dispatch it never hears.
export const LIVE_MODE_EVENT = 'simple_history/live_mode';

function setDocumentTitle( newNum ) {
	let title = document.title;

	// Remove any existing number first or !, like (123) Regular title => Regular title
	title = title.replace( /^\([\d!+]+\) /, '' );

	if ( newNum ) {
		title = '(' + newNum + ') ' + title;
	}

	document.title = title;
}

/**
 * Checks for new events and notifies the user if there are new events.
 *
 * In prev version this was an AJAX call:
 * http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin-ajax.php?action=SimpleHistoryNewRowsNotifier&apiArgs%5Bsince_id%5D=22095&apiArgs%5Bdates%5D=lastdays%3A30
 *
 * We use the REST API instead.
 * same query arg as before but append since_id.
 *
 * @param {Object} props
 */
export function NewEventsNotifier( props ) {
	const { eventsQueryParams, eventsMaxId, eventsMaxDate, onReload } = props;
	const [ newEventsCount, setNewEventsCount ] = useState( 0 );
	const [ shouldPoll, setShouldPoll ] = useState( true );
	const [ isLiveElsewhere, setIsLiveElsewhere ] = useState( false );

	// See LIVE_MODE_EVENT above.
	useEffect( () => {
		const onLiveMode = ( event ) => {
			setIsLiveElsewhere( Boolean( event.detail?.isLive ) );
		};

		window.addEventListener( LIVE_MODE_EVENT, onLiveMode );

		return () => {
			window.removeEventListener( LIVE_MODE_EVENT, onLiveMode );
		};
	}, [] );

	// Anything this notifier had to say is about events the live view has
	// since put on screen, so drop the count rather than leaving a stale
	// one to reappear when live mode goes off again.
	useEffect( () => {
		if ( isLiveElsewhere ) {
			setNewEventsCount( 0 );
			setShouldPoll( true );
		}
	}, [ isLiveElsewhere ] );

	useEffect( () => {
		// Bail if no eventsQueryParams, eventsMaxId, or eventsMaxDate
		if ( ! eventsQueryParams || ! eventsMaxId || ! eventsMaxDate ) {
			return;
		}

		// Bail if another view is keeping itself up to date.
		if ( isLiveElsewhere ) {
			return;
		}

		// Bail if polling is disabled (e.g., after reaching 10+ events)
		if ( ! shouldPoll ) {
			return;
		}

		const intervalId = setInterval( async () => {
			const eventsQueryParamsWithSinceId = {
				...eventsQueryParams,
				since_id: eventsMaxId,
				since_date: eventsMaxDate,
				// Remove any limitation of fields that have been added by main API request.
				_fields: null,
			};

			try {
				const eventsResponse = await apiFetch( {
					path: addQueryArgs(
						'/simple-history/v1/events/has-updates',
						eventsQueryParamsWithSinceId
					),
					// Skip parsing to be able to retrieve headers.
					parse: false,
				} );

				const responseJson = await eventsResponse.json();
				const responseNewEventsCount = responseJson.new_events_count;

				if ( responseNewEventsCount > 0 ) {
					// Cap the count at MAX_NEW_EVENTS_BEFORE_STOP and stop polling if we reach the limit
					if (
						responseNewEventsCount >= MAX_NEW_EVENTS_BEFORE_STOP
					) {
						setNewEventsCount( MAX_NEW_EVENTS_BEFORE_STOP );
						setShouldPoll( false );
					} else {
						setNewEventsCount( responseNewEventsCount );
					}
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Error when checking for new events:', error );
			}
			// TODO: should this be customizable with filter? and also be able to disable it?
		}, UPDATE_CHECK_INTERVAL );

		return () => {
			clearInterval( intervalId );
		};
	}, [
		eventsQueryParams,
		eventsMaxId,
		eventsMaxDate,
		shouldPoll,
		isLiveElsewhere,
	] );

	// When we've stopped polling due to reaching limit, show "10+ new events"
	const hasReachedLimit =
		! shouldPoll && newEventsCount >= MAX_NEW_EVENTS_BEFORE_STOP;

	// The verb belongs in the visible label, not only in the tooltip. This
	// used to read "3 new events" with "Click to load new events" hidden in
	// an aria-label, so a screen-reader user was told what the button did
	// and everyone else was told a number and left to guess.
	const newEventsCountText = hasReachedLimit
		? sprintf(
				// translators: %d: maximum number of events shown before stopping polling
				__( 'Show %d+ new events', 'simple-history' ),
				MAX_NEW_EVENTS_BEFORE_STOP
		  )
		: sprintf(
				// translators: %s: number of new events
				_n(
					'Show %s new event',
					'Show %s new events',
					newEventsCount,
					'simple-history'
				),
				newEventsCount
		  );

	// Update page title with new events count.
	//
	// Not while another view is live: the badge counts events the reader
	// has not seen, and there are none — they are on screen, in the tab the
	// badge would be shouting at them from.
	useEffect( () => {
		if ( isLiveElsewhere ) {
			setDocumentTitle( 0 );

			return;
		}

		const titleCount = hasReachedLimit
			? MAX_NEW_EVENTS_BEFORE_STOP + '+'
			: newEventsCount;
		setDocumentTitle( titleCount );
	}, [ hasReachedLimit, newEventsCount, isLiveElsewhere ] );

	const handleUpdateClick = () => {
		onReload();
		setNewEventsCount( 0 );
		setShouldPoll( true );
	};

	if ( isLiveElsewhere ) {
		return null;
	}

	return (
		<div
			className={ clsx( {
				SimpleHistoryDropin__NewRowsNotifier: true,
				'SimpleHistoryDropin__NewRowsNotifier--haveNewRows':
					newEventsCount > 0,
			} ) }
		>
			<Button
				icon={ update }
				onClick={ handleUpdateClick }
				showTooltip={ true }
				variant="tertiary"
			>
				{ newEventsCountText }
			</Button>
		</div>
	);
}
