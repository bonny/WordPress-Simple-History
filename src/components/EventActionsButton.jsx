import { DropdownMenu, MenuGroup, Slot } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile } from '@wordpress/icons';
import { EventCopyDetails, EventCopyDetailsDetailed } from './EventCopyDetails';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventCopyScreenshotMenuItem } from './EventCopyScreenshotMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';
import { EventSurroundingEventsMenuItem } from './EventSurroundingEventsMenuItem';
import { EventStickMenuItem } from './EventStickMenuItem';
import { EventUnstickMenuItem } from './EventUnstickMenuItem';

/**
 * The button with three dots that opens a dropdown with actions for the event.
 *
 * @param {Object}  props
 * @param {Object}  props.event                The event object
 * @param {string}  props.eventVariant         The variant of the event ('normal' or 'modal')
 * @param {string}  props.eventsAdminPageURL   URL to the events admin page
 * @param {boolean} props.hasPremiumAddOn      Whether the premium add-on is installed
 * @param {boolean} props.userCanManageOptions Whether the user can manage options (is admin)
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( {
	event,
	eventVariant,
	eventsAdminPageURL,
	hasPremiumAddOn,
	userCanManageOptions,
} ) {
	const actionsRef = useRef( null );

	// Don't show actions on modal or dashboard events.
	if ( eventVariant === 'modal' || eventVariant === 'dashboard' ) {
		return null;
	}

	return (
		<div ref={ actionsRef } className="SimpleHistoryLogitem__actions">
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
							<EventCopyLinkMenuItem event={ event } />
						</MenuGroup>

						<MenuGroup>
							<EventCopyDetails event={ event } />
							<EventCopyDetailsDetailed event={ event } />
							<EventCopyScreenshotMenuItem
								event={ event }
								actionsRef={ actionsRef }
							/>
						</MenuGroup>

						<MenuGroup>
							<EventViewMoreSimilarEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
							/>
							<EventSurroundingEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
								userCanManageOptions={ userCanManageOptions }
							/>
						</MenuGroup>

						<MenuGroup>
							<EventUnstickMenuItem
								event={ event }
								onClose={ onClose }
								userCanManageOptions={ userCanManageOptions }
							/>
							<EventStickMenuItem
								event={ event }
								onClose={ onClose }
								hasPremiumAddOn={ hasPremiumAddOn }
							/>
						</MenuGroup>

						<MenuGroup>
							<Slot
								name="SimpleHistorySlotEventActionsMenu"
								fillProps={ {
									onClose,
									event,
									eventVariant,
									userCanManageOptions,
								} }
							/>
						</MenuGroup>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
