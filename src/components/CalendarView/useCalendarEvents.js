import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { getUnixTime } from 'date-fns';
import { getPeriodRange, groupEventsByDay } from './calendarUtils';

export function useCalendarEvents( {
	calView,
	calendarDate,
	eventsQueryParams,
} ) {
	const [ events, setEvents ] = useState( [] );
	const [ groupedEvents, setGroupedEvents ] = useState( {} );
	const [ isLoading, setIsLoading ] = useState( false );

	const fetchEvents = useCallback( async () => {
		setIsLoading( true );

		const { start, end } = getPeriodRange( calendarDate, calView );

		// Strip date/pagination params from the base query and apply the calendar period.
		const {
			date_from: _dateFrom,
			date_to: _dateTo,
			dates: _dates,
			months: _months,
			lastdays: _lastdays,
			page: _page,
			pager_size: _pagerSize,
			...filterParams
		} = eventsQueryParams;

		try {
			const response = await apiFetch( {
				path: addQueryArgs( '/simple-history/v1/events', {
					...filterParams,
					date_from: getUnixTime( start ),
					date_to: getUnixTime( end ),
					per_page: 500,
				} ),
			} );

			const fetched = Array.isArray( response )
				? response
				: response?.data ?? [];

			setEvents( fetched );
			setGroupedEvents( groupEventsByDay( fetched ) );
		} catch ( _error ) {
			setEvents( [] );
			setGroupedEvents( {} );
		} finally {
			setIsLoading( false );
		}
	}, [ calView, calendarDate, eventsQueryParams ] );

	useEffect( () => {
		fetchEvents();
	}, [ fetchEvents ] );

	return { events, groupedEvents, isLoading };
}
