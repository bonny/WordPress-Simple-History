import { Button, __experimentalText as Text } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Checks for new events and notifies the user if there are new events.
 *
 * In prev version this was an AJAX call:
 * http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin-ajax.php?action=SimpleHistoryNewRowsNotifier&apiArgs%5Bsince_id%5D=22095&apiArgs%5Bdates%5D=lastdays%3A30
 *
 * We use the REST API instead.
 * same query arg as before but append since_id.
 */
export function NewEventsNotifier( props ) {
	const { eventsQueryParams, eventsMaxId } = props;
	const [ newEventFound, setNewEventFound ] = useState( false );

	useEffect( () => {
		// Bail if no eventsQueryParams or eventsMaxId
		if ( ! eventsQueryParams || ! eventsMaxId ) {
			return;
		}

		const intervalId = setInterval( () => {
			console.log( 'Checking for new events...' );
			console.log( 'using eventsQueryParams', eventsQueryParams );
			console.log( 'using eventsMaxId', eventsMaxId );

			const eventsQueryParamsWithSinceId = {
				...eventsQueryParams,
				since_id: eventsMaxId,
			};
		}, 5000 );

		return () => {
			console.log( 'clear timer interval on unmount', eventsQueryParams );
			clearInterval( intervalId );
		};
	}, [ eventsQueryParams, eventsMaxId ] );

	return (
		<>
			{ /* <Text>Checking for new events...</Text> */ }
			<Text>
				{ newEventFound ? (
					<Button icon={ update }>1 new event</Button>
				) : null }
			</Text>
		</>
	);
}
