import { format } from 'date-fns';
import { __, sprintf } from '@wordpress/i18n';
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
							title={ sprintf(
								__( '%d error(s)', 'simple-history' ),
								counts.error
							) }
						/>
					) }
					{ counts.warning > 0 && (
						<span
							className="sh-calendar-dot sh-calendar-dot--warning"
							title={ sprintf(
								__( '%d warning(s)', 'simple-history' ),
								counts.warning
							) }
						/>
					) }
					{ counts.info > 0 && (
						<span
							className="sh-calendar-dot sh-calendar-dot--info"
							title={ sprintf(
								__( '%d info', 'simple-history' ),
								counts.info
							) }
						/>
					) }
				</div>
			) }
			{ children }
		</div>
	);
}
