import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Displays an indicator for events that were backfilled from existing WordPress data.
 * Shows "Backfilled from existing data" as plain text.
 *
 * @param {Object} props
 * @param {Object} props.event - The event object
 */
export function EventImportedIndicator( props ) {
	const { event } = props;

	// Check if this is a backfilled event using the dedicated field from REST API.
	if ( ! event.imported ) {
		return null;
	}

	const text = __( 'Backfilled entry', 'simple-history' );

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__importedEvent">
			{ text }
		</EventHeaderItem>
	);
}
