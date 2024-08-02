import { EventDate } from "./EventDate";
import { EventInitiatorName } from "./EventInitiatorName";
import { EventVia } from "./EventVia";

/**
 * Outputs event "meta": name of the event initiator (who), the date, and the via text (if any).
 */
export function EventHeader(props) {
	const { event } = props;

	return (
		<div className="SimpleHistoryLogitem__header">
			<EventInitiatorName event={event} />
			<EventDate event={event} />
			<EventVia event={event} />
		</div>
	);
}
