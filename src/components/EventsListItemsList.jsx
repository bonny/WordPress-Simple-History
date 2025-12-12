import { clsx } from 'clsx';
import { Event } from './Event';

export function EventsListItemsList( props ) {
	const {
		events,
		prevEventsMaxId,
		mapsApiKey,
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		eventsIsLoading,
		eventsSettingsPageURL,
		eventsAdminPageURL,
		userCanManageOptions,
	} = props;

	// Bail if no events.
	if ( ! events || events.length === 0 ) {
		return null;
	}

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
					mapsApiKey={ mapsApiKey }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					hasPremiumAddOn={ hasPremiumAddOn }
					eventsSettingsPageURL={ eventsSettingsPageURL }
					eventsAdminPageURL={ eventsAdminPageURL }
					userCanManageOptions={ userCanManageOptions }
					isNewAfterFetchNewEvents={ event.id > prevEventsMaxId }
				/>
			) ) }
		</ul>
	);
}
