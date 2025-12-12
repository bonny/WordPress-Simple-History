import { __experimentalSpacer as Spacer } from '@wordpress/components';
import { EventsListItemsList } from './EventsListItemsList';
import { EventsListSkeletonList } from './EventsListSkeletonList.jsx';
import { EventsPagination } from './EventsPagination';
import { FetchEventsErrorMessage } from './FetchEventsErrorMessage';
import { FetchEventsNoResultsMessage } from './FetchEventsNoResultsMessage';

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
		eventsSettingsPageURL,
		eventsAdminPageURL,
		eventsLoadingHasErrors,
		eventsLoadingErrorDetails,
		userCanManageOptions,
	} = props;

	const totalPages = eventsMeta.totalPages;

	return (
		<div
			style={ {
				backgroundColor: 'white',
				minHeight: '300px',
				display: 'flex',
				flexDirection: 'column',
				// Make room for divider label that will overlap otherwise.
				paddingTop: '30px',
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
				eventsSettingsPageURL={ eventsSettingsPageURL }
				eventsAdminPageURL={ eventsAdminPageURL }
				userCanManageOptions={ userCanManageOptions }
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
