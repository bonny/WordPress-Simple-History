import { DropdownMenu, Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreVertical } from '@wordpress/icons';
import { applyFilters } from '@wordpress/hooks';

/**
 * Backwards-compatible overflow menu (⋮) that keeps the old
 * SimpleHistorySlotEventsControlBarMenu Slot alive.
 *
 * Old premium versions use <Fill name="SimpleHistorySlotEventsControlBarMenu">
 * to inject Export and Create Entry. Removing this Slot would silently break
 * those installs — users with expired licenses can't update premium.
 *
 * Visibility logic:
 * - Free users: hidden (showPremiumAddonsMenuGroup defaults to true,
 *   meaning no premium is active to set it false).
 * - Old premium: visible (sets showPremiumAddonsMenuGroup to false,
 *   and fills the Slot with real items).
 * - New premium: hidden (sets showOverflowMenu to false, uses inline
 *   button Slots instead).
 *
 * @param {Object} props
 */
export function EventsControlBarOverflowMenu( props ) {
	const { eventsQueryParams, eventsTotal } = props;

	/**
	 * New premium sets this to false to hide the overflow menu
	 * when it uses the new inline button extension points.
	 */
	const showOverflowMenu = applyFilters(
		'SimpleHistory.EventsControlBar.showOverflowMenu',
		true
	);

	/**
	 * Old premium sets this to false to hide promo items.
	 * We reuse it as a signal that premium is active and has
	 * filled the Slot with real items.
	 */
	const showPremiumAddonsMenuGroup = applyFilters(
		'SimpleHistory.showPremiumAddonsMenuGroup',
		true
	);

	// Old premium active: showPremiumAddonsMenuGroup === false, showOverflowMenu === true (default).
	const isOldPremiumActive =
		showPremiumAddonsMenuGroup === false && showOverflowMenu === true;

	// Only show the overflow menu when old premium has filled the Slot.
	if ( ! isOldPremiumActive ) {
		return null;
	}

	return (
		<DropdownMenu
			label={ __( 'More actions', 'simple-history' ) }
			icon={ moreVertical }
			toggleProps={ {
				variant: 'tertiary',
				size: 'compact',
			} }
			className="sh-ControlBarOverflowMenu"
		>
			{ ( { onClose } ) => (
				<Slot
					name="SimpleHistorySlotEventsControlBarMenu"
					fillProps={ {
						onClose,
						eventsQueryParams,
						eventsTotal,
					} }
				/>
			) }
		</DropdownMenu>
	);
}
