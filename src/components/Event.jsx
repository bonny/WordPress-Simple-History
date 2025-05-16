import { clsx } from 'clsx';
import { EventActionsButton } from './EventActionsButton';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventText } from './EventText';
import { EventSeparator } from './EventSeparator';

/**
 * Component for a single event in the list of events.
 *
 * @param {Object} props
 */
export function Event( props ) {
	const {
		event,
		variant = 'normal',
		mapsApiKey,
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		isNewAfterFetchNewEvents,
		eventsSettingsPageURL,
		eventsAdminPageURL,
		prevEvent,
		nextEvent,
		loopIndex,
	} = props;

	const containerClassNames = clsx(
		'SimpleHistoryLogitem',
		`SimpleHistoryLogitem--variant-${ variant }`,
		`SimpleHistoryLogitem--loglevel-${ event.loglevel }`,
		`SimpleHistoryLogitem--logger-${ event.logger }`,
		`SimpleHistoryLogitem--initiator-${ event.initiator }`,
		{
			'SimpleHistoryLogitem--is-sticky': event.sticky,
			'SimpleHistoryLogitem--newRowSinceReload': isNewAfterFetchNewEvents,
		}
	);

	return (
		<li className={ containerClassNames }>
			<EventSeparator
				event={ event }
				eventVariant={ variant }
				prevEvent={ prevEvent }
				nextEvent={ nextEvent }
				loopIndex={ loopIndex }
			/>

			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={ event } />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<EventHeader
					event={ event }
					eventVariant={ variant }
					mapsApiKey={ mapsApiKey }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					hasPremiumAddOn={ hasPremiumAddOn }
				/>

				<EventText event={ event } eventVariant={ variant } />

				<EventDetails event={ event } eventVariant={ variant } />

				<EventOccasions
					event={ event }
					eventVariant={ variant }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					hasPremiumAddOn={ hasPremiumAddOn }
					eventsSettingsPageURL={ eventsSettingsPageURL }
				/>

				<EventActionsButton
					event={ event }
					eventVariant={ variant }
					eventsAdminPageURL={ eventsAdminPageURL }
					hasPremiumAddOn={ hasPremiumAddOn }
				/>
			</div>
		</li>
	);
}
