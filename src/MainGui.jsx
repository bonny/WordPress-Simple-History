import apiFetch from '@wordpress/api-fetch';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';
import { endOfDay, format, startOfDay } from 'date-fns';
import { EventsControlBar } from './components/EventsControlBar';
import { TIMEZONELESS_FORMAT } from './constants';
import { EventsList } from './EventsList';
import { EventsSearchFilters } from './EventsSearchFilters';
import { generateAPIQueryParams } from './functions';

const defaultStartDate = format(
	startOfDay( new Date() ),
	TIMEZONELESS_FORMAT
);
const defaultEndDate = format( endOfDay( new Date() ), TIMEZONELESS_FORMAT );

function MainGui() {
	const [ eventsIsLoading, setEventsIsLoading ] = useState( true );
	const [ events, setEvents ] = useState( [] );
	const [ eventsMeta, setEventsMeta ] = useState( {} );
	const [ eventsReloadTime, setEventsReloadTime ] = useState( Date.now() );
	const [ eventsMaxId, setEventsMaxId ] = useState();
	const [ searchOptionsLoaded, setSearchOptionsLoaded ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ pagerSize, setPagerSize ] = useState( {} );
	const [ mapsApiKey, setMapsApiKey ] = useState( '' );
	const [ selectedDateOption, setSelectedDateOption ] = useState( '' );
	const [ selectedCustomDateFrom, setSelectedCustomDateFrom ] =
		useState( defaultStartDate );
	const [ selectedCustomDateTo, setSelectedCustomDateTo ] =
		useState( defaultEndDate );
	const [ enteredSearchText, setEnteredSearchText ] = useState( '' );
	const [ selectedLogLevels, setSelectedLogLevels ] = useState( [] );
	const [ messageTypesSuggestions, setMessageTypesSuggestions ] = useState(
		[]
	);
	const [ selectedMessageTypes, setSelectedMessageTypes ] = useState( [] );
	const [ userSuggestions, setUserSuggestions ] = useState( [] );
	const [ selectedUsers, setSelectUsers ] = useState( [] );

	const eventsQueryParams = useMemo( () => {
		return generateAPIQueryParams( {
			selectedLogLevels,
			selectedMessageTypes,
			selectedUsers,
			enteredSearchText,
			selectedDateOption,
			selectedCustomDateFrom,
			selectedCustomDateTo,
			page,
			pagerSize,
		} );
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsers,
		enteredSearchText,
		selectedDateOption,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
		pagerSize,
	] );

	/**
	 * Load events from the REST API.
	 * A new function is created each time the eventsQueryParams changes,
	 * so that's whats making the reload of events.
	 */
	const loadEvents = useCallback( async () => {
		setEventsIsLoading( true );

		const eventsResponse = await apiFetch( {
			path: addQueryArgs(
				'/simple-history/v1/events',
				eventsQueryParams
			),
			// Skip parsing to be able to retrieve headers.
			parse: false,
		} );

		const eventsJson = await eventsResponse.json();

		setEventsMeta( {
			total: parseInt( eventsResponse.headers.get( 'X-Wp-Total' ), 10 ),
			totalPages: parseInt(
				eventsResponse.headers.get( 'X-Wp-Totalpages' ),
				10
			),
			link: eventsResponse.headers.get( 'Link' ),
		} );

		setEvents( eventsJson );
		setEventsIsLoading( false );
	}, [ eventsQueryParams ] );

	// Debounce the loadEvents function to avoid multiple calls when user types fast.
	const debouncedLoadEvents = useDebounce( loadEvents, 500 );

	/**
	 * Load events when search options are loaded, or when the reload time is changed,
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

	// When events are loaded for the fist time, or when reloaded, store the max id.
	useEffect( () => {
		if ( ! events || ! events.length || page !== 1 ) {
			return;
		}

		setEventsMaxId( events[ 0 ].id );
	}, [ page, events ] );

	/**
	 * Function to set reload time to current time,
	 * which will trigger a reload of the events.
	 * This is used as a callback function for child components,
	 * for example for the search button in the search component.
	 */
	const handleReload = () => {
		setEventsReloadTime( Date.now() );
	};

	return (
		<div>
			<EventsSearchFilters
				selectedLogLevels={ selectedLogLevels }
				setSelectedLogLevels={ setSelectedLogLevels }
				selectedMessageTypes={ selectedMessageTypes }
				setSelectedMessageTypes={ setSelectedMessageTypes }
				selectedUsers={ selectedUsers }
				setSelectUsers={ setSelectUsers }
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
				userSuggestions={ userSuggestions }
				setUserSuggestions={ setUserSuggestions }
				searchOptionsLoaded={ searchOptionsLoaded }
				setSearchOptionsLoaded={ setSearchOptionsLoaded }
				pagerSize={ pagerSize }
				setPagerSize={ setPagerSize }
				mapsApiKey={ mapsApiKey }
				setMapsApiKey={ setMapsApiKey }
				page={ page }
				setPage={ setPage }
				onReload={ handleReload }
			/>

			<EventsControlBar
				eventsIsLoading={ eventsIsLoading }
				eventsTotal={ eventsMeta.total }
				eventsMaxId={ eventsMaxId }
				eventsQueryParams={ eventsQueryParams }
				onReload={ handleReload }
			/>

			<EventsList
				eventsIsLoading={ eventsIsLoading }
				events={ events }
				eventsMeta={ eventsMeta }
				page={ page }
				setPage={ setPage }
				mapsApiKey={ mapsApiKey }
			/>
		</div>
	);
}

export default MainGui;
