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
import { numberFormatI18n } from '../functions';
import { EventsControlBarOverflowMenu } from './EventsControlBarOverflowMenu';
import { useEventsSettings } from './EventsSettingsContext';
import { ExportButton } from './ExportButton';
import { ShareFilteredViewButton } from './ShareFilteredViewButton';
import { CreateAlertButton } from './CreateAlertButton';
import { CreateLogEntryButton } from './CreateLogEntryButton';
import { EventsSidebarToggle } from './EventsSidebarToggle';
import { EventsViewToggle } from './EventsViewToggle';

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
		newEventsNotifier,
		eventsView,
		onEventsViewChange,
		isSidebarHidden,
		onToggleSidebar,
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

	// Pass the raw number to _n() so it picks the right plural form, but the
	// locale-formatted string to sprintf() so the user sees "187 304", not "187304".
	const eventsTotalFormatted = numberFormatI18n( eventsTotal );

	// This total counts grouped occasions — repeated events collapsed into
	// one, the count the Detailed and Compact lists actually show. Table
	// view renders one row per event instead (selection and export need a
	// stable id per row, so it cannot group), and shows its own total for
	// that. Showing this count alongside it read as a bug: two totals for
	// "the same" filters that never agree, off by whatever the grouping
	// ratio happens to be. Table view has its own total already, so this
	// one is hidden there rather than relabelled — a relabelled count next
	// to a differently-labelled count is still two numbers to reconcile.
	const isTableView = eventsView === 'table';

	const eventsCount =
		eventsTotal && ! isTableView ? (
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
							eventsTotalFormatted
					  )
					: sprintf(
							/* translators: %s: number of events. Events are grouped so similar events are counted as one. */
							_n(
								'%s event',
								'%s events',
								eventsTotal,
								'simple-history'
							),
							eventsTotalFormatted
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
						{ newEventsNotifier }
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
								// Which view is on screen. Passed so a fill
								// can stand down for a view that offers the
								// same action better itself — the table view
								// exports from its own bar, against its own
								// filters, which these params do not carry.
								// Additive: a fill written before this reads
								// undefined and behaves as it always did.
								eventsView,
							} }
						/>

						<ShareFilteredViewButton />

						<EventsViewToggle
							view={ eventsView }
							onChange={ onEventsViewChange }
						/>

						{ /* Beside the view switcher because it is the same
						   kind of choice — how this page is laid out, rather
						   than what it shows. */ }
						<EventsSidebarToggle
							isHidden={ isSidebarHidden }
							onToggle={ onToggleSidebar }
						/>
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
