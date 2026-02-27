import apiFetch from '@wordpress/api-fetch';
import { Spinner, __experimentalText as Text } from '@wordpress/components';
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _x, sprintf, _n } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Icon, chartBar } from '@wordpress/icons';
import { FetchEventsErrorMessage } from './FetchEventsErrorMessage';
import { FetchEventsNoResultsMessage } from './FetchEventsNoResultsMessage';
import { DashboardEventsItemsList } from './DashboardEventsItemsList';

/**
 * Skeleton placeholder rows shown while events are loading.
 *
 * @param {Object} props
 * @param {number} props.count Number of skeleton rows.
 */
function SkeletonEvents( { count = 5 } ) {
	return (
		<ul className="sh-DashboardWidget-skeleton">
			{ Array.from( { length: count } ).map( ( _, i ) => (
				<li key={ i } className="sh-DashboardWidget-skeleton__row">
					<div className="sh-DashboardWidget-skeleton__avatar" />
					<div className="sh-DashboardWidget-skeleton__content">
						<div className="sh-DashboardWidget-skeleton__line sh-DashboardWidget-skeleton__line--short" />
						<div className="sh-DashboardWidget-skeleton__line sh-DashboardWidget-skeleton__line--long" />
					</div>
				</li>
			) ) }
		</ul>
	);
}

/**
 * Simplified events widget for the WordPress dashboard.
 * Replaces the full EventsGUI to keep the dashboard compact:
 * no filters, no control bar, no event action menus, no pagination.
 */
