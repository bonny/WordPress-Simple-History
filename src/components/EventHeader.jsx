import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';
import { EventIPAddresses } from './EventIPAddresses';
import { EventVia } from './EventVia';
import { EventImportedIndicator } from './EventImportedIndicator';

/**
 * Outputs event "meta": name of the event initiator (who), the date, and the via text (if any).
 *
 * @param {Object} props
 */
export function EventHeader( props ) {
	const { event, eventVariant, hasExtendedSettingsAddOn, hasPremiumAddOn } =
		props;
	const { mapsApiKey } = props;

	return (
		<div className="SimpleHistoryLogitem__header">
			<EventInitiatorName event={ event } eventVariant={ eventVariant } />

			<EventDate event={ event } eventVariant={ eventVariant } />

			<EventIPAddresses
				event={ event }
				mapsApiKey={ mapsApiKey }
				hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				hasPremiumAddOn={ hasPremiumAddOn }
			/>

			<EventVia event={ event } />
			<EventImportedIndicator event={ event } />
		</div>
	);
}
