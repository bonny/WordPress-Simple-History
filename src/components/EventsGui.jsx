import apiFetch from '@wordpress/api-fetch';
import { Slot } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { EventsSettingsProvider } from './EventsSettingsContext';
import { addQueryArgs } from '@wordpress/url';
import {
	parseAsArrayOf,
	parseAsBoolean,
	parseAsInteger,
	parseAsIsoDate,
	parseAsJson,
	parseAsString,
	parseAsStringLiteral,
	useQueryState,
} from 'nuqs';
import { z } from 'zod';
import {
	SEARCH_FILTER_DEFAULT_END_DATE,
	SEARCH_FILTER_DEFAULT_START_DATE,
} from '../constants';
import { generateAPIQueryParams, parseApiFetchError } from '../functions';
import { EventsControlBar } from './EventsControlBar';
import { EventsList } from './EventsList';
import { EventsModalIfFragment } from './EventsModalIfFragment';
import { EventsSearchFilters } from './EventsSearchFilters';
import { useSearchOptions } from '../hooks/useSearchOptions';
import { NewEventsNotifier } from './NewEventsNotifier';
import { TablePreview } from './TablePreview';

// The three views the events page offers, and the only values `?view=` may
// hold. Shared by the URL parser and by readEventsViewFromLocation() below,
// so a value the parser would reject cannot slip in through the raw URL.
const EVENTS_VIEWS = [ 'detailed', 'compact', 'table' ];

/**
 * The view named by the current URL, read straight from `window.location`.
 *
 * Only for the first render, before nuqs has resolved its own state — see
 * where this is called. Anything unrecognised reads as no view at all, which
 * falls through to the stored preference.
 *
 * @return {string|null} One of EVENTS_VIEWS, or null.
 */
function readEventsViewFromLocation() {
	const view = new URLSearchParams( window.location.search ).get( 'view' );

	return EVENTS_VIEWS.includes( view ) ? view : null;
}

// Schema for the users object.
const usersSchema = z.array(
	z.object( {
		id: z.string(),
		value: z.string(),
	} )
);

// Schema for the message types object.
// [
// 	{
// 		"value": "WordPress and plugins updates found",
// 		"search_options": [
// 			"AvailableUpdatesLogger:core_update_available",
// 			"AvailableUpdatesLogger:plugin_update_available",
// 			"AvailableUpdatesLogger:theme_update_available"
// 		]
// 	},
// 	{
// 		"value": "Term edited",
// 		"search_options": [
// 			"SimpleCategoriesLogger:edited_term"
// 		]
// 	},
// 	{
// 		"value": "Plugin updates found",
// 		"search_options": [
// 			"AvailableUpdatesLogger:plugin_update_available"
// 		]
// 	}
// ]

const messageTypesSchema = z.array(
	z.object( {
		value: z.string(),
		search_options: z.array( z.string() ),
	} )
);

// Schema for the initiator objects.
const initiatorSchema = z.array(
	z.object( {
		value: z.string(),
		initiator_key: z.string().optional(),
		search_options: z.array( z.string() ),
	} )
);

/**
 * Main component for the events GUI.
 * Contains the filter/search options and the events list.
 * Also contains the modal for the events modal.
 *
 * @return {JSX.Element} The events GUI component.
 */
