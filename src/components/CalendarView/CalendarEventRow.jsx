import { LOG_LEVEL_COLORS } from './calendarUtils';

function openModal( eventId ) {
	window.location.hash = '#simple-history/event/' + eventId;
}

export function CalendarEventRow( { event } ) {
	const shEvent = event.resource;
	const color =
		LOG_LEVEL_COLORS[ shEvent?.loglevel ] ?? LOG_LEVEL_COLORS.info;

	return (
		<div
			className="sh-calendar-event-row"
			style={ { borderLeftColor: color } }
			title={ event.title }
			role="button"
			tabIndex={ 0 }
			onClick={ () => openModal( shEvent.id ) }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' ) {
					openModal( shEvent.id );
				}
			} }
		>
			<span className="sh-calendar-event-message">{ event.title }</span>
		</div>
	);
}
