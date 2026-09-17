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
import { EventHideMessageTypeMenuItem } from './EventHideMessageTypeMenuItem';
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
 * @param {Object} props.event              The event object
 * @param {string} props.eventVariant       The variant of the event ('normal' or 'modal')
 * @param {Object} props.reactionState      Reaction state from useEventReactions hook
 * @param {string} [props.dropdownLabel]    Accessible name for the dropdown
 *                                          toggle button. Defaults to the
 *                                          generic "Actions…" used in the log
 *                                          rows, where the surrounding row
 *                                          already gives the button context. A
 *                                          caller whose rows read alike without
 *                                          that context (a table row) should
 *                                          pass one that identifies the row.
 * @param {Object} [props.settingsOverride] Values to use instead of
 *                                          useEventsSettings()'s own lookup,
 *                                          keyed eventsAdminPageURL/
 *                                          hasPremiumAddOn/userCanManageOptions.
 *                                          A caller built into a *different*
 *                                          webpack bundle than this file —
 *                                          Premium imports this component by
 *                                          relative source path, so its build
 *                                          compiles in its own, disconnected
 *                                          copy of EventsSettingsContext —
 *                                          renders under no matching
 *                                          Provider, so the hook's own
 *                                          test/Storybook fallback (all
 *                                          false/undefined) would otherwise
 *                                          apply here too. Individual keys
 *                                          left out still fall back to the
 *                                          hook.
 * @param {Object} [props.popoverProps]     Replaces the dropdown's default
 *                                          `popoverProps` entirely rather
 *                                          than merging with it. The default
 *                                          renders `inline` — in normal DOM
 *                                          flow next to the toggle, which is
 *                                          what a log row wants (it never
 *                                          scrolls or transforms). A caller
 *                                          whose rows live inside a
 *                                          transformed, scrolling ancestor —
 *                                          a virtualised table row uses
 *                                          `transform` for its position and
 *                                          an `overflow` container for
 *                                          scrolling, both of which trap an
 *                                          inline popover behind or clipped
 *                                          by sibling rows — should drop
 *                                          `inline` so it portals out to
 *                                          `Popover.Slot` (or `document.body`
 *                                          with none rendered) instead.
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( {
	event,
	eventVariant,
	reactionState,
	dropdownLabel,
	settingsOverride,
	popoverProps,
} ) {
	const settingsFromContext = useEventsSettings();
	const { eventsAdminPageURL, hasPremiumAddOn, userCanManageOptions } = {
		...settingsFromContext,
		...settingsOverride,
	};
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
				label={ dropdownLabel || __( 'Actions…', 'simple-history' ) }
				icon={ moreHorizontalMobile }
				popoverProps={
					popoverProps || {
						placement: 'left-start',
						inline: true,
					}
				}
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
							<EventHideMessageTypeMenuItem
								event={ event }
								onClose={ onClose }
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
