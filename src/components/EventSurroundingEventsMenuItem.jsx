import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { external } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

/**
 * Menu item that opens surrounding events in a new tab.
 * Shows events chronologically before and after the selected event.
 * Only visible to administrators.
 *
 * The surrounding_count parameter defaults to 5 but can be manually
 * edited in the URL by experienced users.
 *
 * @param {Object}  props
 * @param {Object}  props.event                The event object
 * @param {string}  props.eventsAdminPageURL   URL to the events admin page
 * @param {boolean} props.userCanManageOptions Whether the user can manage options (is admin)
 */
export function EventSurroundingEventsMenuItem( {
	event,
	eventsAdminPageURL,
	userCanManageOptions,
} ) {
	// Only show for administrators.
	if ( ! userCanManageOptions ) {
		return null;
	}

	// Bail if no event ID.
	if ( ! event?.id ) {
		return null;
	}

	const handleClick = () => {
		const surroundingEventsURL = addQueryArgs( eventsAdminPageURL, {
			surrounding_event_id: event.id,
			surrounding_count: 5,
		} );
		// Open in new tab to preserve current search/pagination.
		window.open( surroundingEventsURL, '_blank', 'noopener,noreferrer' );
	};

	return (
		<MenuItem icon={ external } onClick={ handleClick }>
			{ __( 'Show surrounding events', 'simple-history' ) }
		</MenuItem>
	);
}
