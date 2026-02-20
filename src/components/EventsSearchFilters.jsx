import apiFetch from '@wordpress/api-fetch';
import { Button, Disabled } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import {
	useEffect,
	useState,
	useRef,
	Fragment,
	useCallback,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	DEFAULT_DATE_OPTIONS,
	OPTIONS_LOADING,
	SEARCH_FILTER_DEFAULT_START_DATE,
	SEARCH_FILTER_DEFAULT_END_DATE,
} from '../constants';
import { DefaultFilters } from './DefaultFilters';
import { ExpandedFilters } from './ExpandedFilters';

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 *
 * @param {Object} props
 */
export function EventsSearchFilters( props ) {
	const {
		onReload,
		selectedLogLevels,
		setSelectedLogLevels,
		selectedMessageTypes,
		setSelectedMessageTypes,
		selectedDateOption,
		setSelectedDateOption,
		enteredSearchText,
		setEnteredSearchText,
		selectedCustomDateFrom,
		setSelectedCustomDateFrom,
		selectedCustomDateTo,
		setSelectedCustomDateTo,
		selectedUsersWithId,
		setSelectedUsersWithId,
		selectedInitiator,
		setSelectedInitiator,
		enteredIPAddress,
		setEnteredIPAddress,
		selectedContextFilters,
		setSelectedContextFilters,
		searchOptionsLoaded,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		isExperimentalFeaturesEnabled,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
		hideOwnEvents,
		setHideOwnEvents,
	} = props;

	// Check if search options should be auto-expanded based on URL parameters.
	// Only check filters that are hidden behind the "Show search options" button.
	const hasActiveFilters = useCallback( () => {
		return (
			selectedLogLevels.length > 0 ||
			selectedMessageTypes.length > 0 ||
			selectedUsersWithId.length > 0 ||
			selectedInitiator.length > 0 ||
			enteredIPAddress.trim().length > 0 ||
			selectedContextFilters.trim().length > 0 ||
			hideOwnEvents
		);
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		enteredIPAddress,
		selectedContextFilters,
		hideOwnEvents,
	] );

	const [ isAutoExpanded, setIsAutoExpanded ] = useState( () =>
		hasActiveFilters()
	);
	const [ isManuallyExpanded, setIsManuallyExpanded ] = useState( null );
	const moreOptionsIsExpanded =
		isManuallyExpanded !== null ? isManuallyExpanded : isAutoExpanded;
	const [ dateOptions, setDateOptions ] = useState( OPTIONS_LOADING );
	const [ searchOptions, setSearchOptions ] = useState( null );

	// Store the default date option from the API so we can restore it when clearing filters.
	const defaultDateOptionRef = useRef( '' );

	// Check if any filter (including default filters) has a non-default value.
	const hasAnyActiveFilters = useCallback( () => {
		const hasExpandedFilters = hasActiveFilters();

		const hasSearchText = enteredSearchText.trim().length > 0;

		// Date is non-default if it differs from the API-recommended value.
		const hasNonDefaultDate =
			defaultDateOptionRef.current &&
			selectedDateOption !== defaultDateOptionRef.current;

		return hasExpandedFilters || hasSearchText || hasNonDefaultDate;
	}, [ hasActiveFilters, enteredSearchText, selectedDateOption ] );

	// Reset all filters to their default values.
	const handleClearFilters = () => {
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
		setHideOwnEvents( false );

		// Collapse expanded filters and reset auto-expand state.
		setIsManuallyExpanded( null );
		setIsAutoExpanded( false );
	};

	// Auto-expand search options when filters are applied via URL parameters
	useEffect( () => {
		if ( hasActiveFilters() && ! isAutoExpanded ) {
			setIsAutoExpanded( true );
		}
	}, [ hasActiveFilters, isAutoExpanded ] );

	// Load search options when component mounts.
	useEffect( () => {
		apiFetch( {
			path: addQueryArgs( '/simple-history/v1/search-options', {} ),
		} ).then( ( searchOptionsResponse ) => {
			setSearchOptions( searchOptionsResponse );

			// Append result_months and all dates to dateOptions.
			const monthsOptions = searchOptionsResponse.dates.result_months.map(
				( row ) => ( {
					label: dateI18n( 'F Y', row.yearMonth ),
					value: `month:${ row.yearMonth }`,
				} )
			);

			const allDatesOption = {
				label: __( 'All dates', 'simple-history' ),
				value: 'allDates',
			};

			setDateOptions( [
				...DEFAULT_DATE_OPTIONS,
				...monthsOptions,
				allDatesOption,
			] );

			// Store the default date option for use when clearing filters.
			const apiDefaultDateOption = `lastdays:${ searchOptionsResponse.dates.daysToShow }`;
			defaultDateOptionRef.current = apiDefaultDateOption;

			// Set selected date option to "recommended" option from API.
			// Only set if not already set, because it can be set in the URL.
			if ( ! selectedDateOption ) {
				setSelectedDateOption( apiDefaultDateOption );
			}

			setPagerSize( searchOptionsResponse.pager_size );
			setMapsApiKey( searchOptionsResponse.maps_api_key );

			setHasExtendedSettingsAddOn(
				searchOptionsResponse.addons.has_extended_settings_add_on
			);

			setHasPremiumAddOn(
				searchOptionsResponse.addons.has_premium_add_on
			);

			setIsExperimentalFeaturesEnabled(
				searchOptionsResponse.experimental_features_enabled
			);

			setEventsAdminPageURL(
				searchOptionsResponse.events_admin_page_url
			);
			setEventsSettingsPageURL( searchOptionsResponse.settings_page_url );

			// Set current user ID for "Hide my own events" feature.
			if ( searchOptionsResponse.current_user_id ) {
				setCurrentUserId( searchOptionsResponse.current_user_id );
			}

			// Set whether user can manage options (is administrator).
			if ( searchOptionsResponse.current_user_can_manage_options ) {
				setUserCanManageOptions(
					searchOptionsResponse.current_user_can_manage_options
				);
			}

			setSearchOptionsLoaded( true );
		} );
	}, [
		setPagerSize,
		setSearchOptionsLoaded,
		setSelectedDateOption,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
		selectedDateOption,
	] );

	const showMoreOrLessText = moreOptionsIsExpanded
		? __( 'Collapse search options', 'simple-history' )
		: __( 'Show search options', 'simple-history' );

	// Dynamic created <Disabled> elements. Used to disable the whole search component while loading.
	const MaybeDisabledTag = searchOptionsLoaded ? Fragment : Disabled;

	return (
		<MaybeDisabledTag>
			<div className="SimpleHistory-filters">
				<DefaultFilters
					dateOptions={ dateOptions }
					selectedDateOption={ selectedDateOption }
					setSelectedDateOption={ setSelectedDateOption }
					searchText={ enteredSearchText }
					setSearchText={ setEnteredSearchText }
					selectedCustomDateFrom={ selectedCustomDateFrom }
					setSelectedCustomDateFrom={ setSelectedCustomDateFrom }
					selectedCustomDateTo={ selectedCustomDateTo }
					setSelectedCustomDateTo={ setSelectedCustomDateTo }
				/>
				{ moreOptionsIsExpanded ? (
					<ExpandedFilters
						selectedLogLevels={ selectedLogLevels }
						setSelectedLogLevels={ setSelectedLogLevels }
						selectedMessageTypes={ selectedMessageTypes }
						setSelectedMessageTypes={ setSelectedMessageTypes }
						setSelectedUsersWithId={ setSelectedUsersWithId }
						selectedUsersWithId={ selectedUsersWithId }
						selectedInitiator={ selectedInitiator }
						setSelectedInitiator={ setSelectedInitiator }
						enteredIPAddress={ enteredIPAddress }
						setEnteredIPAddress={ setEnteredIPAddress }
						selectedContextFilters={ selectedContextFilters }
						setSelectedContextFilters={ setSelectedContextFilters }
						isExperimentalFeaturesEnabled={
							isExperimentalFeaturesEnabled
						}
						searchOptions={ searchOptions }
						hideOwnEvents={ hideOwnEvents }
						setHideOwnEvents={ setHideOwnEvents }
					/>
				) : null }
				<p className="SimpleHistory__filters__filterSubmitWrap">
					<Button variant="secondary" onClick={ onReload }>
						{ __( 'Search events', 'simple-history' ) }
					</Button>

					{ hasAnyActiveFilters() && (
						<Button
							variant="tertiary"
							onClick={ handleClearFilters }
							className="SimpleHistoryFilterDropin-clearFilters"
						>
							{ __( 'Clear filters', 'simple-history' ) }
						</Button>
					) }

					<Button
						variant="tertiary"
						onClick={ () => {
							const currentExpanded =
								isManuallyExpanded !== null
									? isManuallyExpanded
									: isAutoExpanded;
							setIsManuallyExpanded( ! currentExpanded );
						} }
						className="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"
					>
						{ showMoreOrLessText }
					</Button>
				</p>
			</div>
		</MaybeDisabledTag>
	);
}
