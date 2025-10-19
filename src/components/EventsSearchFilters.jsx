import apiFetch from '@wordpress/api-fetch';
import { Button, Disabled } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { useEffect, useState, Fragment, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { DEFAULT_DATE_OPTIONS, OPTIONS_LOADING } from '../constants';
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
		selectedContextFilters,
		setSelectedContextFilters,
		searchOptionsLoaded,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
	} = props;

	// Check if search options should be auto-expanded based on URL parameters.
	// Only check filters that are hidden behind the "Show search options" button.
	const hasActiveFilters = useCallback( () => {
		return (
			selectedLogLevels.length > 0 ||
			selectedMessageTypes.length > 0 ||
			selectedUsersWithId.length > 0 ||
			selectedInitiator.length > 0 ||
			selectedContextFilters.trim().length > 0
		);
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		selectedContextFilters,
	] );

	const [ isAutoExpanded, setIsAutoExpanded ] = useState( () =>
		hasActiveFilters()
	);
	const [ isManuallyExpanded, setIsManuallyExpanded ] = useState( null );
	const moreOptionsIsExpanded =
		isManuallyExpanded !== null ? isManuallyExpanded : isAutoExpanded;
	const [ dateOptions, setDateOptions ] = useState( OPTIONS_LOADING );
	const [ searchOptions, setSearchOptions ] = useState( null );

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

			// Set selected date option to "recommended" option from API.
			// Only set if not already set, because it can be set in the URL.
			if ( ! selectedDateOption ) {
				setSelectedDateOption(
					`lastdays:${ searchOptionsResponse.dates.daysToShow }`
				);
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
						selectedContextFilters={ selectedContextFilters }
						setSelectedContextFilters={ setSelectedContextFilters }
						searchOptions={ searchOptions }
					/>
				) : null }
				<p className="SimpleHistory__filters__filterSubmitWrap">
					<Button variant="secondary" onClick={ onReload }>
						{ __( 'Search events', 'simple-history' ) }
					</Button>

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
