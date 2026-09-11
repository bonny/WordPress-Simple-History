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

	// Pager size arrives with the search options. Until then, show a typical
	// page's worth of rows so the list is not empty for the first round trip.
	const skeletonRowsCount = pagerSize.page ?? 10;

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
