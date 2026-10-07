import { EventActionsButton } from './EventActionsButton';
import { EventActionLinks } from './EventActionLinks';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventReactions } from './EventReactions';
import { EventText } from './EventText';

/**
 * The full-height event row: avatar in a left column, and a header, the
 * message, its details table and everything else in a second column.
 *
 * Used by the detailed event log, the dashboard widget and the event modal.
 * The compact log has its own row, see EventRowCompact.
 *
 * @param {Object}  props
 * @param {Object}  props.event
 * @param {string}  props.variant                 "normal", "dashboard" or "modal".
 * @param {Object}  props.reactionState           From useEventReactions, shared with the actions menu.
 * @param {boolean} props.isSurroundingEventsMode
 */
export function EventRowDetailed( {
	event,
	variant,
	reactionState,
	isSurroundingEventsMode,
} ) {
	return (
		<>
			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={ event } />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<EventHeader
					event={ event }
					eventVariant={ variant }
					isSurroundingEventsMode={ isSurroundingEventsMode }
				/>

				<EventText event={ event } eventVariant={ variant } />

				{ /* The dashboard widget is too narrow for the details table. */ }
				{ variant !== 'dashboard' && (
					<EventDetails event={ event } eventVariant={ variant } />
				) }

				<EventActionLinks event={ event } />

				<EventReactions { ...reactionState } />

				<EventOccasions event={ event } eventVariant={ variant } />

				<EventActionsButton
					event={ event }
					eventVariant={ variant }
					reactionState={ reactionState }
				/>
			</div>
		</>
	);
}
