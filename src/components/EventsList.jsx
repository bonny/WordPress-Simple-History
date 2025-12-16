import { __experimentalSpacer as Spacer, Notice } from '@wordpress/components';
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
	const styles = {
		backgroundColor: 'white',
		minHeight: '300px',
		display: 'flex',
		flexDirection: 'column',
		// Make room for divider label that will overlap otherwise.
		paddingTop: '30px',
	};

	return (
		<div style={ styles }>
			{ /* Show info notice when viewing surrounding events */ }
			{ isSurroundingEventsMode && (
				<Notice status="info" isDismissible={ false }>
					{ sprintf(
						/* translators: 1: event ID, 2: number of surrounding events */
						__( 'Viewing #%1$d with %2$d surrounding events', 'simple-history' ),
						surroundingEventId,
						surroundingCount || 5
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
