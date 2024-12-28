import { __experimentalSpacer as Spacer } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { Event } from './Event';
import { EventsPagination } from './EventsPagination';
import { FetchEventsErrorMessage } from './FetchEventsErrorMessage';
import { FetchEventsNoResultsMessage } from './FetchEventsNoResultsMessage';
import { EventsListSkeletonList } from './EventsListSkeletonList.jsx';

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
			<EventsListSkeletonList
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
		'is-loaded': ! eventsIsLoading,
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
