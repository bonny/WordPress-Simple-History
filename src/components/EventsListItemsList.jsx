import { clsx } from 'clsx';
import { Event } from './Event';

export function EventsListItemsList( props ) {
	const {
		events,
		prevEventsMaxId,
		eventsIsLoading,
		surroundingEventId,
	} = props;

	if ( ! events || events.length === 0 ) {
		return null;
	}

	const isSurroundingEventsMode = Boolean( surroundingEventId );

	const ulClasses = clsx( {
		SimpleHistoryLogitems: true,
		'is-loading': eventsIsLoading,
		'is-loaded': ! eventsIsLoading,
	} );

	return (
		<ul className={ ulClasses }>
			{ events.map( ( event, index ) => (
				<Event
					key={ `${ event.id }-${ index }` }
					event={ event }
					loopIndex={ index }
					prevEvent={ events[ index - 1 ] }
					nextEvent={ events[ index + 1 ] }
					isNewAfterFetchNewEvents={ event.id > prevEventsMaxId }
					isCenterEvent={ event.id === surroundingEventId }
					isSurroundingEventsMode={ isSurroundingEventsMode }
				/>
			) ) }
		</ul>
	);
}
