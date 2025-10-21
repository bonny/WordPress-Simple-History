import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Displays an indicator for events that were imported from existing WordPress data.
 * Shows "Imported from existing data" as plain text.
 *
 * @param {Object} props
 * @param {Object} props.event - The event object
 */
export function EventImportedIndicator( props ) {
	const { event } = props;

	// Check if this is an imported event using the dedicated field from REST API.
	if ( ! event.imported ) {
		return null;
	}

	const text = __( 'Imported from existing data', 'simple-history' );

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__importedEvent">
			{ text }
		</EventHeaderItem>
	);
}
