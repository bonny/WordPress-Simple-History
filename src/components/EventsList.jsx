import apiFetch from '@wordpress/api-fetch';
import {
	ExternalLink,
	__experimentalSpacer as Spacer,
	Notice,
} from '@wordpress/components';
import {
	createInterpolateElement,
	useEffect,
	useState,
} from '@wordpress/element';
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

	// Fetch backfill status when the notice becomes visible.
	const [ backfillTypeStats, setBackfillTypeStats ] = useState( null );
	useEffect( () => {
		if ( ! showBackfilledNotice ) {
			return;
		}

		apiFetch( { path: '/simple-history/v1/backfill-status' } )
			.then( ( data ) => setBackfillTypeStats( data.type_stats ?? null ) )
			.catch( () => {} );
	}, [ showBackfilledNotice ] );

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
							{ ( () => {
								if ( ! backfillTypeStats ) {
									return __(
										"Some of your existing content wasn't imported into history.",
										'simple-history'
									);
								}

								// Find types where available > imported (i.e. limit was hit).
								const missed = Object.entries(
									backfillTypeStats
								).filter(
									( [ , s ] ) => s.available > s.imported
								);

								if ( missed.length === 0 ) {
									return __(
										"You're seeing all of your imported history.",
										'simple-history'
									);
								}

								const parts = missed.map(
									( [ type, s ] ) =>
										`${
											s.available - s.imported
										} ${ type }s`
								);

								const list =
									parts.length === 1
										? parts[ 0 ]
										: parts.slice( 0, -1 ).join( ', ' ) +
										  ' ' +
										  __( 'and', 'simple-history' ) +
										  ' ' +
										  parts[ parts.length - 1 ];

								return sprintf(
									// translators: %s: list of content types with counts, e.g. "247 posts and 12 pages".
									__(
										'%s are missing from your history.',
										'simple-history'
									),
									list
								);
							} )() }
						</strong>
					</p>
					<p>
						{ createInterpolateElement(
							__(
								'When Simple History was installed, it automatically imported your existing WordPress content. The free version imports up to 100 items per type — <PremiumLink>Upgrade to Premium</PremiumLink> to import everything.',
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
