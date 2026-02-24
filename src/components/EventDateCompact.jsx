import { humanTimeDiff } from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Lightweight date display for compact event lists (admin bar, sidebar).
 * Shows a live-updating relative time like "3 min ago".
 *
 * Unlike EventDate, this does not import @wordpress/components,
 * keeping the admin bar bundle small.
 *
 * @param {Object} props
 * @param {Object} props.event Event object with date_local property.
 */
export function EventDateCompact( { event } ) {
	const [ formattedDate, setFormattedDate ] = useState( () =>
		humanTimeDiff( event.date_local )
	);

	useEffect( () => {
		const intervalId = setInterval( () => {
			setFormattedDate( humanTimeDiff( event.date_local ) );
		}, 1000 );

		return () => clearInterval( intervalId );
	}, [ event.date_local ] );

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when">
			<div>{ formattedDate }</div>
		</EventHeaderItem>
	);
}
