import { DropdownMenu, MenuGroup, Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile } from '@wordpress/icons';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';
import { EventCopyDetails } from './EventCopyDetails';

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

							<EventCopyLinkMenuItem event={ event } />

							<EventViewMoreSimilarEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
							/>
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
