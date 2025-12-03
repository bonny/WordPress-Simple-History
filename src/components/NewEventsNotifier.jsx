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

	useEffect( () => {
		// Bail if no eventsQueryParams, eventsMaxId, or eventsMaxDate
		if ( ! eventsQueryParams || ! eventsMaxId || ! eventsMaxDate ) {
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
	}, [ eventsQueryParams, eventsMaxId, eventsMaxDate, shouldPoll ] );

	// When we've stopped polling due to reaching limit, show "10+ new events"
	const hasReachedLimit =
		! shouldPoll && newEventsCount >= MAX_NEW_EVENTS_BEFORE_STOP;

	const newEventsCountText = hasReachedLimit
		? sprintf(
				// translators: %d: maximum number of events shown before stopping polling
				__( '%d+ new events', 'simple-history' ),
				MAX_NEW_EVENTS_BEFORE_STOP
		  )
		: sprintf(
				// translators: %s: number of new events
				_n(
					'%s new event',
					'%s new events',
					newEventsCount,
					'simple-history'
				),
				newEventsCount
		  );

	// Update page title with new events count.
	useEffect( () => {
		const titleCount = hasReachedLimit
			? MAX_NEW_EVENTS_BEFORE_STOP + '+'
			: newEventsCount;
		setDocumentTitle( titleCount );
	}, [ hasReachedLimit, newEventsCount ] );

	const handleUpdateClick = () => {
		onReload();
		setNewEventsCount( 0 );
		setShouldPoll( true );
	};

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
				label={ __( 'Click to load new events', 'simple-history' ) }
				showTooltip={ true }
				variant="tertiary"
				style={ { width: '100%', justifyContent: 'center' } }
			>
				{ newEventsCountText }
			</Button>
		</div>
	);
}
