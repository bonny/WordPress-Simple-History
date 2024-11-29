import {
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	Spinner,
	__experimentalText as Text,
	withFilters,
} from '@wordpress/components';
import { _n, _x, sprintf } from '@wordpress/i18n';
import { EventsControlBarActionsDropdownMenu } from './EventsControlBarActionsDropdownMenu';

/**
 * This will no be rendered/called if there is a filter in use,
 * unless the filter calls the component.
 *
 * @param {Object} props
 */
const EmptyFilterComponent = ( props ) => {
	console.log( 'EmptyFilterComponent, props', props );
	return <></>;
};

// Based on solution here:
// https://nickdiego.com/a-primer-on-wordpress-slotfill-technology/
// Filter can only be called one time.
const EventsControlBarSlotfillsFilter = withFilters( 'SimpleHistory.Settings' )(
	EmptyFilterComponent
);

/**
 * Control bar at the top of the events listing
 * with number of events, reload button, more actions like export,
 * and so on.
 *
 * @param {Object} props
 */
export function EventsControlBar( props ) {
	const { eventsIsLoading, eventsTotal, eventsQueryParams } = props;

	const loadingIndicator = eventsIsLoading ? (
		<Text>
			<Spinner />

			{ _x(
				'Loading…',
				'Message visible while waiting for log to load from server the first time',
				'simple-history'
			) }
		</Text>
	) : null;

	const eventsCount = eventsTotal ? (
		<Text>
			{ sprintf(
				/* translators: %s: number of events */
				_n( '%s event', '%s events', eventsTotal, 'simple-history' ),
				eventsTotal
			) }
		</Text>
	) : null;

	return (
		<>
			<EventsControlBarSlotfillsFilter { ...props } />

			<div
				style={ {
					background: 'white',
					padding: '6px 12px',
				} }
			>
				<Flex gap={ 2 }>
					<FlexItem>
						<HStack spacing={ 2 }>
							{ eventsCount }
							{ loadingIndicator }
						</HStack>
					</FlexItem>

					<FlexItem>
						<EventsControlBarActionsDropdownMenu
							eventsQueryParams={ eventsQueryParams }
						/>
					</FlexItem>
				</Flex>
			</div>
		</>
	);
}
