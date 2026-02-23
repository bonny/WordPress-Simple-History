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
 * Notice shown at the end of the event list when backfilled entries
 * exist and some content was not imported due to the per-type limit.
 *
 * Hidden entirely when all available content was imported successfully.
 */
function BackfilledNotice() {
	const [ typeStats, setTypeStats ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/simple-history/v1/backfill-status' } )
			.then( ( data ) => setTypeStats( data.type_stats ?? null ) )
			.catch( () => {} );
	}, [] );

	// Find types where available > imported (i.e. limit was hit).
	const missed = typeStats
		? Object.entries( typeStats ).filter(
				( [ , s ] ) => s.available > s.imported
		  )
		: [];

	// Don't show notice if everything was imported successfully.
	if ( typeStats && missed.length === 0 ) {
		return null;
	}

	let heading;

	if ( ! typeStats || missed.length === 0 ) {
		heading = __(
			'Your site has even more history to explore.',
			'simple-history'
		);
	} else {
		// Use pre-formatted labels from the REST API (e.g. "247 posts", "12 pages").
		const parts = missed.map( ( [ , s ] ) => s.missed_label );

		// Use Intl.ListFormat for locale-aware list joining (commas, "and", etc.).
		const locale = document.documentElement.lang || 'en';
		const list = new Intl.ListFormat( locale, {
			style: 'long',
			type: 'conjunction',
		} ).format( parts );

		heading = sprintf(
			// translators: %s: list of content types with counts, e.g. "147 more posts and 12 more pages".
			__( 'Backfill %s into your history.', 'simple-history' ),
			list
		);
	}

	return (
		<Notice
			status="info"
			isDismissible={ false }
			className={ 'sh-BackfilledNotice' }
		>
			<p>
				<strong>{ heading }</strong>
			</p>
			<p>
				{ createInterpolateElement(
					__(
						'When first installed, Simple History backfilled up to 100 existing items per content type to give you a head start. All new activity is logged automatically — this only affects older content created before the plugin was active. <PremiumLink>Upgrade to Premium</PremiumLink> to backfill your complete history with no limits.',
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

	// Show backfilled notice on the last page when the last event is backfilled.
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

			{ showBackfilledNotice && <BackfilledNotice /> }

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
