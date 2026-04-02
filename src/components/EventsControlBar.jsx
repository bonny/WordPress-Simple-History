import {
	Flex,
	FlexItem,
	Slot,
	__experimentalHStack as HStack,
	Spinner,
	__experimentalText as Text,
} from '@wordpress/components';
import { _n, _x, sprintf } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { EventsControlBarOverflowMenu } from './EventsControlBarOverflowMenu';
import { useEventsSettings } from './EventsSettingsContext';
import { ExportButton } from './ExportButton';
import { ShareFilteredViewButton } from './ShareFilteredViewButton';
import { CreateAlertButton } from './CreateAlertButton';
import { CreateLogEntryButton } from './CreateLogEntryButton';

/**
 * Control bar between filters and the events listing,
 * with number of events and action buttons.
 *
 * @param {Object} props
 */
export function EventsControlBar( props ) {
	const {
		eventsIsLoading,
		eventsTotal,
		eventsQueryParams,
		hasAnyActiveFilters,
	} = props;

	const { alertsPageURL, userCanManageOptions, searchOptionsLoaded } =
		useEventsSettings();

	/**
	 * Filter to show/hide the premium promo buttons (Export, Create Alert, Create Log Entry).
	 * New premium sets this to false and renders its own real buttons.
	 */
	const showPromoButtons = applyFilters(
		'SimpleHistory.EventsControlBar.showPromoButtons',
		true
	);

	/**
	 * Old premium sets showPremiumAddonsMenuGroup to false.
	 * When that happens, hide promo buttons too — old premium handles
	 * Export and Create Entry via the overflow Slot instead.
	 */
	const showPremiumAddonsMenuGroup = applyFilters(
		'SimpleHistory.showPremiumAddonsMenuGroup',
		true
	);

	const shouldShowPromoButtons =
		showPromoButtons && showPremiumAddonsMenuGroup !== false;

	// Show spinner + text on first load (no count yet),
	// just the spinner on subsequent reloads (count already visible).
	const loadingIndicator = eventsIsLoading ? (
		<>
			<Spinner style={ { margin: 0 } } />
			{ ! eventsTotal && (
				<Text as="span">
					{ _x(
						'Loading…',
						'Message visible while waiting for log to load from server the first time',
						'simple-history'
					) }
				</Text>
			) }
		</>
	) : null;

	const eventsCount = eventsTotal ? (
		<Text as="span">
			{ hasAnyActiveFilters
				? sprintf(
						/* translators: %s: number of matching events */
						_n(
							'%s matching event',
							'%s matching events',
							eventsTotal,
							'simple-history'
						),
						eventsTotal
				  )
				: sprintf(
						/* translators: %s: number of events. Events are grouped so similar events are counted as one. */
						_n(
							'%s event',
							'%s events',
							eventsTotal,
							'simple-history'
						),
						eventsTotal
				  ) }
		</Text>
	) : null;

	return (
		<div className="sh-EventsControlBar-actions">
			<Flex
				gap={ 2 }
				justify="space-between"
				align="center"
				wrap={ false }
			>
				<FlexItem>
					<HStack spacing={ 2 } wrap={ false }>
						{ eventsCount }
						{ loadingIndicator }
					</HStack>
				</FlexItem>

				<FlexItem>
					<HStack
						spacing={ 1 }
						wrap={ false }
						className="sh-ControlBarButtons"
						style={ {
							opacity: searchOptionsLoaded ? 1 : 0,
							transition: 'opacity 0.15s ease-in',
						} }
					>
						{ shouldShowPromoButtons && <ExportButton /> }

						{ shouldShowPromoButtons && (
							<CreateAlertButton
								hasActiveFilters={ hasAnyActiveFilters }
							/>
						) }

						{ shouldShowPromoButtons && <CreateLogEntryButton /> }

						{ /* Extension point for premium inline buttons.
							     Premium fills this with real Export, Create Alert,
							     and Create Log Entry buttons. */ }
						<Slot
							name="SimpleHistorySlotControlBarButtons"
							fillProps={ {
								eventsQueryParams,
								eventsTotal,
								hasAnyActiveFilters,
								alertsPageURL,
								userCanManageOptions,
							} }
						/>

						<ShareFilteredViewButton />
					</HStack>
				</FlexItem>

				{ /* Backwards-compatible overflow menu with the old Slot.
				     Old premium versions inject Export + Create Entry here
				     via <Fill name="SimpleHistorySlotEventsControlBarMenu">.
				     New premium hides this via the showOverflowMenu filter. */ }
				<EventsControlBarOverflowMenu
					eventsQueryParams={ eventsQueryParams }
					eventsTotal={ eventsTotal }
				/>
			</Flex>
		</div>
	);
}
