import { format } from 'date-fns';
import { getSeverityCounts } from './calendarUtils';

export function CalendarDayCell( { children, value, groupedEvents } ) {
	const dayKey = format( value, 'yyyy-MM-dd' );
	const dayEvents = groupedEvents[ dayKey ] || [];
	const counts = getSeverityCounts( dayEvents );
	const hasEvents = dayEvents.length > 0;

	return (
		<div className="sh-calendar-day-cell">
			{ hasEvents && (
				<div className="sh-calendar-severity-dots">
					{ counts.error > 0 && (
						<span
							className="sh-calendar-dot sh-calendar-dot--error"
							title={ `${ counts.error } error(s)` }
						/>
					) }
					{ counts.warning > 0 && (
						<span
							className="sh-calendar-dot sh-calendar-dot--warning"
							title={ `${ counts.warning } warning(s)` }
						/>
					) }
					{ counts.info > 0 && (
						<span
							className="sh-calendar-dot sh-calendar-dot--info"
							title={ `${ counts.info } info` }
						/>
					) }
				</div>
			) }
			{ children }
		</div>
	);
}