function EventsGUI() {
	const [ eventsIsLoading, setEventsIsLoading ] = useState( true );
	const [ eventsLoadingHasErrors, setEventsLoadingHasErrors ] =
		useState( false );
	const [ eventsLoadingErrorDetails, setEventsLoadingErrorDetails ] =
		useState( {
			errorCode: undefined,
			errorMessage: undefined,
		} );
	const [ events, setEvents ] = useState( [] );
	const [ eventsMeta, setEventsMeta ] = useState( {} );
	const [ eventsReloadTime, setEventsReloadTime ] = useState( Date.now() );

	// Store the max id of the events. Used to check for new events.
	const [ eventsMaxId, setEventsMaxId ] = useState();

	// Store the max date of the events. Used together with maxId to check for new events.
	const [ eventsMaxDate, setEventsMaxDate ] = useState();

	// Store the previous max id of the events. Used to modify events in the list so user can see what events are new.
	const [ prevEventsMaxId, setPrevEventsMaxId ] = useState();

	const [ searchOptionsLoaded, setSearchOptionsLoaded ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ pagerSize, setPagerSize ] = useState( {} );
	const [ mapsApiKey, setMapsApiKey ] = useState( '' );
	const [ hasExtendedSettingsAddOn, setHasExtendedSettingsAddOn ] =
		useState( false );
	const [ hasPremiumAddOn, setHasPremiumAddOn ] = useState( false );
	const [ hasFailedLoginLimit, setHasFailedLoginLimit ] = useState( false );
	const [ failedLoginLimitThreshold, setFailedLoginLimitThreshold ] =
		useState( 0 );
	const [ failedLoginSuppressedCount, setFailedLoginSuppressedCount ] =
		useState( 0 );
	const [ isReactionsEnabled, setIsReactionsEnabled ] = useState( false );
	const [ isExperimentalFeaturesEnabled, setIsExperimentalFeaturesEnabled ] =
		useState( false );
	// Seeded from the value localized at enqueue time (see React_Dropin) so links
	// can be built on the first render. The search-options response overwrites it
	// with the same value once it arrives.
	const [ eventsAdminPageURL, setEventsAdminPageURL ] = useState(
		window.simpleHistoryReactData?.eventsAdminPageURL
	);

	// The view the user last chose, localized at enqueue time from user meta
	// so the first render already uses it. See REST_API::register_user_meta().
	const [ storedEventsView, setStoredEventsView ] = useState(
		[ 'compact', 'table' ].includes(
			window.simpleHistoryReactData?.eventsView
		)
			? window.simpleHistoryReactData.eventsView
			: 'detailed'
	);
	// The views the reader has hidden the page sidebar in, localized at
	// enqueue time for the same reason the view is — so the first paint is
	// already right and the page does not rearrange itself once. See
	// REST_API::HIDDEN_SIDEBAR_VIEWS_USER_META_KEY.
	const [ hiddenSidebarViews, setHiddenSidebarViews ] = useState( () => {
		const stored = window.simpleHistoryReactData?.hiddenSidebarViews;

		return Array.isArray( stored )
			? stored.filter( ( view ) => EVENTS_VIEWS.includes( view ) )
			: [ 'table' ];
	} );

	const [ settingsPageURL, setSettingsPageURL ] = useState();
	const [ alertsPageURL, setAlertsPageURL ] = useState();
	const [ currentUserId, setCurrentUserId ] = useState( null );
	const [ userCanManageOptions, setUserCanManageOptions ] = useState( false );

	/**
	 * Start filter/search options states.
	 */

	const useQueryStateOptions = {
		throttleMs: 50,
	};

	// Value selected in dates dropdown.
	// Example values: "lastdays:30", "month:2025-04", "allDates", "customRange".
	const [ selectedDateOption, setSelectedDateOption ] = useQueryState(
		'date',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Custom from date. Default to today.
	// Stored in URL as "from=2025-04-01", variable is a Date object.
	const [ selectedCustomDateFrom, setSelectedCustomDateFrom ] = useQueryState(
		'from',
		parseAsIsoDate
			.withDefault( SEARCH_FILTER_DEFAULT_START_DATE )
			.withOptions( useQueryStateOptions )
	);

	// Custom to date. Default to today.
	const [ selectedCustomDateTo, setSelectedCustomDateTo ] = useQueryState(
		'to',
		parseAsIsoDate
			.withDefault( SEARCH_FILTER_DEFAULT_END_DATE )
			.withOptions( useQueryStateOptions )
	);

	// Search text, ie. the text in the search input field.
	const [ enteredSearchText, setEnteredSearchText ] = useQueryState(
		'q',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Empty array to use as default value for the log levels.
	// If [] is passed to the withDefault() then a new array is created on each render,
	// causing useEffect to trigger on each render and the log reloads indefinitely.
	const emptyArray = useMemo( () => [], [] );
	const [ selectedLogLevels, setSelectedLogLevels ] = useQueryState(
		'levels',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Array with the selected message types.
	// Contains the same values as the messageTypesSuggestions array.
	// This is a weird format that contains much info.
	// Example contents:
	// [
	// 	{
	// 		"value": "WordPress and plugins updates found",
	// 		"search_options": [
	// 			"AvailableUpdatesLogger:core_update_available",
	// 			"AvailableUpdatesLogger:plugin_update_available",
	// 			"AvailableUpdatesLogger:theme_update_available"
	// 		]
	// 	},
	// 	{
	// 		"value": "Term edited",
	// 		"search_options": [
	// 			"SimpleCategoriesLogger:edited_term"
	// 		]
	// 	},
	// 	{
	// 		"value": "Plugin updates found",
	// 		"search_options": [
	// 			"AvailableUpdatesLogger:plugin_update_available"
	// 		]
	// 	}
	// ]
	const [ selectedMessageTypes, setSelectedMessageTypes ] = useQueryState(
		'messages',
		parseAsJson( messageTypesSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Array with objects that contain both the user id and the name+email in the same object. Keys are "id" and "value".
	// All users that are selected are added here.
	// This data is used to get user id from the name+email when we send the selected users to the API.
	// Example object:
	// [
	// 	  {
	// 	    "id": "1",
	// 	    "value": "Jane (jane@example.com)"
	// 	  },
	// 	  {
	// 	    "id": "2",
	// 	    "value": "John (john@example.com)"
	//    }
	// ]
	const [ selectedUsersWithId, setSelectedUsersWithId ] = useQueryState(
		'users',
		parseAsJson( usersSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Selected initiator filter.
	const [ selectedInitiator, setSelectedInitiator ] = useQueryState(
		'initiator',
		parseAsJson( initiatorSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// IP address filter.
	const [ enteredIPAddress, setEnteredIPAddress ] = useQueryState(
		'ip',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Selected context filters.
	// Plain string with newline-separated "key:value" pairs, e.g., "_user_id:1\n_sticky:1"
	const [ selectedContextFilters, setSelectedContextFilters ] = useQueryState(
		'context',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Metadata search: plain text search across all context values.
	const [ enteredMetadataSearch, setEnteredMetadataSearch ] = useQueryState(
		'metadata',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Show only events triggered via an AI agent.
	const [ showAIOnly, setShowAIOnly ] = useQueryState(
		'ai-only',
		parseAsBoolean.withDefault( false ).withOptions( useQueryStateOptions )
	);

	// Negative/exclusion filters - hide events matching these criteria.
	// Read-only from URL (no setters needed until Phase 2: GUI controls).
	const [ excludeSearch ] = useQueryState(
		'exclude-search',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	const [ excludeLogLevels ] = useQueryState(
		'exclude-levels',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeLoggers ] = useQueryState(
		'exclude-loggers',
		parseAsArrayOf( parseAsString )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	// Same shape as selectedMessageTypes. Set from an event's "Hide events of
	// this type" action and cleared from the chips above the list.
	const [ excludeMessages, setExcludeMessages ] = useQueryState(
		'exclude-messages',
		parseAsJson( messageTypesSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeUsers, setExcludeUsers ] = useQueryState(
		'exclude-users',
		parseAsJson( usersSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeInitiator ] = useQueryState(
		'exclude-initiator',
		parseAsJson( initiatorSchema.parse )
			.withDefault( emptyArray )
			.withOptions( useQueryStateOptions )
	);

	const [ excludeContextFilters ] = useQueryState(
		'exclude-context',
		parseAsString.withDefault( '' ).withOptions( useQueryStateOptions )
	);

	// Surrounding events - show events before and after a specific event ID.
	// This is an admin-only feature that bypasses normal filters.
	const [ surroundingEventId ] = useQueryState(
		'surrounding_event_id',
		parseAsInteger.withOptions( useQueryStateOptions )
	);

	// Number of events to show before and after the center event.
	const [ surroundingCount ] = useQueryState(
		'surrounding_count',
		parseAsInteger.withOptions( useQueryStateOptions )
	);

	// View in the URL wins over the stored one, so a shared link opens in the
	// view it was copied from. Nothing is written to the URL on page load.
	const [ urlEventsView, setUrlEventsView ] = useQueryState(
		'view',
		parseAsStringLiteral( EVENTS_VIEWS ).withOptions( useQueryStateOptions )
	);

	// The URL wins over the stored preference, so a shared link opens in the
	// view it was copied from.
	//
	// The middle term is for the first render only. nuqs settles its values
	// after that render, so on a fresh load of a ?view=table link
	// `urlEventsView` is still null and the stored preference would win for
	// one paint — long enough to draw the wrong view's chrome and then take
	// it away again. Reading the raw parameter is a better guess than the
	// stored value while nuqs is still catching up; once it has, and after
	// any in-page view switch, `urlEventsView` is authoritative and this
	// term is never reached.
	const eventsView =
		urlEventsView ?? readEventsViewFromLocation() ?? storedEventsView;

	// Whether the reader has changed view on this page, as opposed to
	// arriving in one. Only the first case can produce the layout jump the
	// reserved space below exists to absorb — see where it is rendered.
	const [ hasSwitchedView, setHasSwitchedView ] = useState( false );

	const handleEventsViewChange = useCallback(
		( newView ) => {
			// See where this is read: the table view only reserves the
			// filter panel's height once a switch has actually happened on
			// this page, because that is the only time there is a jump to
			// prevent.
			setHasSwitchedView( true );
			setUrlEventsView( newView );
			setStoredEventsView( newView );

			// Remember the choice for next time, through a dedicated route
			// rather than /wp/v2/users/me — that endpoint always runs
			// wp_update_user() and fires profile_update, which third-party
			// plugins act on for actual profile changes. See
			// REST_API::save_events_view(). A failed save is not worth
			// interrupting the user for; the toggle still works on this visit.
			apiFetch( {
				path: '/simple-history/v1/events-view',
				method: 'POST',
				data: { view: newView },
			} ).catch( () => {} );
		},
		[ setUrlEventsView ]
	);

	/**
	 * End filter/search options states.
	 */

	// Derive hideOwnEvents from whether current user is in excludeUsers.
	const hideOwnEvents = useMemo( () => {
		if ( ! currentUserId ) {
			return false;
		}
		return excludeUsers.some(
			( user ) => String( user.id ) === String( currentUserId )
		);
	}, [ excludeUsers, currentUserId ] );

	// Callback to toggle hideOwnEvents by adding/removing current user from excludeUsers URL state.
	const setHideOwnEvents = useCallback(
		( shouldHide ) => {
			if ( ! currentUserId ) {
				return;
			}

			if ( shouldHide ) {
				// Add current user to excludeUsers if not already there.
				const alreadyExcluded = excludeUsers.some(
					( user ) => String( user.id ) === String( currentUserId )
				);
				if ( ! alreadyExcluded ) {
					setExcludeUsers( [
						...excludeUsers,
						{ id: String( currentUserId ), value: 'Me' },
					] );
				}
			} else {
				// Remove current user from excludeUsers.
				setExcludeUsers(
					excludeUsers.filter(
						( user ) =>
							String( user.id ) !== String( currentUserId )
					)
				);
			}
		},
		[ currentUserId, excludeUsers, setExcludeUsers ]
	);

	// Store the default date option from the API so we can restore it when clearing filters.
	const defaultDateOptionRef = useRef( '' );

	// The page's one call to /search-options. It fills the filter dropdowns,
	// but it also carries the pager size, the add-on flags, the admin page
	// URLs and the current user — so it belongs to the page, not to the
	// filter panel it used to live inside. See useSearchOptions().
	const { searchOptions, dateOptionGroups } = useSearchOptions( {
		selectedDateOption,
		defaultDateOptionRef,
		setSelectedDateOption,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setHasFailedLoginLimit,
		setFailedLoginLimitThreshold,
		setFailedLoginSuppressedCount,
		setIsReactionsEnabled,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL: setSettingsPageURL,
		setAlertsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
	} );

	// Check if any non-date filter has a non-default value. Used by the
	// end-of-results hint to decide whether "adjust filters above" is
	// actionable advice.
	const hasNonDateActiveFilters = useMemo( () => {
		const hasExpandedFilters =
			selectedLogLevels.length > 0 ||
			selectedMessageTypes.length > 0 ||
			selectedUsersWithId.length > 0 ||
			selectedInitiator.length > 0 ||
			enteredIPAddress.trim().length > 0 ||
			selectedContextFilters.trim().length > 0 ||
			enteredMetadataSearch.trim().length > 0 ||
			showAIOnly ||
			hideOwnEvents ||
			excludeMessages.length > 0;

		const hasSearchText = enteredSearchText.trim().length > 0;

		return hasExpandedFilters || hasSearchText;
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		enteredIPAddress,
		selectedContextFilters,
		enteredMetadataSearch,
		showAIOnly,
		hideOwnEvents,
		excludeMessages,
		enteredSearchText,
	] );

	// Check if any filter has a non-default value.
	const hasAnyActiveFilters = useMemo( () => {
		const hasNonDefaultDate =
			defaultDateOptionRef.current &&
			selectedDateOption !== defaultDateOptionRef.current;

		return hasNonDateActiveFilters || hasNonDefaultDate;
	}, [ hasNonDateActiveFilters, selectedDateOption ] );

	// Reset all filter values to defaults.
	const handleClearFilters = useCallback( () => {
		setSelectedDateOption( defaultDateOptionRef.current );
		setEnteredSearchText( '' );
		setSelectedCustomDateFrom( SEARCH_FILTER_DEFAULT_START_DATE );
		setSelectedCustomDateTo( SEARCH_FILTER_DEFAULT_END_DATE );
		setSelectedLogLevels( [] );
		setSelectedMessageTypes( [] );
		setSelectedUsersWithId( [] );
		setSelectedInitiator( [] );
		setEnteredIPAddress( '' );
		setSelectedContextFilters( '' );
		setEnteredMetadataSearch( '' );
		setShowAIOnly( false );
		setHideOwnEvents( false );
		setExcludeMessages( [] );
	}, [
		setSelectedDateOption,
		setEnteredSearchText,
		setSelectedCustomDateFrom,
		setSelectedCustomDateTo,
		setSelectedLogLevels,
		setSelectedMessageTypes,
		setSelectedUsersWithId,
		setSelectedInitiator,
		setEnteredIPAddress,
		setSelectedContextFilters,
		setEnteredMetadataSearch,
		setShowAIOnly,
		setHideOwnEvents,
		setExcludeMessages,
	] );

	// Hide every event with the same logger and message key as this event
	// from the current list. Subtractive search: the user cannot say what
	// they are looking for, but can recognise what it is not.
	const hideMessageType = useCallback(
		( event ) => {
			if ( ! event?.logger || ! event?.message_key ) {
				return;
			}

			const searchOption = `${ event.logger }:${ event.message_key }`;

			const alreadyHidden = excludeMessages.some( ( messageType ) =>
				messageType.search_options.includes( searchOption )
			);

			if ( alreadyHidden ) {
				return;
			}

			// Label the chip with the message template, with placeholders
			// blanked out so it reads as a type rather than as this one event.
			// message_template is translated; message_uninterpolated is the
			// stored template, in whatever language the site had at log time.
			const label = (
				event.message_template ||
				event.message_uninterpolated ||
				event.message ||
				searchOption
			)
				.replace( /\{[^}]+\}/g, '…' )
				.trim();

			setExcludeMessages( [
				...excludeMessages,
				{
					value: label,
					search_options: [ searchOption ],
				},
			] );
		},
		[ excludeMessages, setExcludeMessages ]
	);

	// Generate the events query params.
	// Memoized to avoid unnecessary re-renders in the child components.
	const eventsQueryParams = useMemo( () => {
		return generateAPIQueryParams( {
			selectedLogLevels,
			selectedMessageTypes,
			selectedUsersWithId,
			selectedInitiator,
			enteredIPAddress,
			selectedContextFilters,
			enteredMetadataSearch,
			showAIOnly,
			enteredSearchText,
			selectedDateOption,
			selectedCustomDateFrom,
			selectedCustomDateTo,
			page,
			pagerSize,
			excludeSearch,
			excludeLogLevels,
			excludeLoggers,
			excludeMessages,
			excludeUsers,
			excludeInitiator,
			excludeContextFilters,
			surroundingEventId,
			surroundingCount,
		} );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		enteredIPAddress,
		selectedContextFilters,
		enteredMetadataSearch,
		showAIOnly,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
		pagerSize,
		excludeSearch,
		excludeLogLevels,
		excludeLoggers,
		excludeMessages,
		excludeUsers,
		excludeInitiator,
		excludeContextFilters,
		surroundingEventId,
		surroundingCount,
	] );

	// Reset page to 1 when filters are modified.
	useEffect( () => {
		setPage( 1 );
	}, [
		selectedDateOption,
		enteredSearchText,
		selectedLogLevels,
		selectedMessageTypes,
		selectedInitiator,
		enteredIPAddress,
		selectedContextFilters,
		enteredMetadataSearch,
		showAIOnly,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		excludeUsers,
	] );

	/**
	 * Load events from the REST API.
	 * A new function is created each time the eventsQueryParams changes,
	 * so that's whats making the reload of events.
	 *
	 * TODO: Move this to a hook.
	 */
	// Derived rather than using eventsView itself in loadEvents' deps below:
	// detailed and compact share this same fetch, and eventsView also flips
	// between those two, which must not refire the full fetch — only the
	// switch into or out of table view changes what gets requested.
	const isTableView = eventsView === 'table';

	const isSidebarHidden = hiddenSidebarViews.includes( eventsView );

	// Take the page sidebar out, or put it back.
	//
	// The sidebar is printed by a PHP dropin outside this React root, so the
	// only way to say anything about it from here is to reach up to the wrap
	// both of them sit in. It has to be done from JavaScript rather than
	// decided in PHP because the view switches without a page load — a
	// server-side conditional would be right until the first time someone
	// used the switcher. The rule itself is in css/styles.css.
	useEffect( () => {
		const wrap = document.querySelector( '.SimpleHistoryGuiWrap' );

		if ( ! wrap ) {
			return undefined;
		}

		wrap.classList.toggle(
			'SimpleHistoryGuiWrap--hideSidebar',
			isSidebarHidden
		);

		return () => {
			wrap.classList.remove( 'SimpleHistoryGuiWrap--hideSidebar' );
		};
	}, [ isSidebarHidden ] );

	const handleToggleSidebar = useCallback( () => {
		// Only this view changes. The three views want different amounts of
		// room, so hiding the sidebar in the table must not quietly hide it
		// in the detailed list as well.
		const next = hiddenSidebarViews.includes( eventsView )
			? hiddenSidebarViews.filter( ( view ) => view !== eventsView )
			: [ ...hiddenSidebarViews, eventsView ];

		setHiddenSidebarViews( next );

		// Outside the state updater, not inside it. An updater has to be a
		// pure function of the previous state: React invokes it twice in
		// StrictMode and may re-invoke it when a render is thrown away, so a
		// request sent from in there went out twice per click, and would go
		// out for a render nobody ever saw.
		//
		// A failed save is not worth interrupting anyone for; the toggle
		// still works for this visit. Same reasoning as the view preference
		// above.
		apiFetch( {
			path: '/simple-history/v1/sidebar-visibility',
			method: 'POST',
			data: { views: next },
		} ).catch( () => {} );
	}, [ eventsView, hiddenSidebarViews ] );

	const loadEvents = useCallback( async () => {
		setEventsIsLoading( true );

		try {
			// In table view, Premium fetches and renders the events itself
			// (see the SimpleHistorySlotTableView fill), so this request's
			// only job is keeping the new-events notifier and eventsTotal
			// accurate. Ask for the smallest response that can still answer
			// both: one row, for the max id/date headers, and a minimal
			// field set. The count query is not skipped, so eventsTotal
			// still reflects the real total.
			const fetchQueryParams = isTableView
				? {
						...eventsQueryParams,
						per_page: 1,
						_fields: 'id,date_gmt',
				  }
				: eventsQueryParams;

			const eventsResponse = await apiFetch( {
				path: addQueryArgs(
					'/simple-history/v1/events',
					fetchQueryParams
				),
				parse: false,
			} );

			const eventsJson = await eventsResponse.json();

			// Table view's request is trimmed to per_page: 1, so its
			// "totalPages" is really the total event count (one "page" per
			// event) and its single row is not a real event. Only the
			// total itself (from the untrimmed count query) is usable, so
			// keep the existing event list and page count untouched and
			// just refresh the total that the control bar shows.
			if ( isTableView ) {
				setEventsMeta( ( previousEventsMeta ) => ( {
					...previousEventsMeta,
					total: parseInt(
						eventsResponse.headers.get( 'X-Wp-Total' ),
						10
					),
				} ) );
			} else {
				setEventsMeta( {
					total: parseInt(
						eventsResponse.headers.get( 'X-Wp-Total' ),
						10
					),
					totalPages: parseInt(
						eventsResponse.headers.get( 'X-Wp-Totalpages' ),
						10
					),
					link: eventsResponse.headers.get( 'Link' ),
				} );
			}

			// To keep track of new events we need to store both old max id and new max id.
			// Extract maxId and maxDate from response headers for accurate new event detection.
			if ( eventsJson && eventsJson.length && page === 1 ) {
				const maxId = eventsResponse.headers.get(
					'X-SimpleHistory-MaxId'
				);
				const maxDate = eventsResponse.headers.get(
					'X-SimpleHistory-MaxDate'
				);

				if ( maxId ) {
					setEventsMaxId( parseInt( maxId, 10 ) );
				}

				if ( maxDate ) {
					setEventsMaxDate( maxDate );
				}
			}

			// Table view's single stub row (id + date_gmt only) must never
			// reach the event list or pager, so leave the last real
			// events in place until the view switches back and a full
			// refetch replaces them.
			if ( ! isTableView ) {
				setEvents( eventsJson );
			}
		} catch ( error ) {
			// Parse before setting state, so both updates land in the same
			// render. Awaiting between them renders an intermediate "there is
			// an error but no details yet" state.
			const errorDetails = await parseApiFetchError( error );

			setEventsLoadingHasErrors( true );
			setEventsLoadingErrorDetails( errorDetails );
		} finally {
			setEventsIsLoading( false );
		}
	}, [ eventsQueryParams, page, isTableView ] );

	// Debounce the loadEvents function to avoid multiple calls when user types fast.
	const debouncedLoadEvents = useDebounce( loadEvents, 500 );

	// The debounce coalesces fast filter changes. The first load has nothing to
	// coalesce, so waiting out the 500 ms trailing edge only delays the first paint.
	const isFirstLoadRef = useRef( true );

	/**
	 * Load events when search options are loaded,
	 * when the reload time is changed,
	 * or when function debouncedLoadEvents is changed due to changes in eventsQueryParams.
	 * The very first load happens immediately; subsequent loads are debounced.
	 */
	useEffect( () => {
		// Wait for search options to be loaded before loading events,
		// or the loadEvents will be called twice.
		// Exception: when viewing surrounding events, we don't need search options.
		if ( ! searchOptionsLoaded && ! surroundingEventId ) {
			return;
		}

		if ( isFirstLoadRef.current ) {
			isFirstLoadRef.current = false;
			loadEvents();
			return;
		}

		debouncedLoadEvents();
	}, [
		loadEvents,
		debouncedLoadEvents,
		searchOptionsLoaded,
		eventsReloadTime,
		surroundingEventId,
	] );

	/**
	 * Function to set reload time to current time,
	 * which will trigger a reload of the events.
	 * This is used as a callback function for child components,
	 * for example for the search button in the search component.
	 */
	const handleReload = () => {
		setPage( 1 );
		setPrevEventsMaxId( eventsMaxId );
		setEventsReloadTime( Date.now() );
	};

	// Scroll to top smoothly when going to a new page.
	useEffect( () => {
		window.scrollTo( {
			top: 0,
			behavior: 'smooth',
		} );
	}, [ page ] );

	// Listen for chart date click events from the sidebar chart.
	// When a date is clicked in the chart, update the date filter to show events for that day.
	useEffect( () => {
		const handleChartDateClick = ( event ) => {
			const { date } = event.detail;

			// Parse the date string (Y-m-d format) to create a Date object.
			// The date string is in format "2024-10-05".
			const dateObj = new Date( date + 'T00:00:00Z' );

			// Set the date option to custom range.
			setSelectedDateOption( 'customRange' );

			// Set both from and to dates to the same date (to show only one day).
			setSelectedCustomDateFrom( dateObj );
			setSelectedCustomDateTo( dateObj );
		};

		window.addEventListener(
			'SimpleHistory:chartDateClick',
			handleChartDateClick
		);

		return () => {
			window.removeEventListener(
				'SimpleHistory:chartDateClick',
				handleChartDateClick
			);
		};
	}, [
		setSelectedDateOption,
		setSelectedCustomDateFrom,
		setSelectedCustomDateTo,
	] );

	// Listen for event creation from premium add-on or other extensions.
	// When a new log entry is created, refresh the event list immediately.
	useEffect( () => {
		const handleEventCreated = () => {
			handleReload();
		};

		window.addEventListener(
			'SimpleHistory:eventCreated',
			handleEventCreated
		);

		return () => {
			window.removeEventListener(
				'SimpleHistory:eventCreated',
				handleEventCreated
			);
		};
	}, [ handleReload ] );

	// Listen for IP address filter events from the IP address popover.
	// When a user clicks "Show all events from this IP address" in the popover,
	// update the IP address filter.
	useEffect( () => {
		const handleFilterByIPAddress = ( event ) => {
			const { ipAddress } = event.detail;
			setEnteredIPAddress( ipAddress );
		};

		window.addEventListener(
			'SimpleHistory:filterByIPAddress',
			handleFilterByIPAddress
		);

		return () => {
			window.removeEventListener(
				'SimpleHistory:filterByIPAddress',
				handleFilterByIPAddress
			);
		};
	}, [ setEnteredIPAddress ] );

	const eventsSettingsValue = useMemo(
		() => ( {
			mapsApiKey,
			hasExtendedSettingsAddOn,
			hasPremiumAddOn,
			hasFailedLoginLimit,
			reactionsEnabled: isReactionsEnabled,
			experimentalFeaturesEnabled: isExperimentalFeaturesEnabled,
			eventsSettingsPageURL: settingsPageURL,
			alertsPageURL,
			eventsAdminPageURL,
			userCanManageOptions,
			searchOptionsLoaded,
			currentUserId,
			// This GUI owns the filter controls, so descendants can filter by
			// updating state here instead of navigating away.
			canFilterEventsInPlace: true,
			hideMessageType,
		} ),
		[
			mapsApiKey,
			hasExtendedSettingsAddOn,
			hasPremiumAddOn,
			hasFailedLoginLimit,
			isReactionsEnabled,
			isExperimentalFeaturesEnabled,
			settingsPageURL,
			alertsPageURL,
			eventsAdminPageURL,
			userCanManageOptions,
			searchOptionsLoaded,
			currentUserId,
			hideMessageType,
		]
	);

	return (
		<EventsSettingsProvider value={ eventsSettingsValue }>
			{ /* Stats bar (EventsStatsBar) was here — removed for now, component still exists if needed. */ }

			{ /* Not rendered at all in the table view, and not while viewing
			   surrounding events.

			   The table view carries its own query bar and its own chips
			   for exactly these filters, so this panel would be a second,
			   differently-shaped control for the same thing. Leaving it out
			   of the DOM rather than hiding it also means table-view work
			   no longer has to reason about a collapsed panel that is still
			   mounted and still holding state. What made this possible was
			   moving the /search-options fetch up into useSearchOptions()
			   above — until then the panel was also the page's bootstrap,
			   and not rendering it took the page's data with it. */ }
			{ /* The table view does not render the filter panel, but after
			   an in-page switch it keeps the space the panel occupied.
			   Switching from Detailed or Compact to Table otherwise pulled
			   the whole table up by the panel's height, which reads as the
			   page breaking rather than as a view changing.

			   Only after a switch, though. On a fresh load of a ?view=table
			   link — a shared view, a bookmark, the stored preference —
			   there is no previous layout and so no jump, and the reservation
			   was simply 65px of blank space at the very top of the page,
			   above everything, every time. That was the first thing on the
			   screen and it read as a broken margin. */ }
			{ ! surroundingEventId &&
				eventsView === 'table' &&
				hasSwitchedView && (
					<div
						className="SimpleHistory-filters__reservedSpace"
						aria-hidden="true"
					/>
				) }

			{ ! surroundingEventId && eventsView !== 'table' && (
				<EventsSearchFilters
					selectedLogLevels={ selectedLogLevels }
					setSelectedLogLevels={ setSelectedLogLevels }
					selectedMessageTypes={ selectedMessageTypes }
					setSelectedMessageTypes={ setSelectedMessageTypes }
					selectedDateOption={ selectedDateOption }
					setSelectedDateOption={ setSelectedDateOption }
					enteredSearchText={ enteredSearchText }
					setEnteredSearchText={ setEnteredSearchText }
					selectedCustomDateFrom={ selectedCustomDateFrom }
					setSelectedCustomDateFrom={ setSelectedCustomDateFrom }
					selectedCustomDateTo={ selectedCustomDateTo }
					setSelectedCustomDateTo={ setSelectedCustomDateTo }
					selectedUsersWithId={ selectedUsersWithId }
					setSelectedUsersWithId={ setSelectedUsersWithId }
					selectedInitiator={ selectedInitiator }
					setSelectedInitiator={ setSelectedInitiator }
					enteredIPAddress={ enteredIPAddress }
					selectedContextFilters={ selectedContextFilters }
					setSelectedContextFilters={ setSelectedContextFilters }
					enteredMetadataSearch={ enteredMetadataSearch }
					setEnteredMetadataSearch={ setEnteredMetadataSearch }
					showAIOnly={ showAIOnly }
					setShowAIOnly={ setShowAIOnly }
					searchOptions={ searchOptions }
					dateOptionGroups={ dateOptionGroups }
					searchOptionsLoaded={ searchOptionsLoaded }
					onReload={ handleReload }
					excludeMessages={ excludeMessages }
					setExcludeMessages={ setExcludeMessages }
					hideOwnEvents={ hideOwnEvents }
					setHideOwnEvents={ setHideOwnEvents }
					handleClearFilters={ handleClearFilters }
					hasAnyActiveFilters={ hasAnyActiveFilters }
				/>
			) }

			{ /* Hide control bar when viewing surrounding events */ }
			{ ! surroundingEventId && (
				<EventsControlBar
					eventsIsLoading={ eventsIsLoading }
					eventsTotal={ eventsMeta.total }
					eventsQueryParams={ eventsQueryParams }
					hasAnyActiveFilters={ hasAnyActiveFilters }
					eventsView={ eventsView }
					onEventsViewChange={ handleEventsViewChange }
					isSidebarHidden={ isSidebarHidden }
					onToggleSidebar={ handleToggleSidebar }
					newEventsNotifier={
						<NewEventsNotifier
							eventsQueryParams={ eventsQueryParams }
							eventsMaxId={ eventsMaxId }
							eventsMaxDate={ eventsMaxDate }
							onReload={ handleReload }
						/>
					}
				/>
			) }

			{ eventsView === 'table' ? (
				/* Premium fills this Slot with the real table. With no fill —
				   no Premium, or a Premium too old to know about the Slot —
				   the preview renders, so the view is never blank. */
				<Slot
					name="SimpleHistorySlotTableView"
					fillProps={ {
						eventsQueryParams,
						eventsTotal: eventsMeta.total,
						hasAnyActiveFilters,
						eventsIsLoading,
						eventsReloadTime,
					} }
				>
					{ ( fills ) =>
						fills.length > 0 ? (
							fills
						) : (
							<TablePreview
								onBackToList={ () =>
									handleEventsViewChange( 'detailed' )
								}
							/>
						)
					}
				</Slot>
			) : (
				<EventsList
					eventsIsLoading={ eventsIsLoading }
					events={ events }
					eventsMeta={ eventsMeta }
					page={ page }
					pagerSize={ pagerSize }
					setPage={ setPage }
					prevEventsMaxId={ prevEventsMaxId }
					failedLoginLimitThreshold={ failedLoginLimitThreshold }
					failedLoginSuppressedCount={ failedLoginSuppressedCount }
					eventsLoadingHasErrors={ eventsLoadingHasErrors }
					eventsLoadingErrorDetails={ eventsLoadingErrorDetails }
					surroundingEventId={ surroundingEventId }
					surroundingCount={ surroundingCount }
					hasActiveFilters={ hasAnyActiveFilters }
					onClearFilters={ handleClearFilters }
					canAdjustFilters={
						hasNonDateActiveFilters &&
						selectedDateOption !== 'allDates'
					}
					eventsView={ eventsView }
				/>
			) }

			<EventsModalIfFragment />
		</EventsSettingsProvider>
	);
}

export default EventsGUI;
