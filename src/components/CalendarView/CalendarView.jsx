import { useMemo } from '@wordpress/element';
import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, parse, startOfWeek, getDay } from 'date-fns';
import { enUS } from 'date-fns/locale';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { CalendarToolbar } from './CalendarToolbar';
import { CalendarDayCell } from './CalendarDayCell';
import { CalendarEventRow } from './CalendarEventRow';
import { useCalendarEvents } from './useCalendarEvents';
import './CalendarView.scss';

const localizer = dateFnsLocalizer( {
	format,
	parse,
	startOfWeek: ( date ) => startOfWeek( date, { weekStartsOn: 1 } ),
	getDay,
	locales: { 'en-US': enUS },
} );

export function CalendarView( {
	calView,
	setCalView,
	calendarDate,
	setCalendarDate,
	eventsQueryParams,
} ) {
	const { events, groupedEvents, isLoading } = useCalendarEvents( {
		calView,
		calendarDate,
		eventsQueryParams,
	} );

	const rbcEvents = useMemo(
		() =>
			events.map( ( event ) => ( {
				id: event.id,
				title: event.message,
				start: new Date( event.date_local ),
				end: new Date( event.date_local ),
				resource: event,
			} ) ),
		[ events ]
	);

	const components = useMemo(
		() => ( {
			toolbar: CalendarToolbar,
			dateCellWrapper: ( props ) => (
				<CalendarDayCell { ...props } groupedEvents={ groupedEvents } />
			),
			event: CalendarEventRow,
		} ),
		[ groupedEvents ]
	);

	return (
		<div className="sh-calendar-wrap">
			{ isLoading && (
				<div className="sh-calendar-loading">
					{ /* Loading indicator — events already in state stay visible */ }
				</div>
			) }
			<Calendar
				localizer={ localizer }
				events={ rbcEvents }
				view={ calView }
				onView={ setCalView }
				date={ calendarDate }
				onNavigate={ setCalendarDate }
				onDrillDown={ ( date, view ) => {
					const nextView = view === 'month' ? 'week' : 'day';
					setCalView( nextView );
					setCalendarDate( date );
				} }
				onShowMore={ ( _evts, date ) => {
					setCalView( 'day' );
					setCalendarDate( date );
				} }
				components={ components }
				style={ { minHeight: 600 } }
			/>
		</div>
	);
}

export default CalendarView;
