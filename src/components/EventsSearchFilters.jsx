import { Button, Disabled, Icon } from '@wordpress/components';
import { useEffect, useMemo, useState, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { settings, chevronDown } from '@wordpress/icons';
import { DefaultFilters } from './DefaultFilters';
import { ExpandedFilters } from './ExpandedFilters';
import { HiddenMessageTypes } from './HiddenMessageTypes';

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 *
 * Presentational: the /search-options request that fills these dropdowns
 * lives in useSearchOptions(), called by EventsGui. This component can
 * therefore be left unrendered — as the table view does — without the rest
 * of the page losing its data.
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
		selectedContextFilters,
		setSelectedContextFilters,
		enteredMetadataSearch,
		setEnteredMetadataSearch,
		showAIOnly,
		setShowAIOnly,
		searchOptions,
		dateOptionGroups,
		searchOptionsLoaded,
		hideOwnEvents,
		setHideOwnEvents,
		excludeMessages,
		setExcludeMessages,
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
		if ( showAIOnly ) {
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
		showAIOnly,
		hideOwnEvents,
	] );

	// Opening the panel on load when a filter is active is the right call
	// here: this panel is the only place those filters are visible. It used
	// to need a "unless this is the table view" guard, because the table
	// view rendered the panel collapsed and would have arrived from a shared
	// link showing a 500px panel that only repeated what its own chips
	// already said. EventsGui no longer renders this component in the table
	// view at all, so the guard is gone with it.
	//
	// `show-filters=1` always opens the panel: that is someone asking.
	const [ isAutoExpanded, setIsAutoExpanded ] = useState( () => {
		const urlParams = new URLSearchParams( window.location.search );

		if ( urlParams.get( 'show-filters' ) === '1' ) {
			return true;
		}

		return activeExpandedFilterCount > 0;
	} );
	const [ isManuallyExpanded, setIsManuallyExpanded ] = useState( null );
	const moreOptionsIsExpanded =
		isManuallyExpanded !== null ? isManuallyExpanded : isAutoExpanded;

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
				<HiddenMessageTypes
					excludeMessages={ excludeMessages }
					setExcludeMessages={ setExcludeMessages }
				/>
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
							showAIOnly={ showAIOnly }
							setShowAIOnly={ setShowAIOnly }
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
