import { useMemo } from '@wordpress/element';
import { Calendar, dateFnsLocalizer } from 'react-big-calendar';
import { format, parse, startOfWeek, getDay } from 'date-fns';
import { enUS } from 'date-fns/locale';
import 'react-big-calendar/lib/css/react-big-calendar.css';
import { CalendarToolbar } from './CalendarToolbar';
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
	const components = useMemo(
		() => ( {
			toolbar: CalendarToolbar,
		} ),
		[]
	);

	return (
		<div className="sh-calendar-wrap">
			<Calendar
				localizer={ localizer }
				events={ [] }
				view={ calView }
				onView={ setCalView }
				date={ calendarDate }
				onNavigate={ setCalendarDate }
				onDrillDown={ ( date, view ) => {
					const nextView = view === 'month' ? 'week' : 'day';
					setCalView( nextView );
					setCalendarDate( date );
				} }
				onShowMore={ ( _events, date ) => {
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
