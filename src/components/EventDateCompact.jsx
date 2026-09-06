import { useEventRelativeTime } from '../functions';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Lightweight date display for compact event lists (admin bar, sidebar).
 * Shows a live-updating relative time like "3 min ago".
 *
 * Unlike EventDate, this does not import @wordpress/components,
 * keeping the admin bar bundle small.
 *
 * @param {Object} props
 * @param {Object} props.event Event object with date_gmt property.
 */
export function EventDateCompact( { event } ) {
	const formattedDate = useEventRelativeTime( event );

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when">
			<div>{ formattedDate }</div>
		</EventHeaderItem>
	);
}
