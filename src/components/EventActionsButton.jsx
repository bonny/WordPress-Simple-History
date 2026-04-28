import { Button, DropdownMenu, MenuGroup, Slot } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fullscreen, moreHorizontalMobile } from '@wordpress/icons';
import {
	EventCopyDetails,
	EventCopyDetailsDetailed,
	EventCopyDetailsJson,
} from './EventCopyDetails';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventCopyScreenshotMenuItem } from './EventCopyScreenshotMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';
import { EventSurroundingEventsMenuItem } from './EventSurroundingEventsMenuItem';
import { EventStickMenuItem } from './EventStickMenuItem';
import { EventUnstickMenuItem } from './EventUnstickMenuItem';
import { EventReactionQuickButton } from './EventReactions';
import { navigateToEventPermalink } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';

/**
 * Event actions area: quick action buttons (shown on hover) and the
 * three-dot dropdown menu.
 *
 * @param {Object} props
 * @param {Object} props.event          The event object
 * @param {string} props.eventVariant   The variant of the event ('normal' or 'modal')
 * @param {Object} props.reactionState  Reaction state from useEventReactions hook
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( { event, eventVariant, reactionState } ) {
	const { eventsAdminPageURL, hasPremiumAddOn, userCanManageOptions } =
		useEventsSettings();
	const actionsRef = useRef( null );

	// Don't show actions on modal or dashboard events.
	if ( eventVariant === 'modal' || eventVariant === 'dashboard' ) {
		return null;
	}

	return (
		<div ref={ actionsRef } className="SimpleHistoryLogitem__actions">
			{ reactionState && (
				<EventReactionQuickButton
					isUpdating={ reactionState.isUpdating }
					toggleReaction={ reactionState.toggleReaction }
				/>
			) }
			<Button
				icon={ fullscreen }
				label={ __( 'Event details', 'simple-history' ) }
				size="small"
				onClick={ () => navigateToEventPermalink( { event } ) }
			/>

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
							<EventCopyDetailsJson event={ event } />
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
