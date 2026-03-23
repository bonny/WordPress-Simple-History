import {
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	Spinner,
	__experimentalText as Text,
} from '@wordpress/components';
import { _n, _x, sprintf } from '@wordpress/i18n';
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
						eventsTotal={ eventsTotal }
					/>
				</FlexItem>
			</Flex>
		</div>
	);
}
