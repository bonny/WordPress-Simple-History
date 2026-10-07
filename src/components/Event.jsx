import { clsx } from 'clsx';
import { useEventReactions } from './EventReactions';
import { EventRowCompact } from './EventRowCompact';
import { EventRowDetailed } from './EventRowDetailed';
import { EventSeparator } from './EventSeparator';

/**
 * Component for a single event in the list of events.
 *
 * Owns what every view shares — the list item, its class names, the date
 * separator and the reaction state — and hands the row itself to the layout
 * for the current variant. The compact log and the detailed log arrange the
 * same parts differently, so they get a row component each instead of a pile
 * of variant checks in one place.
 *
 * @param {Object} props
 */
export function Event( props ) {
	const {
		event,
		variant = 'normal',
		isNewAfterFetchNewEvents,
		prevEvent,
		isCenterEvent,
		isSurroundingEventsMode,
	} = props;

	// Lives here, not in the row: EventReactions and EventActionsButton both
	// read it, and the actions menu is what writes to it.
	const reactionState = useEventReactions( event );

	const containerClassNames = clsx(
		'SimpleHistoryLogitem',
		`SimpleHistoryLogitem--variant-${ variant }`,
		`SimpleHistoryLogitem--loglevel-${ event.loglevel }`,
		`SimpleHistoryLogitem--logger-${ event.logger }`,
		`SimpleHistoryLogitem--initiator-${ event.initiator }`,
		{
			'SimpleHistoryLogitem--is-sticky': event.sticky,
			'SimpleHistoryLogitem--newRowSinceReload': isNewAfterFetchNewEvents,
			'SimpleHistoryLogitem--is-center-event': isCenterEvent,
		}
	);

	const Row = variant === 'compact' ? EventRowCompact : EventRowDetailed;

	return (
		<li className={ containerClassNames }>
			<EventSeparator
				event={ event }
				eventVariant={ variant }
				prevEvent={ prevEvent }
			/>

			<Row
				event={ event }
				variant={ variant }
				reactionState={ reactionState }
				isSurroundingEventsMode={ isSurroundingEventsMode }
			/>
		</li>
	);
}
