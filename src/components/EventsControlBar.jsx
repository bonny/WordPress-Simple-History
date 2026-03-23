import {
	Button,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	Spinner,
	__experimentalText as Text,
} from '@wordpress/components';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { EventsControlBarActionsDropdownMenu } from './EventsControlBarActionsDropdownMenu';

/**
 * Control bar between filters and the events listing,
 * with number of events and actions dropdown.
 *
 * @param {Object} props
 */
export function EventsControlBar( props ) {
	const {
		eventsIsLoading,
		eventsTotal,
		eventsQueryParams,
		hasAnyActiveFilters,
		handleClearFilters,
	} = props;

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
		<div className="sh-EventsControlBar-actions">
			<Flex gap={ 2 }>
				<FlexItem>
					<HStack spacing={ 2 }>
						{ eventsCount }
						{ loadingIndicator }

						{ hasAnyActiveFilters && (
							<Button
								variant="tertiary"
								onClick={ handleClearFilters }
								className="SimpleHistoryFilterDropin-clearFilters"
								size="small"
							>
								{ __( 'Clear filters', 'simple-history' ) }
							</Button>
						) }
					</HStack>
				</FlexItem>

				<FlexItem>
					<EventsControlBarActionsDropdownMenu
						eventsQueryParams={ eventsQueryParams }
						eventsTotal={ eventsTotal }
					/>
				</FlexItem>
			</Flex>
		</div>
	);
}
