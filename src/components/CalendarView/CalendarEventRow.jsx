import { LOG_LEVEL_COLORS } from './calendarUtils';

export function CalendarEventRow( { event } ) {
	const shEvent = event.resource;
	const color =
		LOG_LEVEL_COLORS[ shEvent?.loglevel ] ?? LOG_LEVEL_COLORS.info;

	return (
		<div
			className="sh-calendar-event-row"
			style={ { borderLeftColor: color } }
			title={ event.title }
		>
			<span className="sh-calendar-event-message">{ event.title }</span>
		</div>
	);
}
