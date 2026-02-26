import apiFetch from '@wordpress/api-fetch';
import { Spinner, __experimentalText as Text } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _x } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { FetchEventsErrorMessage } from './FetchEventsErrorMessage';
import { FetchEventsNoResultsMessage } from './FetchEventsNoResultsMessage';
import { DashboardEventsItemsList } from './DashboardEventsItemsList';

/**
 * Simplified events widget for the WordPress dashboard.
 * Replaces the full EventsGUI to keep the dashboard compact:
 * no filters, no control bar, no event action menus, no pagination.
 */
export function DashboardEventsWidget() {
	const [ events, setEvents ] = useState( [] );
	const [ eventsIsLoading, setEventsIsLoading ] = useState( true );
	const [ eventsLoadingHasErrors, setEventsLoadingHasErrors ] =
		useState( false );
	const [ eventsLoadingErrorDetails, setEventsLoadingErrorDetails ] =
		useState( { errorCode: undefined, errorMessage: undefined } );
	const [ eventsAdminPageURL, setEventsAdminPageURL ] = useState( '' );
	const [ pagerSize, setPagerSize ] = useState( null );
	const [ hasPremiumAddOn, setHasPremiumAddOn ] = useState( false );

	// Fetch search options on mount to get pager size and admin page URL.
	useEffect( () => {
		apiFetch( { path: '/simple-history/v1/search-options' } )
			.then( ( response ) => {
				setPagerSize( response.pager_size );
				setEventsAdminPageURL( response.events_admin_page_url || '' );
				setHasPremiumAddOn(
					response.addons?.has_premium_add_on || false
				);
			} )
			.catch( () => {} );
	}, [] );

	// Fetch events once pager size is available.
	const loadEvents = useCallback( async () => {
		if ( ! pagerSize ) {
			return;
		}

		setEventsIsLoading( true );

		try {
			const eventsResponse = await apiFetch( {
				path: addQueryArgs( '/simple-history/v1/events', {
					per_page: pagerSize.dashboard,
					_fields: [
						'id',
						'logger',
						'date_local',
						'date_gmt',
						'message',
						'message_html',
						'message_key',
						'details_data',
						'details_html',
						'loglevel',
						'occasions_id',
						'subsequent_occasions_count',
						'initiator',
						'initiator_data',
						'ip_addresses',
						'via',
						'permalink',
						'sticky',
						'sticky_appended',
						'backfilled',
						'action_links',
					],
				} ),
				parse: false,
			} );

			const eventsJson = await eventsResponse.json();
			setEvents( eventsJson );
		} catch ( error ) {
			setEventsLoadingHasErrors( true );

			const errorDetails = {
				code: null,
				statusText: null,
				bodyJson: null,
				bodyText: null,
			};

			if ( error.headers && error.status && error.statusText ) {
				const contentType = error.headers.get( 'Content-Type' );
				errorDetails.code = error.status;
				errorDetails.statusText = error.statusText;

				if (
					contentType &&
					contentType.includes( 'application/json' )
				) {
					errorDetails.bodyJson = await error.json();
				} else {
					errorDetails.bodyText = await error.text();
				}
			} else {
				errorDetails.bodyText = __( 'Unknown error', 'simple-history' );
			}

			setEventsLoadingErrorDetails( errorDetails );
		} finally {
			setEventsIsLoading( false );
		}
	}, [ pagerSize ] );

	useEffect( () => {
		loadEvents();
	}, [ loadEvents ] );

	return (
		<div
			style={ {
				backgroundColor: 'white',
				minHeight: '100px',
			} }
		>
			{ eventsIsLoading && (
				<div style={ { padding: '12px', textAlign: 'center' } }>
					<Text>
						<Spinner />
						{ _x(
							'Loading…',
							'Message visible while waiting for log to load from server the first time',
							'simple-history'
						) }
					</Text>
				</div>
			) }

			<FetchEventsNoResultsMessage
				eventsIsLoading={ eventsIsLoading }
				events={ events }
			/>

			<FetchEventsErrorMessage
				eventsLoadingHasErrors={ eventsLoadingHasErrors }
				eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
			/>

			<DashboardEventsItemsList
				eventsIsLoading={ eventsIsLoading }
				events={ events }
				hasPremiumAddOn={ hasPremiumAddOn }
			/>

			{ /* "View all activity" link replaces full pagination */ }
			{ ! eventsIsLoading && events.length > 0 && eventsAdminPageURL && (
				<div className="sh-DashboardWidget-viewAll">
					<a href={ eventsAdminPageURL }>
						{ __( 'View all activity →', 'simple-history' ) }
					</a>
				</div>
			) }
		</div>
	);
}
