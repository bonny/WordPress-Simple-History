import { clsx } from 'clsx';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventText } from './EventText';

/**
 * Component for a single event in the list of events.
 *
 * @param {Object} props
 */
export function Event( props ) {
	const { event, variant = 'normal' } = props;
	const { mapsApiKey } = props;

	/*
		erik editor par+erik@earthpeople.se 3:09 pm (för 9 minuter sedan)
		Made POST request to http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin-ajax.php felsokning
		Method	POST
	*/

	const containerClassNames = clsx(
		'SimpleHistoryLogitem',
		`SimpleHistoryLogitem--loglevel-${ event.level }`,
		`SimpleHistoryLogitem--logger-${ event.logger }`,
		`SimpleHistoryLogitem--initiator-${ event.initiator }`
	);

	return (
		<li key={ event.id } className={ containerClassNames }>
			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={ event } />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<EventHeader
					event={ event }
					mapsApiKey={ mapsApiKey }
					eventVariant={ variant }
				/>
				<EventText event={ event } eventVariant={ variant } />
				<EventDetails event={ event } eventVariant={ variant } />
				<EventOccasions event={ event } eventVariant={ variant } />
			</div>
		</li>
	);
}
