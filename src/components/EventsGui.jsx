import apiFetch from '@wordpress/api-fetch';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	parseAsArrayOf,
	parseAsIsoDate,
	parseAsJson,
	parseAsString,
	useQueryState,
} from 'nuqs';
import { z } from 'zod';
import {
	SEARCH_FILTER_DEFAULT_END_DATE,
	SEARCH_FILTER_DEFAULT_START_DATE,
} from '../constants';
import { generateAPIQueryParams } from '../functions';
import { DashboardFooter } from './DashboardFooter';
import { EventsControlBar } from './EventsControlBar';
import { EventsList } from './EventsList';
import { EventsModalIfFragment } from './EventsModalIfFragment';
import { EventsSearchFilters } from './EventsSearchFilters';
import { NewEventsNotifier } from './NewEventsNotifier';

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

// Schema for the initiator objects.
const initiatorSchema = z.array(
	z.object( {
		value: z.string(),
		initiator_key: z.string().optional(),
		search_options: z.array( z.string() ),
	} )
);

/**
 * Main component for the events GUI.
 * Contains the filter/search options and the events list.
 * Also contains the modal for the events modal.
 *
 * @return {JSX.Element} The events GUI component.
 */
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

	// Store the max date of the events. Used together with maxId to check for new events.
	const [ eventsMaxDate, setEventsMaxDate ] = useState();

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

	// Selected initiator filter.
	const [ selectedInitiator, setSelectedInitiator ] = useQueryState(
		'initiator',
		parseAsJson( initiatorSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Selected context filters.
	// Plain string with newline-separated "key:value" pairs, e.g., "_user_id:1\n_sticky:1"
	const [ selectedContextFilters, setSelectedContextFilters ] = useQueryState(
		'context',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Negative/exclusion filters - hide events matching these criteria.
	// Read-only from URL (no setters needed until Phase 2: GUI controls).
	const [ excludeSearch ] = useQueryState(
		'exclude-search',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	const [ excludeLogLevels ] = useQueryState(
		'exclude-levels',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeLoggers ] = useQueryState(
		'exclude-loggers',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeMessages ] = useQueryState(
		'exclude-messages',
		parseAsJson( messageTypesSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeUsers ] = useQueryState(
		'exclude-users',
		parseAsJson( usersSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeInitiator ] = useQueryState(
		'exclude-initiator',
		parseAsJson( initiatorSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeContextFilters ] = useQueryState(
		'exclude-context',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
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
			selectedInitiator,
			selectedContextFilters,
			enteredSearchText,
			selectedDateOption,
			selectedCustomDateFrom,
			selectedCustomDateTo,
			page,
			pagerSize,
			excludeSearch,
			excludeLogLevels,
			excludeLoggers,
			excludeMessages,
			excludeUsers,
			excludeInitiator,
			excludeContextFilters,
		} );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		selectedContextFilters,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
		pagerSize,
		excludeSearch,
		excludeLogLevels,
		excludeLoggers,
		excludeMessages,
		excludeUsers,
		excludeInitiator,
		excludeContextFilters,
	] );

	// Reset page to 1 when filters are modified.
	useEffect( () => {
		setPage( 1 );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedInitiator,
		selectedContextFilters,
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
			// Extract maxId and maxDate from response headers for accurate new event detection.
			if ( eventsJson && eventsJson.length && page === 1 ) {
				const maxId = eventsResponse.headers.get(
					'X-SimpleHistory-MaxId'
				);
				const maxDate = eventsResponse.headers.get(
					'X-SimpleHistory-MaxDate'
				);

				if ( maxId ) {
					setEventsMaxId( parseInt( maxId, 10 ) );
				}

				if ( maxDate ) {
					setEventsMaxDate( maxDate );
				}
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

	// Listen for chart date click events from the sidebar chart.
	// When a date is clicked in the chart, update the date filter to show events for that day.
	useEffect( () => {
		const handleChartDateClick = ( event ) => {
			const { date } = event.detail;

			// Parse the date string (Y-m-d format) to create a Date object.
			// The date string is in format "2024-10-05".
			const dateObj = new Date( date + 'T00:00:00Z' );

			// Set the date option to custom range.
			setSelectedDateOption( 'customRange' );

			// Set both from and to dates to the same date (to show only one day).
			setSelectedCustomDateFrom( dateObj );
			setSelectedCustomDateTo( dateObj );
		};

		window.addEventListener(
			'SimpleHistory:chartDateClick',
			handleChartDateClick
		);

		return () => {
			window.removeEventListener(
				'SimpleHistory:chartDateClick',
				handleChartDateClick
			);
		};
	}, [
		setSelectedDateOption,
		setSelectedCustomDateFrom,
		setSelectedCustomDateTo,
	] );

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
				selectedInitiator={ selectedInitiator }
				setSelectedInitiator={ setSelectedInitiator }
				selectedContextFilters={ selectedContextFilters }
				setSelectedContextFilters={ setSelectedContextFilters }
				searchOptionsLoaded={ searchOptionsLoaded }
				setSearchOptionsLoaded={ setSearchOptionsLoaded }
				setPagerSize={ setPagerSize }
				setMapsApiKey={ setMapsApiKey }
				setHasExtendedSettingsAddOn={ setHasExtendedSettingsAddOn }
				setHasPremiumAddOn={ setHasPremiumAddOn }
				isExperimentalFeaturesEnabled={ isExperimentalFeaturesEnabled }
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
				eventsMaxDate={ eventsMaxDate }
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

			{ isDashboard ? <DashboardFooter /> : null }

			<EventsModalIfFragment />
		</>
	);
}

export default EventsGUI;
