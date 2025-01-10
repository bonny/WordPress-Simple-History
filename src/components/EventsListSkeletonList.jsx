import { EventListSkeletonEventsItem } from './EventListSkeletonEventsItem';

/**
 * Render a skeleton list of events while the real events are loading.
 * Only shown when events are loading and there are no events, i.e for the first page load.
 *
 * @param {*} props
 * @return {null|*} Nothing or the skeleton list.
 */
export function EventsListSkeletonList( props ) {
	const { eventsIsLoading, events, pagerSize } = props;

	if ( ! eventsIsLoading || events.length > 0 ) {
		return null;
	}

	const skeletonRowsCount = pagerSize.page ?? 0;

	return (
		<div>
			<ul className="SimpleHistoryLogitems">
				{ Array.from( { length: skeletonRowsCount } ).map(
					( _, index ) => (
						<EventListSkeletonEventsItem
							key={ index }
							index={ index }
						/>
					)
				) }
			</ul>
		</div>
	);
}
