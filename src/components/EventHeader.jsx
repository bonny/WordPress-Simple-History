import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';
import { EventIPAddresses } from './EventIPAddresses';
import { EventVia } from './EventVia';

/**
 * Outputs event "meta": name of the event initiator (who), the date, and the via text (if any).
 *
 * @param {Object} props
 */
export function EventHeader( props ) {
	const { event } = props;
	const { mapsApiKey } = props;

	return (
		<div className="SimpleHistoryLogitem__header">
			<EventInitiatorName event={ event } />
			<EventDate event={ event } />
			<EventIPAddresses event={ event } mapsApiKey={ mapsApiKey } />
			<EventVia event={ event } />
		</div>
	);
}
