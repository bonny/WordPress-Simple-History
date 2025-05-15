import {
	useQueryState,
	parseAsString,
	parseAsIsoDate,
	parseAsArrayOf,
	parseAsJson,
} from 'nuqs';
import { z } from 'zod';
import apiFetch from '@wordpress/api-fetch';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
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
import { __ } from '@wordpress/i18n';

// Schema for the users object.
const usersSchema = z.array(
	z.object( {
		id: z.string(),
		value: z.string(),
	} )
);

// Schema for the message types object.
// [
// 	{
// 		"value": "WordPress and plugins updates found",
// 		"search_options": [
// 			"AvailableUpdatesLogger:core_update_available",
// 			"AvailableUpdatesLogger:plugin_update_available",
// 			"AvailableUpdatesLogger:theme_update_available"
// 		]
// 	},
// 	{
// 		"value": "Term edited",
// 		"search_options": [
// 			"SimpleCategoriesLogger:edited_term"
// 		]
// 	},
// 	{
// 		"value": "Plugin updates found",
// 		"search_options": [
// 			"AvailableUpdatesLogger:plugin_update_available"
// 		]
// 	}
// ]

const messageTypesSchema = z.array(
	z.object( {
		value: z.string(),
		search_options: z.array( z.string() ),
	} )
);

function EventsGUI() {
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
	const [ settingsPageURL, setSettingsPageURL ] = useState();

	/**
	 * Start filter/search options states.
	 */

	const isDashboard = window.pagenow === 'dashboard';
	const useQueryStateOptions = {
		// On dashboard we set throttle to +Infinity to avoid setting the URL at all
		// (since it's a "shared" page and we don't want to change the URL.)
		throttleMs: isDashboard ? +Infinity : 50,
	};

	// Value selected in dates dropdown.
	// Example values: "lastdays:30", "month:2025-04", "allDates", "customRange".
	const [ selectedDateOption, setSelectedDateOption ] = useQueryState(
		'date',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Custom from date. Default to today.
	// Stored in URL as "from=2025-04-01", variable is a Date object.
	const [ selectedCustomDateFrom, setSelectedCustomDateFrom ] = useQueryState(
		'from',
		parseAsIsoDate
			.withDefault( SEARCH_FILTER_DEFAULT_START_DATE )
			.withOptions( useQueryStateOptions )
	);

	// Custom to date. Default to today.
	const [ selectedCustomDateTo, setSelectedCustomDateTo ] = useQueryState(
		'to',
		parseAsIsoDate
			.withDefault( SEARCH_FILTER_DEFAULT_END_DATE )
			.withOptions( useQueryStateOptions )
	);

	// Search text, ie. the text in the search input field.
	const [ enteredSearchText, setEnteredSearchText ] = useQueryState(
		'q',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Empty array to use as default value for the log levels.
	// If [] is passed to the withDefault() then a new array is created on each render,
	// causing useEffect to trigger on each render and the log reloads indefinitely.
	const emptyArray = useMemo( () => [], [] );
	const [ selectedLogLevels, setSelectedLogLevels ] = useQueryState(
		'levels',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Array with the selected message types.
	// Contains the same values as the messageTypesSuggestions array.
	// This is a weird format that contains much info.
	// Example contents:
	// [
	// 	{
	// 		"value": "WordPress and plugins updates found",
	// 		"search_options": [
	// 			"AvailableUpdatesLogger:core_update_available",
	// 			"AvailableUpdatesLogger:plugin_update_available",
	// 			"AvailableUpdatesLogger:theme_update_available"
	// 		]
	// 	},
	// 	{
	// 		"value": "Term edited",
	// 		"search_options": [
	// 			"SimpleCategoriesLogger:edited_term"
	// 		]
	// 	},
	// 	{
	// 		"value": "Plugin updates found",
	// 		"search_options": [
	// 			"AvailableUpdatesLogger:plugin_update_available"
	// 		]
	// 	}
	// ]
	const [ selectedMessageTypes, setSelectedMessageTypes ] = useQueryState(
		'messages',
		parseAsJson( messageTypesSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Array with objects that contain both the user id and the name+email in the same object. Keys are "id" and "value".
	// All users that are selected are added here.
	// This data is used to get user id from the name+email when we send the selected users to the API.
	// Example object:
	// [
	// 	  {
	// 	    "id": "1",
	// 	    "value": "Jane (jane@example.com)"
	// 	  },
	// 	  {
	// 	    "id": "2",
	// 	    "value": "John (john@example.com)"
	//    }
	// ]
	const [ selectedUsersWithId, setSelectedUsersWithId ] = useQueryState(
		'users',
		parseAsJson( usersSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	/**
	 * End filter/search options states.
	 */

	// Generate the events query params.
	// Memoized to avoid unnecessary re-renders in the child components.
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
				const firstEventThatIsNotSticky = eventsJson.find(
					( event ) => ! event.sticky
				);
				setEventsMaxId( firstEventThatIsNotSticky.id );
			}

			setEvents( eventsJson );
		} catch ( error ) {
			setEventsLoadingHasErrors( true );

			// Base error details that we fill with data from the error.
			const errorDetails = {
				code: null, // Example number "500".
				statusText: null, // Example "Internal Server Error".
				bodyJson: null,
				bodyText: null,
			};

			// Fetch error response.
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
				// Unknown error.
				errorDetails.bodyText = __( 'Unknown error', 'simple-history' );
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
				setEventsSettingsPageURL={ setSettingsPageURL }
				setPage={ setPage }
				onReload={ handleReload }
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
				pagerSize={ pagerSize }
				setPage={ setPage }
				eventsMaxId={ eventsMaxId }
				prevEventsMaxId={ prevEventsMaxId }
				mapsApiKey={ mapsApiKey }
				hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				hasPremiumAddOn={ hasPremiumAddOn }
				eventsSettingsPageURL={ settingsPageURL }
				eventsAdminPageURL={ eventsAdminPageURL }
				eventsLoadingHasErrors={ eventsLoadingHasErrors }
				eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
			/>

			<EventsModalIfFragment />
		</>
	);
}

export default EventsGUI;
