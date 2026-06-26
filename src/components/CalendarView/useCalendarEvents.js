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

	const fetchEvents = useCallback( () => {
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

		return {
			start,
			end,
			filterParams,
		};
	}, [ calView, calendarDate, eventsQueryParams ] );

	useEffect( () => {
		let ignore = false;

		const fetchAllEvents = async () => {
			const { start, end, filterParams } = fetchEvents();

			const params = {
				...filterParams,
				date_from: getUnixTime( start ),
				date_to: getUnixTime( end ),
			};

			// Fetch first page and read total pages from response headers.
			const firstResponse = await apiFetch( {
				path: addQueryArgs( '/simple-history/v1/events', {
					...params,
					per_page: 100,
					page: 1,
				} ),
				parse: false,
			} );

			if ( ! firstResponse.ok ) {
				throw new Error( 'API error' );
			}

			const firstData = await firstResponse.json();
			const allEvents = Array.isArray( firstData )
				? [ ...firstData ]
				: [];

			const totalPages = parseInt(
				firstResponse.headers.get( 'X-WP-TotalPages' ) || '1',
				10
			);

			// Fetch remaining pages sequentially.
			for ( let page = 2; page <= totalPages; page++ ) {
				if ( ignore ) {
					return;
				}

				const pageResponse = await apiFetch( {
					path: addQueryArgs( '/simple-history/v1/events', {
						...params,
						per_page: 100,
						page,
					} ),
					parse: false,
				} );

				if ( pageResponse.ok ) {
					const pageData = await pageResponse.json();

					if ( Array.isArray( pageData ) ) {
						allEvents.push( ...pageData );
					}
				}
			}

			if ( ! ignore ) {
				setEvents( allEvents );
				setGroupedEvents( groupEventsByDay( allEvents ) );
				setIsLoading( false );
			}
		};

		setIsLoading( true );

		fetchAllEvents().catch( () => {
			if ( ! ignore ) {
				setEvents( [] );
				setGroupedEvents( {} );
				setIsLoading( false );
			}
		} );

		return () => {
			ignore = true;
		};
	}, [ fetchEvents ] );

	return { events, groupedEvents, isLoading };
}
