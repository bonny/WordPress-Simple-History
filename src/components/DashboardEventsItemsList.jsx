import { clsx } from 'clsx';
import { Event } from './Event';

/**
 * Simplified events list for the dashboard widget.
 * Renders events with variant="dashboard" to hide the actions menu.
 */
export function DashboardEventsItemsList( props ) {
	const {
		events,
		eventsIsLoading,
		hasPremiumAddOn,
		hasFailedLoginLimit,
		eventsSettingsPageURL,
		mapsApiKey,
	} = props;

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
					variant="dashboard"
					loopIndex={ index }
					prevEvent={ events[ index - 1 ] }
					nextEvent={ events[ index + 1 ] }
					hasPremiumAddOn={ hasPremiumAddOn }
					hasFailedLoginLimit={ hasFailedLoginLimit }
					eventsSettingsPageURL={ eventsSettingsPageURL }
					mapsApiKey={ mapsApiKey }
				/>
			) ) }
		</ul>
	);
}
