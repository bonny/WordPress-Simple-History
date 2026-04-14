import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { positionCenter } from '@wordpress/icons';
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
		// In network admin, open the network admin page so the surrounding
		// events view queries the network event tables. Detect via the
		// localized context, falling back to the current URL path (which
		// contains /wp-admin/network/ when we're on a network admin page).
		const isNetworkAdmin =
			window.simpleHistoryNetworkContext?.isNetworkAdmin ||
			window.location.pathname.includes( '/wp-admin/network/' );

		let baseUrl = eventsAdminPageURL;

		if ( isNetworkAdmin ) {
			if ( window.simpleHistoryNetworkContext?.adminPageUrl ) {
				baseUrl = window.simpleHistoryNetworkContext.adminPageUrl;
			} else {
				// Rewrite /wp-admin/admin.php?page=simple_history_admin_menu_page
				// to the network equivalent.
				baseUrl = eventsAdminPageURL
					.replace(
						'/wp-admin/admin.php',
						'/wp-admin/network/admin.php'
					)
					.replace(
						'simple_history_admin_menu_page',
						'simple_history_network_page'
					);
			}
		}

		const surroundingEventsURL = addQueryArgs( baseUrl, {
			surrounding_event_id: event.id,
			surrounding_count: 5,
		} );
		// Open in new tab to preserve current search/pagination.
		window.open( surroundingEventsURL, '_blank', 'noopener,noreferrer' );
	};

	return (
		<MenuItem icon={ positionCenter } onClick={ handleClick }>
			{ __( 'Show surrounding events', 'simple-history' ) }
		</MenuItem>
	);
}
