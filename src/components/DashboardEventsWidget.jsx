import apiFetch from '@wordpress/api-fetch';
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Icon, chartBar } from '@wordpress/icons';
import { EVENT_FIELDS, parseApiFetchError } from '../functions';
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
		useState( null );
	const [ eventsAdminPageURL, setEventsAdminPageURL ] = useState( '' );
	const [ settingsPageURL, setSettingsPageURL ] = useState( '' );
	const [ mapsApiKey, setMapsApiKey ] = useState( '' );
	const [ pagerSize, setPagerSize ] = useState( null );
	const [ hasPremiumAddOn, setHasPremiumAddOn ] = useState( false );
	const [ stats, setStats ] = useState( null );
	const contentRef = useRef( null );
	const prevHeightRef = useRef( 0 );
	const transitionHandlerRef = useRef( null );
	const tipSeedRef = useRef( Math.random() );

	// Pick a tip using a stable seed so it doesn't change when hasPremiumAddOn flips.
	const tip = useMemo( () => {
		const tips = hasPremiumAddOn
			? [
					__(
						'Pin important events with "Sticky" so they stay visible at the top.',
						'simple-history'
					),
					__(
						'Set up alerts in Settings to get notified when important events happen.',
						'simple-history'
					),
					__(
						'Use Message Control in Settings to choose exactly which events get logged.',
						'simple-history'
					),
					__(
						'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.',
						'simple-history'
					),
					__(
						'Export your event log as CSV, JSON, or HTML from Export & Tools.',
						'simple-history'
					),
					__(
						'Use "Show surrounding events" to see what happened right before and after any event.',
						'simple-history'
					),
			  ]
			: [
					__(
						'Get email alerts when important events happen. Available with Simple History Premium.',
						'simple-history'
					),
					__(
						'Pin important events so they stay visible at the top. Available with Simple History Premium.',
						'simple-history'
					),
					__(
						'Keep a full year of event history. Available with Simple History Premium.',
						'simple-history'
					),
					__(
						'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.',
						'simple-history'
					),
					__(
						'Export your event log as CSV, JSON, or HTML from Export & Tools.',
						'simple-history'
					),
					__(
						'Use "Show surrounding events" to see what happened right before and after any event.',
						'simple-history'
					),
			  ];

		return tips[ Math.floor( tipSeedRef.current * tips.length ) ];
	}, [ hasPremiumAddOn ] );

	// Animate any content height change (skeleton resize, events loading, etc.).
	// Runs every render (no deps) — the early-exit guard makes it cheap when height is unchanged.
	useLayoutEffect( () => {
		const el = contentRef.current;
		if ( ! el ) {
			return;
		}

		// Clean up any interrupted animation.
		if ( transitionHandlerRef.current ) {
			el.removeEventListener(
				'transitionend',
				transitionHandlerRef.current
			);
			transitionHandlerRef.current = null;
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
			transitionHandlerRef.current = null;
		};
		transitionHandlerRef.current = handleEnd;
		el.addEventListener( 'transitionend', handleEnd );

		return () => {
			if ( transitionHandlerRef.current ) {
				el.removeEventListener(
					'transitionend',
					transitionHandlerRef.current
				);
				transitionHandlerRef.current = null;
			}
		};
	} );

	// Fetch search options on mount to get pager size and admin page URL.
	useEffect( () => {
		apiFetch( { path: '/simple-history/v1/search-options' } )
			.then( ( response ) => {
				setPagerSize( response.pager_size );
				setEventsAdminPageURL( response.events_admin_page_url || '' );
				setSettingsPageURL( response.settings_page_url || '' );
				setMapsApiKey( response.maps_api_key || '' );
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
					_fields: EVENT_FIELDS,
				} ),
				parse: false,
			} );

			const eventsJson = await eventsResponse.json();
			setEvents( eventsJson );
		} catch ( error ) {
			setEventsLoadingHasErrors( true );
			setEventsLoadingErrorDetails( await parseApiFetchError( error ) );
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
									'%s event last 7 days',
									'%s events last 7 days',
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
							aria-label={ __(
								'Search events',
								'simple-history'
							) }
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
					eventsSettingsPageURL={ settingsPageURL }
					mapsApiKey={ mapsApiKey }
				/>
			</div>

			{ /* Tip: shown after events load. */ }
			{ ! eventsIsLoading && events.length > 0 && (
				<p className="sh-DashboardWidget-tip">{ tip }</p>
			) }

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
