import { DropdownMenu, MenuGroup, MenuItem, Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile, pin } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';
import { EventCopyDetails, EventCopyDetailsDetailed } from './EventCopyDetails';

function EventUnStickMenuItem( { event } ) {
	// Bail if event is not sticky.
	if ( ! event.sticky ) {
		return null;
	}

	const handleUnstick = async () => {
		try {
			await apiFetch( {
				path: `/simple-history/v1/events/${ event.id }/unstick`,
				method: 'POST',
			} );
			// Refresh the page to show updated state
			//	window.location.reload();
		} catch ( error ) {
			// Silently fail - the user will see the event is still sticky
		}
	};

	return (
		<MenuItem onClick={ handleUnstick } icon={ pin }>
			{ __( 'Unstick', 'simple-history' ) }
		</MenuItem>
	);
}

/**
 * The button with three dots that opens a dropdown with actions for the event.
 *
 * @param {Object} props
 * @param {Object} props.event              The event object
 * @param {string} props.eventVariant       The variant of the event ('normal' or 'modal')
 * @param {string} props.eventsAdminPageURL URL to the events admin page
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( {
	event,
	eventVariant,
	eventsAdminPageURL,
} ) {
	// Don't show actions on modal events.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__actions">
			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreHorizontalMobile }
				popoverProps={ {
					placement: 'left-start',
					inline: true,
				} }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<EventDetailsMenuItem
								event={ event }
								eventVariant={ eventVariant }
								onClose={ onClose }
							/>

							<EventCopyDetails event={ event } />

							<EventCopyDetailsDetailed event={ event } />

							<EventCopyLinkMenuItem event={ event } />

							<EventViewMoreSimilarEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
							/>

							<EventUnStickMenuItem event={ event } />
						</MenuGroup>

						<Slot
							name="SimpleHistorySlotEventActionsMenu"
							fillProps={ {
								onClose,
								event,
								eventVariant,
							} }
						/>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
