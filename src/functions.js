import { LOGLEVELS_OPTIONS } from './constants';
import { useState, useEffect } from '@wordpress/element';
import { format } from 'date-fns';

/**
 * Generate API query object based on selected filters.
 *
 * @param {Object} props
 * @param {Array}  props.selectedLogLevels
 * @param {Array}  props.selectedMessageTypes
 * @param {Array}  props.selectedUsersWithId
 * @param {Array}  props.selectedInitiator
 * @param {Array}  props.selectedContextFilters
 * @param {string} props.enteredSearchText
 * @param {string} props.selectedDateOption
 * @param {Date}   props.selectedCustomDateFrom
 * @param {Date}   props.selectedCustomDateTo
 * @param {number} props.page
 * @param {Object} props.pagerSize
 * @return {Object} API query object.
 */
export function generateAPIQueryParams( props ) {
	const {
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
	} = props;

	// Set pager size depending on if page or dashboard.
	// window.pagenow = 'dashboard_page_simple_history_page' | 'dashboard'
	let perPage = pagerSize.page;
	if ( window.pagenow === 'dashboard' ) {
		perPage = pagerSize.dashboard;
	}

	// Create query params based on selected filters.
	const eventsQueryParams = {
		page,
		per_page: perPage,
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
		],
	};

	if ( enteredSearchText ) {
		eventsQueryParams.search = enteredSearchText;
	}

	if ( selectedDateOption ) {
		if ( selectedDateOption === 'customRange' ) {
			eventsQueryParams.date_from = format(
				selectedCustomDateFrom,
				'yyyy-MM-dd'
			);
			eventsQueryParams.date_to = format(
				selectedCustomDateTo,
				'yyyy-MM-dd'
			);
		} else {
			eventsQueryParams.dates = selectedDateOption;
		}
	}

	if ( selectedLogLevels.length ) {
		// Values in selectedLogLevels are the labels of the log levels, not the values we can use in the API.
		// Use the LOGLEVELS_OPTIONS to find the value for the translated label.
		const selectedLogLevelsValues = [];
		selectedLogLevels.forEach( ( selectedLogLevel ) => {
			const logLevelOption = LOGLEVELS_OPTIONS.find(
				( logLevelOptionLocal ) =>
					logLevelOptionLocal.label === selectedLogLevel
			);

			if ( logLevelOption ) {
				selectedLogLevelsValues.push( logLevelOption.value );
			}
		} );

		if ( selectedLogLevelsValues.length ) {
			eventsQueryParams.loglevels = selectedLogLevelsValues;
		}
	}

	if ( selectedMessageTypes.length ) {
		// Array with strings with the message types.
		const selectedMessageTypesValues = [];

		selectedMessageTypes.forEach( ( selectedMessageTypesValue ) => {
			selectedMessageTypesValue.search_options.forEach(
				( searchOption ) => {
					selectedMessageTypesValues.push( searchOption );
				}
			);
		} );

		eventsQueryParams.messages = selectedMessageTypesValues.join( ',' );
	}

	// Add selected users ids to query params.
	// selectedUsers is an array of strings with the user name and email (not id).
	// ie. ["john doe (john@example.com)", ...]
	// eventsQueryParams.users should be an array of user ids.
	if ( selectedUsersWithId.length ) {
		// Array with user ids.
		const selectedUsersValues = selectedUsersWithId.map(
			( selectedUserWithId ) => {
				return selectedUserWithId.id;
			}
		);

		eventsQueryParams.users = selectedUsersValues;
	}

	// Add selected initiators to query params.
	if ( selectedInitiator.length > 0 ) {
		const selectedInitiatorValues = selectedInitiator.map(
			( initiator ) => {
				return initiator.initiator_key || initiator.value;
			}
		);
		eventsQueryParams.initiator = selectedInitiatorValues;
	}

	// Add selected context filters to query params.
	// selectedContextFilters is a string with newline-separated "key:value" pairs.
	// Convert to object format: { "key": "value", "key2": "value2" }
	if ( selectedContextFilters && selectedContextFilters.trim().length > 0 ) {
		const contextFiltersObject = {};
		// Split by newline, trim each line, filter empty lines
		const filterLines = selectedContextFilters
			.split( '\n' )
			.map( ( line ) => line.trim() )
			.filter( ( line ) => line.length > 0 );

		filterLines.forEach( ( contextFilter ) => {
			// Split on first colon only, in case value contains colons
			const colonIndex = contextFilter.indexOf( ':' );
			if ( colonIndex > 0 ) {
				const key = contextFilter.substring( 0, colonIndex ).trim();
				const value = contextFilter.substring( colonIndex + 1 ).trim();
				if ( key && value ) {
					contextFiltersObject[ key ] = value;
				}
			}
		} );

		if ( Object.keys( contextFiltersObject ).length > 0 ) {
			eventsQueryParams.context_filters = contextFiltersObject;
		}
	}

	// Check if there are any search options, besides date.
	// Anything selected besides date will disable sticky events.
	const hasSearchOptions =
		enteredSearchText ||
		selectedLogLevels.length ||
		selectedMessageTypes.length ||
		selectedUsersWithId.length ||
		selectedInitiator.length > 0 ||
		( selectedContextFilters && selectedContextFilters.trim().length > 0 );

	// If first page and no search options then include sticky events.
	if ( page === 1 && ! hasSearchOptions ) {
		eventsQueryParams.include_sticky = true;
	}

	return eventsQueryParams;
}

/**
 * Redirect to event permalink.
 *
 * @param {Object} props
 * @param {Object} props.event
 */
export function navigateToEventPermalink( { event } ) {
	// Pushstate does not trigger hashchange event, so we need to do it manually.
	window.location.hash = `#simple-history/event/${ event.id }`;
}

/**
 * Custom hook to get the URL fragment.
 *
 * Based on solution:
 * https://stackoverflow.com/questions/58442168/why-useeffect-doesnt-run-on-window-location-pathname-changes/58443076#58443076
 */
export const useURLFragment = () => {
	const [ fragment, setFragment ] = useState( window.location.hash );

	const listenToPopstate = () => {
		setFragment( window.location.hash );
	};

	useEffect( () => {
		window.addEventListener( 'popstate', listenToPopstate );

		return () => {
			window.removeEventListener( 'popstate', listenToPopstate );
		};
	}, [] );

	return fragment;
};

/**
 * Random function from https://stackoverflow.com/a/7228322
 *
 * @param {number} min
 * @param {number} max
 * @return {number} Random number between min and max.
 */
export function randomIntFromInterval( min, max ) {
	return Math.floor( Math.random() * ( max - min + 1 ) + min );
}
