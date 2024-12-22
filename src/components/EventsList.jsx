import { __experimentalSpacer as Spacer } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { Event } from './Event';
import { EventsPagination } from './EventsPagination';
import { FetchEventsErrorMessage } from './FetchEventsErrorMessage';
import { FetchEventsNoResultsMessage } from './FetchEventsNoResultsMessage';

/**
 * Render a skeleton list of events while the real events are loading.
 * Only shown when events are loading and there are no events, i.e for the first page load.
 *
 * @param {*} props
 * @returns
 */
function FetchEventsSkeleton( props ) {
	const { eventsIsLoading, events, pagerSize } = props;

	if ( ! eventsIsLoading || events.length > 0 ) {
		return null;
	}

	const skeletonRowsCount = pagerSize.page ?? 0;

	return (
		<div>
			<ul class="SimpleHistoryLogitems">
				{ Array.from( { length: skeletonRowsCount } ).map(
					( _, index ) => (
						<li
							key={ index }
							className="SimpleHistoryLogitem SimpleHistoryLogitem--variant-normal SimpleHistoryLogitem--loglevel-debug SimpleHistoryLogitem--logger-WPHTTPRequestsLogger SimpleHistoryLogitem--initiator-wp_user"
						>
							<div
								className="SimpleHistoryLogitem__firstcol"
								style={ {
									width: 32,
									height: 32,
									borderRadius: '50%',
									backgroundColor: 'var(--sh-color-gray-4)',
								} }
							></div>

							<div className="SimpleHistoryLogitem__secondcol">
								<div
									className="SimpleHistoryLogitem__header"
									style={ {
										backgroundColor:
											'var(--sh-color-gray-4)',
										width: '40%',
										height: '1rem',
									} }
								></div>
								<div
									className="SimpleHistoryLogitem__text"
									style={ {
										backgroundColor:
											'var(--sh-color-gray-4)',
										width: '60%',
										height: '1.25rem',
									} }
								></div>
								<div
									className="SimpleHistoryLogitem__details"
									style={ {
										backgroundColor:
											'var(--sh-color-gray-4)',
										width: '45%',
										height: '3rem',
									} }
								></div>
							</div>
						</li>
					)
				) }
			</ul>
		</div>
	);
}

/**
 * Renders the main list of events.
 *
 * @param {Object} props
 */
export function EventsList( props ) {
	const {
		events,
		page,
		pagerSize,
		setPage,
		eventsIsLoading,
		eventsMeta,
		prevEventsMaxId,
		mapsApiKey,
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		eventsLoadingHasErrors,
		eventsLoadingErrorDetails,
	} = props;
	const totalPages = eventsMeta.totalPages;

	return (
		<div
			style={ {
				backgroundColor: 'white',
				minHeight: '300px',
				display: 'flex',
				flexDirection: 'column',
			} }
		>
			<FetchEventsSkeleton
				eventsIsLoading={ eventsIsLoading }
				pagerSize={ pagerSize }
				events={ events }
			/>

			<FetchEventsNoResultsMessage
				eventsIsLoading={ eventsIsLoading }
				events={ events }
			/>

			<FetchEventsErrorMessage
				eventsLoadingHasErrors={ eventsLoadingHasErrors }
				eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
			/>

			<EventsListItemsList
				eventsIsLoading={ eventsIsLoading }
				events={ events }
				prevEventsMaxId={ prevEventsMaxId }
				mapsApiKey={ mapsApiKey }
				hasPremiumAddOn={ hasPremiumAddOn }
				hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
			/>

			<Spacer margin={ 4 } />

			<EventsPagination
				page={ page }
				totalPages={ totalPages }
				setPage={ setPage }
			/>

			<Spacer paddingBottom={ 4 } />
		</div>
	);
}

function EventsListItemsList( props ) {
	const {
		events,
		prevEventsMaxId,
		mapsApiKey,
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		eventsIsLoading,
	} = props;

	// Bail if no events.
	if ( ! events || events.length === 0 ) {
		return null;
	}

	const ulClasses = clsx( {
		SimpleHistoryLogitems: true,
		'is-loading': eventsIsLoading,
	} );

	return (
		<ul className={ ulClasses }>
			{ events.map( ( event ) => (
				<Event
					key={ event.id }
					event={ event }
					mapsApiKey={ mapsApiKey }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					hasPremiumAddOn={ hasPremiumAddOn }
					isNewAfterFetchNewEvents={ event.id > prevEventsMaxId }
				/>
			) ) }
		</ul>
	);
}
