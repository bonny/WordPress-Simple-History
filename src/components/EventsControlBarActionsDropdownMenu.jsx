import { DropdownMenu, Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile } from '@wordpress/icons';
import { PremiumAddonsPromoMenuGroup } from './PremiumAddonsPromoMenuGroup';

/**
 * Dropdown menu with information about the actions you can do with the events.
 * By default is has "placeholder" items that are promoted to premium features.
 *
 * @param {Object} props
 */
export function EventsControlBarActionsDropdownMenu( props ) {
	const { eventsQueryParams, eventsTotal } = props;

	return (
		<DropdownMenu
			text={ __( 'Event options', 'simple-history' ) }
			label={ __( 'Actions (Export & other tools)', 'simple-history' ) }
			icon={ moreHorizontalMobile } // or moreHorizontal or moreHorizontalMobile
			// Props passed to the toggle button.
			toggleProps={ {
				iconPosition: 'right',
				variant: 'tertiary',
			} }
		>
			{ ( { onClose } ) => (
				<>
					{ /* <MenuGroup>
						<MenuItem onClick={ onClose }>
							Copy link to search
						</MenuItem>
					</MenuGroup> */ }

					<PremiumAddonsPromoMenuGroup
						onCloseDropdownMenu={ onClose }
					/>

					<Slot
						name="SimpleHistorySlotEventsControlBarMenu"
						fillProps={ {
							onClose,
							eventsQueryParams,
							eventsTotal,
						} }
					/>
				</>
			) }
		</DropdownMenu>
	);
}
