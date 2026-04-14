import apiFetch from '@wordpress/api-fetch';
import { Button, Disabled, Icon } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { useEffect, useMemo, useState, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { settings, chevronDown } from '@wordpress/icons';
import {
	DATE_OPTION_GROUPS_LOADING,
	DEFAULT_DATE_OPTION_GROUPS,
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
		enteredMetadataSearch,
		setEnteredMetadataSearch,
		searchOptionsLoaded,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setHasFailedLoginLimit,
		setFailedLoginLimitThreshold,
		setFailedLoginSuppressedCount,
		isExperimentalFeaturesEnabled,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
		setAlertsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
		hideOwnEvents,
		setHideOwnEvents,
		defaultDateOptionRef,
		handleClearFilters,
		hasAnyActiveFilters,
	} = props;

	// Count active expanded filters — used for badge and auto-expand logic.
	const activeExpandedFilterCount = useMemo( () => {
		let count = 0;
		if ( selectedLogLevels.length > 0 ) {
			count++;
		}
		if ( selectedMessageTypes.length > 0 ) {
			count++;
		}
		if ( selectedUsersWithId.length > 0 ) {
			count++;
		}
		if ( selectedInitiator.length > 0 ) {
			count++;
		}
		if ( enteredIPAddress.trim().length > 0 ) {
			count++;
		}
		if ( selectedContextFilters.trim().length > 0 ) {
			count++;
		}
		if ( enteredMetadataSearch.trim().length > 0 ) {
			count++;
		}
		if ( hideOwnEvents ) {
			count++;
		}
		return count;
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsersWithId,
		selectedInitiator,
		enteredIPAddress,
		selectedContextFilters,
		enteredMetadataSearch,
		hideOwnEvents,
	] );

	const [ isAutoExpanded, setIsAutoExpanded ] = useState( () => {
		const urlParams = new URLSearchParams( window.location.search );
		return (
			activeExpandedFilterCount > 0 ||
			urlParams.get( 'show-filters' ) === '1'
		);
	} );
	const [ isManuallyExpanded, setIsManuallyExpanded ] = useState( null );
	const moreOptionsIsExpanded =
		isManuallyExpanded !== null ? isManuallyExpanded : isAutoExpanded;
	const [ dateOptionGroups, setDateOptionGroups ] = useState(
		DATE_OPTION_GROUPS_LOADING
	);
	const [ searchOptions, setSearchOptions ] = useState( null );

	// Wrap parent's clear handler to also reset local UI state.
	const handleClearFiltersWithUI = () => {
		handleClearFilters();
		setIsManuallyExpanded( null );
		setIsAutoExpanded( false );
	};

	// Auto-expand when filters are applied via URL parameters.
	useEffect( () => {
		if ( activeExpandedFilterCount > 0 && ! isAutoExpanded ) {
			setIsAutoExpanded( true );
		}
	}, [ activeExpandedFilterCount, isAutoExpanded ] );

	// Load search options when component mounts.
	useEffect( () => {
		const fetchSearchOptions = async () => {
			try {
				const searchOptionsResponse = await apiFetch( {
					path: addQueryArgs(
						'/simple-history/v1/search-options',
						{}
					),
				} );

				setSearchOptions( searchOptionsResponse );

				// "All dates" is rendered as an ungrouped option at the
				// very top — it's the conventional "reset/clear" slot
				// in a select, immediately discoverable, and avoids the
				// orphaned-option problem of placing it after optgroups.
				const allDatesGroup = {
					label: '',
					options: [
						{
							label: __( 'All dates', 'simple-history' ),
							value: 'allDates',
						},
					],
				};

				const monthsOptions =
					searchOptionsResponse.dates.result_months.map(
						( row ) => ( {
							label: dateI18n( 'F Y', row.yearMonth ),
							value: `month:${ row.yearMonth }`,
						} )
					);

				const monthsGroup = {
					label: __( 'By month', 'simple-history' ),
					options: monthsOptions,
				};

				setDateOptionGroups( [
					allDatesGroup,
					...DEFAULT_DATE_OPTION_GROUPS,
					monthsGroup,
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

				setHasFailedLoginLimit(
					searchOptionsResponse.has_failed_login_limit
				);

				setFailedLoginLimitThreshold(
					searchOptionsResponse.failed_login_limit_threshold || 0
				);

				setFailedLoginSuppressedCount(
					searchOptionsResponse.failed_login_suppressed_count || 0
				);

				setEventsAdminPageURL(
					searchOptionsResponse.events_admin_page_url
				);
				setEventsSettingsPageURL(
					searchOptionsResponse.settings_page_url
				);

				// Set alerts page URL if provided by premium add-on.
				if ( searchOptionsResponse.alerts_page_url ) {
					setAlertsPageURL( searchOptionsResponse.alerts_page_url );
				}

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
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error(
					'Simple History: Failed to load search options',
					error
				);
			} finally {
				setSearchOptionsLoaded( true );
			}
		};

		fetchSearchOptions();
	}, [
		setPagerSize,
		setSearchOptionsLoaded,
		setSelectedDateOption,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setHasPremiumAddOn,
		setHasFailedLoginLimit,
		setFailedLoginLimitThreshold,
		setFailedLoginSuppressedCount,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
		setEventsSettingsPageURL,
		setAlertsPageURL,
		setCurrentUserId,
		setUserCanManageOptions,
		selectedDateOption,
	] );

	const filtersButtonLabel =
		activeExpandedFilterCount > 0
			? sprintf(
					/* translators: %d: number of active filters */
					__( 'Filters (%d)', 'simple-history' ),
					activeExpandedFilterCount
			  )
			: __( 'Filters', 'simple-history' );

	// Dynamic created <Disabled> elements. Used to disable the whole search component while loading.
	const MaybeDisabledTag = searchOptionsLoaded ? Fragment : Disabled;

	return (
		<MaybeDisabledTag>
			<div className="SimpleHistory-filters">
				<div className="SimpleHistory-filters__searchRow">
					<DefaultFilters
						dateOptionGroups={ dateOptionGroups }
						selectedDateOption={ selectedDateOption }
						setSelectedDateOption={ setSelectedDateOption }
						searchText={ enteredSearchText }
						setSearchText={ setEnteredSearchText }
						selectedCustomDateFrom={ selectedCustomDateFrom }
						setSelectedCustomDateFrom={ setSelectedCustomDateFrom }
						selectedCustomDateTo={ selectedCustomDateTo }
						setSelectedCustomDateTo={ setSelectedCustomDateTo }
						onReload={ onReload }
					>
						<Button
							variant="secondary"
							__next40pxDefaultSize
							onClick={ () => {
								const currentExpanded =
									isManuallyExpanded !== null
										? isManuallyExpanded
										: isAutoExpanded;
								setIsManuallyExpanded( ! currentExpanded );
							} }
							className={ `SimpleHistory-filters__filtersToggle${
								activeExpandedFilterCount > 0
									? ' has-active-filters'
									: ''
							}` }
							aria-expanded={ moreOptionsIsExpanded }
							aria-controls="SimpleHistory-expandedFilters"
						>
							<Icon icon={ settings } size={ 16 } />
							{ filtersButtonLabel }
							<Icon
								icon={ chevronDown }
								size={ 20 }
								className="SimpleHistory-filters__filtersToggleChevron"
								aria-hidden="true"
							/>
						</Button>

						{ hasAnyActiveFilters && (
							<Button
								variant="tertiary"
								__next40pxDefaultSize
								onClick={ handleClearFiltersWithUI }
								className="SimpleHistoryFilterDropin-clearFilters"
							>
								{ __( 'Clear filters', 'simple-history' ) }
							</Button>
						) }
					</DefaultFilters>
				</div>
				{ moreOptionsIsExpanded ? (
					<div
						className="SimpleHistory-filters__expandedFilters"
						id="SimpleHistory-expandedFilters"
					>
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
							setSelectedContextFilters={
								setSelectedContextFilters
							}
							enteredMetadataSearch={ enteredMetadataSearch }
							setEnteredMetadataSearch={
								setEnteredMetadataSearch
							}
							isExperimentalFeaturesEnabled={
								isExperimentalFeaturesEnabled
							}
							searchOptions={ searchOptions }
							hideOwnEvents={ hideOwnEvents }
							setHideOwnEvents={ setHideOwnEvents }
						/>
					</div>
				) : null }
			</div>
		</MaybeDisabledTag>
	);
}
