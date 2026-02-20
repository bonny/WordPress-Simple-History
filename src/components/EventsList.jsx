import {
	ExternalLink,
	__experimentalSpacer as Spacer,
	Notice,
} from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';
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

	// Check if we should show the "oldest backfilled event" notice.
	// Show when on the last page and the last event is a backfilled entry.
	const lastEvent = events?.length ? events[ events.length - 1 ] : null;
	const isOnLastPage = page === totalPages && totalPages > 0;
	const showBackfilledNotice =
		! eventsIsLoading &&
		! hasPremiumAddOn &&
		! isSurroundingEventsMode &&
		isOnLastPage &&
		lastEvent?.backfilled;

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
						__(
							'Viewing #%1$d with %2$d surrounding events',
							'simple-history'
						),
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

			{ showBackfilledNotice && (
				<Notice status="info" isDismissible={ false }>
					<p>
						<strong>
							{ __(
								'You have reached the oldest backfilled event.',
								'simple-history'
							) }
						</strong>
					</p>
					<p>
						{ createInterpolateElement(
							__(
								'The free version backfills up to 100 items per content type. <PremiumLink>Upgrade to Premium</PremiumLink> to backfill more history.',
								'simple-history'
							),
							{
								PremiumLink: (
									<ExternalLink
										href={ getTrackingUrl(
											'https://simple-history.com/add-ons/premium/',
											'premium_events_backfill'
										) }
									/>
								),
							}
						) }
					</p>
				</Notice>
			) }

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
