import apiFetch from '@wordpress/api-fetch';
import { Notice, __experimentalText as Text } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	SEARCH_FILTER_DEFAULT_END_DATE,
	SEARCH_FILTER_DEFAULT_START_DATE,
} from '../constants';
import { generateAPIQueryParams } from '../functions';
import { EventsControlBar } from './EventsControlBar';
import { EventsList } from './EventsList';
import { EventsModalIfFragment } from './EventsModalIfFragment';
import { EventsSearchFilters } from './EventsSearchFilters';
import { NewEventsNotifier } from './NewEventsNotifier';

function FetchEventsErrorMessage( props ) {
	const { eventsLoadingHasErrors, eventsLoadingErrorDetails } = props;

	if ( ! eventsLoadingHasErrors ) {
		return null;
	}

	return (
		<div
			style={ {
				margin: '1rem',
			} }
		>
			<Notice status="warning" isDismissible={ false }>
				<Text>
					{ __(
						'There was an error loading the events. Please try again later.',
						'simple-history'
					) }
				</Text>

				<details>
					<summary
						style={ {
							marginTop: '.5rem',
						} }
					>
						{ __( 'View error details', 'simple-history' ) }
					</summary>

					<pre>
						{ JSON.stringify( eventsLoadingErrorDetails, null, 2 ) }
					</pre>
				</details>
			</Notice>
		</div>
	);
}

