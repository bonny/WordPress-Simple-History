import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';
import { EventIPAddresses } from './EventIPAddresses';
import { EventVia } from './EventVia';
import { EventBackfilledIndicator } from './EventBackfilledIndicator';
import { EventNetworkFallbackIndicator } from './EventNetworkFallbackIndicator';

/**
 * Outputs event "meta": name of the event initiator (who), the date, and the via text (if any).
 *
 * @param {Object} props
 */
export function EventHeader( props ) {
	const { event, eventVariant, isSurroundingEventsMode } = props;

	return (
		<div className="SimpleHistoryLogitem__header">
			{ isSurroundingEventsMode && (
				<span className="SimpleHistoryLogitem__eventId">
					#{ event.id }
				</span>
			) }

			<EventInitiatorName event={ event } eventVariant={ eventVariant } />

			<EventDate event={ event } eventVariant={ eventVariant } />

			<EventIPAddresses event={ event } eventVariant={ eventVariant } />

			<EventVia event={ event } />
			<EventBackfilledIndicator event={ event } />
			<EventNetworkFallbackIndicator event={ event } />
		</div>
	);
}
