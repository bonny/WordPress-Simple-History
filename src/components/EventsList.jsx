import {
	__experimentalSpacer as Spacer,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
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
		surroundingEventId,
		surroundingCount,
	} = props;

	const totalPages = eventsMeta.totalPages;
	const isSurroundingEventsMode = Boolean( surroundingEventId );

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
			{ /* Show info notice when viewing surrounding events */ }
			{ isSurroundingEventsMode && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: number of events before/after, 2: event ID */
						__(
							'Showing %1$d events before and after event #%2$d. You can change the count by editing the surrounding_count parameter in the URL.',
							'simple-history'
						),
						surroundingCount || 5,
						surroundingEventId
					) }
				</Notice>
			) }

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
				surroundingEventId={ surroundingEventId }
			/>

			<Spacer margin={ 4 } />

			{ /* Hide pagination when viewing surrounding events */ }
			{ ! isSurroundingEventsMode && (
				<EventsPagination
					page={ page }
					totalPages={ totalPages }
					setPage={ setPage }
				/>
			) }

			<Spacer paddingBottom={ 4 } />
		</div>
	);
}