function EventsGui() {
	const [ eventsIsLoading, setEventsIsLoading ] = useState( true );
	const [ eventsLoadingHasErrors, setEventsLoadingHasErrors ] =
		useState( false );
	const [ eventsLoadingErrorDetails, setEventsLoadingErrorDetails ] =
		useState( {
			errorCode: undefined,
			errorMessage: undefined,
		} );
	const [ events, setEvents ] = useState( [] );
	const [ eventsMeta, setEventsMeta ] = useState( {} );
	const [ eventsReloadTime, setEventsReloadTime ] = useState( Date.now() );

	// Store the max id of the events. Used to check for new events.
	const [ eventsMaxId, setEventsMaxId ] = useState();

	// Store the previous max id of the events. Used to modify events in the list so user can see what events are new.
	const [ prevEventsMaxId, setPrevEventsMaxId ] = useState();

	const [ searchOptionsLoaded, setSearchOptionsLoaded ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ pagerSize, setPagerSize ] = useState( {} );
	const [ mapsApiKey, setMapsApiKey ] = useState( '' );
	const [ hasExtendedSettingsAddOn, setHasExtendedSettingsAddOn ] =
		useState( false );
	const [ hasPremiumAddOn, setHasPremiumAddOn ] = useState( false );
	const [ isExperimentalFeaturesEnabled, setIsExperimentalFeaturesEnabled ] =
		useState( false );
	const [ eventsAdminPageURL, setEventsAdminPageURL ] = useState();
	const [ selectedDateOption, setSelectedDateOption ] = useState( '' );
	const [ selectedCustomDateFrom, setSelectedCustomDateFrom ] = useState(
		SEARCH_FILTER_DEFAULT_START_DATE
	);
	const [ selectedCustomDateTo, setSelectedCustomDateTo ] = useState(
		SEARCH_FILTER_DEFAULT_END_DATE
	);
	const [ enteredSearchText, setEnteredSearchText ] = useState( '' );
	const [ selectedLogLevels, setSelectedLogLevels ] = useState( [] );

	// Array with objects that contains message types suggestions, used in the message types select control.
	// Keys are "slug" for search and "value".
	const [ messageTypesSuggestions, setMessageTypesSuggestions ] = useState(
		[]
	);

	// Array with the selected message types.
	// Contains the same values as the messageTypesSuggestions array.
	const [ selectedMessageTypes, setSelectedMessageTypes ] = useState( [] );

	// Array with objects that contain both the user id and the name+email in the same object. Keys are "id" and "value".
	// All users that are selected are added here.
	// This data is used to get user id from the name+email when we send the selected users to the API.
	const [ selectedUsersWithId, setSelectedUsersWithId ] = useState( [] );

	const eventsQueryParams = useMemo( () => {
		return generateAPIQueryParams( {
			selectedLogLevels,
			selectedMessageTypes,
			selectedUsersWithId,
			enteredSearchText,
			selectedDateOption,
			selectedCustomDateFrom,
			selectedCustomDateTo,
			page,
			pagerSize,
		} );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
		pagerSize,
	] );

	// Reset page to 1 when filters are modified.
	useEffect( () => {
		setPage( 1 );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedCustomDateFrom,
		selectedCustomDateTo,
	] );

	/**
	 * Load events from the REST API.
	 * A new function is created each time the eventsQueryParams changes,
	 * so that's whats making the reload of events.
	 *
	 * TODO: Move this to a hook.
	 */
	const loadEvents = useCallback( async () => {
		setEventsIsLoading( true );

		try {
			const eventsResponse = await apiFetch( {
				path: addQueryArgs(
					'/simple-history/v1/events',
					eventsQueryParams
				),
				parse: false,
			} );

			const eventsJson = await eventsResponse.json();

			setEventsMeta( {
				total: parseInt(
					eventsResponse.headers.get( 'X-Wp-Total' ),
					10
				),
				totalPages: parseInt(
					eventsResponse.headers.get( 'X-Wp-Totalpages' ),
					10
				),
				link: eventsResponse.headers.get( 'Link' ),
			} );

			// To keep track of new events we need to store both old max id and new max id.
			if ( eventsJson && eventsJson.length && page === 1 ) {
				setEventsMaxId( eventsJson[ 0 ].id );
			}

			setEvents( eventsJson );
		} catch ( error ) {
			console.error( 'Error loading events:', error );

			setEventsLoadingHasErrors( true );

			const errorDetails = {
				code: error.status, // Example number "500".
				statusText: error.statusText, // Example "Internal Server Error".
				bodyJson: null,
				bodyText: null,
			};

			const contentType = error.headers.get( 'Content-Type' );
			if ( contentType && contentType.includes( 'application/json' ) ) {
				const errorJson = await error.json();
				console.error( 'Error response JSON:', errorJson );
				errorDetails.bodyJson = errorJson;
			} else {
				const errorText = await error.text();
				console.error( 'Error response text:', errorText );
				errorDetails.bodyText = errorText;
			}

			setEventsLoadingErrorDetails( errorDetails );
		} finally {
			setEventsIsLoading( false );
		}
	}, [ eventsQueryParams, page ] );

	// Debounce the loadEvents function to avoid multiple calls when user types fast.
	const debouncedLoadEvents = useDebounce( loadEvents, 500 );

	/**
	 * Load events when search options are loaded,
	 * when the reload time is changed,
	 * or when function debouncedLoadEvents is changed due to changes in eventsQueryParams.
	 */
	useEffect( () => {
		// Wait for search options to be loaded before loading events,
		// or the loadEvents will be called twice.
		if ( ! searchOptionsLoaded ) {
			return;
		}

		debouncedLoadEvents();
	}, [ debouncedLoadEvents, searchOptionsLoaded, eventsReloadTime ] );

	/**
	 * Function to set reload time to current time,
	 * which will trigger a reload of the events.
	 * This is used as a callback function for child components,
	 * for example for the search button in the search component.
	 */
	const handleReload = () => {
		setPage( 1 );
		setPrevEventsMaxId( eventsMaxId );
		setEventsReloadTime( Date.now() );
	};

	// Scroll to top smoothly when going to a new page.
	useEffect( () => {
		window.scrollTo( {
			top: 0,
			behavior: 'smooth',
		} );
	}, [ page ] );

	return (
		<>
			<EventsSearchFilters
				selectedLogLevels={ selectedLogLevels }
				setSelectedLogLevels={ setSelectedLogLevels }
				selectedMessageTypes={ selectedMessageTypes }
				setSelectedMessageTypes={ setSelectedMessageTypes }
				selectedDateOption={ selectedDateOption }
				setSelectedDateOption={ setSelectedDateOption }
				enteredSearchText={ enteredSearchText }
				setEnteredSearchText={ setEnteredSearchText }
				selectedCustomDateFrom={ selectedCustomDateFrom }
				setSelectedCustomDateFrom={ setSelectedCustomDateFrom }
				selectedCustomDateTo={ selectedCustomDateTo }
				setSelectedCustomDateTo={ setSelectedCustomDateTo }
				messageTypesSuggestions={ messageTypesSuggestions }
				setMessageTypesSuggestions={ setMessageTypesSuggestions }
				selectedUsersWithId={ selectedUsersWithId }
				setSelectedUsersWithId={ setSelectedUsersWithId }
				searchOptionsLoaded={ searchOptionsLoaded }
				setSearchOptionsLoaded={ setSearchOptionsLoaded }
				setPagerSize={ setPagerSize }
				setMapsApiKey={ setMapsApiKey }
				setHasExtendedSettingsAddOn={ setHasExtendedSettingsAddOn }
				setHasPremiumAddOn={ setHasPremiumAddOn }
				setIsExperimentalFeaturesEnabled={
					setIsExperimentalFeaturesEnabled
				}
				eventsAdminPageURL={ eventsAdminPageURL }
				setEventsAdminPageURL={ setEventsAdminPageURL }
				setPage={ setPage }
				onReload={ handleReload }
			/>

			<FetchEventsErrorMessage
				eventsQueryParams={ eventsQueryParams }
				eventsMaxId={ eventsMaxId }
				onReload={ handleReload }
				eventsLoadingHasErrors={ eventsLoadingHasErrors }
				eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
			/>

			<EventsControlBar
				isExperimentalFeaturesEnabled={ isExperimentalFeaturesEnabled }
				eventsIsLoading={ eventsIsLoading }
				eventsTotal={ eventsMeta.total }
				eventsQueryParams={ eventsQueryParams }
			/>

			<NewEventsNotifier
				eventsQueryParams={ eventsQueryParams }
				eventsMaxId={ eventsMaxId }
				onReload={ handleReload }
			/>

			<EventsList
				eventsIsLoading={ eventsIsLoading }
				events={ events }
				eventsMeta={ eventsMeta }
				page={ page }
				setPage={ setPage }
				eventsMaxId={ eventsMaxId }
				prevEventsMaxId={ prevEventsMaxId }
				mapsApiKey={ mapsApiKey }
				hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				hasPremiumAddOn={ hasPremiumAddOn }
			/>

			<EventsModalIfFragment />
		</>
	);
}

export default EventsGui;
