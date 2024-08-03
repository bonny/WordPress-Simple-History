import apiFetch from '@wordpress/api-fetch';
import { Button, __experimentalText as Text } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { update } from '@wordpress/icons';
import { _n, __, sprintf } from '@wordpress/i18n';

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
	const { eventsQueryParams, eventsMaxId, onReload } = props;
	const [ newEventsCount, setNewEventsCount ] = useState( 0 );
	console.log( 'newEventsCount is', newEventsCount );
	useEffect( () => {
		// Bail if no eventsQueryParams or eventsMaxId
		if ( ! eventsQueryParams || ! eventsMaxId ) {
			return;
		}

		const intervalId = setInterval( async () => {
			console.log( 'Checking for new events...' );

			const eventsQueryParamsWithSinceId = {
				...eventsQueryParams,
				since_id: eventsMaxId,
				// Remove any fields that have been added by main api request.
				_fields: null,
			};

			console.log(
				'eventsQueryParamsWithSinceId',
				eventsQueryParamsWithSinceId
			);
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
				setNewEventsCount( responseNewEventsCount );
			}
			console.log( 'responseJson', responseJson );
		}, 5000 );

		return () => {
			console.log( 'clear timer interval on unmount', eventsQueryParams );
			clearInterval( intervalId );
		};
	}, [ eventsQueryParams, eventsMaxId, newEventsCount ] );

	const newEventsCountText = sprintf(
		// translators: %s: number of new events
		_n( '%s new event', '%s new events', newEventsCount, 'simple-history' ),
		newEventsCount
	);

	const handleUpdateClick = () => {
		onReload();
		setNewEventsCount( 0 );
	};

	return (
		<>
			{ /* <Text>Checking for new events...</Text> */ }
			<Text>
				{ newEventsCount > 0 ? (
					<Button
						icon={ update }
						onClick={ handleUpdateClick }
						label={ __(
							'Click to load new events',
							'simple-history'
						) }
						showTooltip={ true }
					>
						{ newEventsCountText }
					</Button>
				) : null }
			</Text>
		</>
	);
}