export function DashboardEventsWidget() {
	const [ events, setEvents ] = useState( [] );
	const [ eventsIsLoading, setEventsIsLoading ] = useState( true );
	const [ eventsLoadingHasErrors, setEventsLoadingHasErrors ] =
		useState( false );
	const [ eventsLoadingErrorDetails, setEventsLoadingErrorDetails ] =
		useState( { errorCode: undefined, errorMessage: undefined } );
	const [ eventsAdminPageURL, setEventsAdminPageURL ] = useState( '' );
	const [ pagerSize, setPagerSize ] = useState( null );
	const [ hasPremiumAddOn, setHasPremiumAddOn ] = useState( false );
	const [ stats, setStats ] = useState( null );
	const contentRef = useRef( null );
	const prevHeightRef = useRef( 0 );

	// Animate any content height change (skeleton resize, events loading, etc.).
	useLayoutEffect( () => {
		const el = contentRef.current;
		if ( ! el ) {
			return;
		}

		const toHeight = el.scrollHeight;
		const fromHeight = prevHeightRef.current;
		prevHeightRef.current = toHeight;

		// Skip first render or no meaningful change.
		if ( ! fromHeight || Math.abs( fromHeight - toHeight ) < 1 ) {
			return;
		}

		// Pin to previous height.
		el.style.transition = 'none';
		el.style.height = fromHeight + 'px';
		el.style.overflow = 'hidden';

		// Force reflow.
		// eslint-disable-next-line no-unused-expressions
		el.offsetHeight;

		// Animate to new height.
		el.style.transition = 'height 0.3s ease';
		el.style.height = toHeight + 'px';

		const handleEnd = () => {
			el.style.height = '';
			el.style.overflow = '';
			el.style.transition = '';
			el.removeEventListener( 'transitionend', handleEnd );
		};
		el.addEventListener( 'transitionend', handleEnd );
	} );

	// Fetch search options on mount to get pager size and admin page URL.
	useEffect( () => {
		apiFetch( { path: '/simple-history/v1/search-options' } )
			.then( ( response ) => {
				setPagerSize( response.pager_size );
				setEventsAdminPageURL( response.events_admin_page_url || '' );
				setHasPremiumAddOn(
					response.addons?.has_premium_add_on || false
				);
				if ( response.stats ) {
					setStats( response.stats );
				}
			} )
			.catch( () => {} );
	}, [] );

	// Fetch events once pager size is available.
	const loadEvents = useCallback( async () => {
		if ( ! pagerSize ) {
			return;
		}

		setEventsIsLoading( true );

		try {
			const eventsResponse = await apiFetch( {
				path: addQueryArgs( '/simple-history/v1/events', {
					per_page: pagerSize.dashboard,
					_fields: [
						'id',
						'logger',
						'date_local',
						'date_gmt',
						'message',
						'message_html',
						'message_key',
						'details_data',
						'details_html',
						'loglevel',
						'occasions_id',
						'subsequent_occasions_count',
						'initiator',
						'initiator_data',
						'ip_addresses',
						'via',
						'permalink',
						'sticky',
						'sticky_appended',
						'backfilled',
						'action_links',
					],
				} ),
				parse: false,
			} );

			const eventsJson = await eventsResponse.json();
			setEvents( eventsJson );
		} catch ( error ) {
			setEventsLoadingHasErrors( true );

			const errorDetails = {
				code: null,
				statusText: null,
				bodyJson: null,
				bodyText: null,
			};

			if ( error.headers && error.status && error.statusText ) {
				const contentType = error.headers.get( 'Content-Type' );
				errorDetails.code = error.status;
				errorDetails.statusText = error.statusText;

				if (
					contentType &&
					contentType.includes( 'application/json' )
				) {
					errorDetails.bodyJson = await error.json();
				} else {
					errorDetails.bodyText = await error.text();
				}
			} else {
				errorDetails.bodyText = __( 'Unknown error', 'simple-history' );
			}

			setEventsLoadingErrorDetails( errorDetails );
		} finally {
			setEventsIsLoading( false );
		}
	}, [ pagerSize ] );

	useEffect( () => {
		loadEvents();
	}, [ loadEvents ] );

	const handleSearchSubmit = ( evt ) => {
		evt.preventDefault();
		const searchValue =
			evt.target.elements[ 'sh-dashboard-search' ].value.trim();
		if ( searchValue && eventsAdminPageURL ) {
			window.location.href = addQueryArgs( eventsAdminPageURL, {
				q: searchValue,
			} );
		} else if ( eventsAdminPageURL ) {
			window.location.href = eventsAdminPageURL;
		}
	};

	return (
		<div className="sh-DashboardWidget">
			{ /* Stats row: skeleton placeholder while loading, real data when ready. */ }
			<div className="sh-DashboardWidget-stats">
				{ stats ? (
					<>
						<Icon icon={ chartBar } size={ 16 } />
						<span>
							{ sprintf(
								/* translators: 1: number of events today */
								_n(
									'%s event today',
									'%s events today',
									stats.num_events_today,
									'simple-history'
								),
								stats.num_events_today
							) }
						</span>
						<span className="sh-DashboardWidget-stats__separator">
							&middot;
						</span>
						<span>
							{ sprintf(
								/* translators: 1: number of events last 7 days */
								_n(
									'%s this week',
									'%s this week',
									stats.num_events_last_7_days,
									'simple-history'
								),
								stats.num_events_last_7_days
							) }
						</span>
					</>
				) : (
					<div className="sh-DashboardWidget-skeleton__line sh-DashboardWidget-skeleton__line--stats" />
				) }
			</div>

			{ /* Search row: always visible, form works even before admin URL loads. */ }
			<div className="sh-DashboardWidget-searchRow">
				<div className="sh-DashboardWidget-searchRow__row">
					<form
						className="sh-DashboardWidget-search"
						onSubmit={ handleSearchSubmit }
					>
						<input
							type="search"
							name="sh-dashboard-search"
							placeholder={ __(
								'Search users, plugins, posts…',
								'simple-history'
							) }
							className="sh-DashboardWidget-search__input"
						/>
						<button type="submit" className="button">
							{ __( 'Search all events', 'simple-history' ) }
						</button>
					</form>
					{ eventsAdminPageURL ? (
						<a
							href={ addQueryArgs( eventsAdminPageURL, {
								'show-filters': 1,
							} ) }
							className="sh-DashboardWidget-searchRow__filtersLink"
						>
							{ __( 'Show search options', 'simple-history' ) }
						</a>
					) : (
						<span className="sh-DashboardWidget-searchRow__filtersLink is-placeholder">
							{ __( 'Show search options', 'simple-history' ) }
						</span>
					) }
				</div>
			</div>

			{ /* Event list area with animated height. */ }
			<div className="sh-DashboardWidget-content" ref={ contentRef }>
				<FetchEventsNoResultsMessage
					eventsIsLoading={ eventsIsLoading }
					events={ events }
				/>

				<FetchEventsErrorMessage
					eventsLoadingHasErrors={ eventsLoadingHasErrors }
					eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
				/>

				{ eventsIsLoading && (
					<SkeletonEvents count={ pagerSize?.dashboard || 5 } />
				) }

				<DashboardEventsItemsList
					eventsIsLoading={ eventsIsLoading }
					events={ events }
					hasPremiumAddOn={ hasPremiumAddOn }
				/>
			</div>

			{ /* Footer: always visible. */ }
			<div className="sh-DashboardWidget-viewAll">
				{ eventsAdminPageURL ? (
					<a href={ eventsAdminPageURL }>
						{ __( 'View detailed activity →', 'simple-history' ) }
					</a>
				) : (
					<span className="sh-DashboardWidget-viewAll__placeholder">
						{ __( 'View detailed activity →', 'simple-history' ) }
					</span>
				) }
			</div>
		</div>
	);
}
