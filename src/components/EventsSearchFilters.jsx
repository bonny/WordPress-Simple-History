import apiFetch from '@wordpress/api-fetch';
import { Button, Disabled } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { useEffect, useState, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	DEFAULT_DATE_OPTIONS,
	OPTIONS_LOADING,
	SUBITEM_PREFIX,
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
		messageTypesSuggestions,
		setMessageTypesSuggestions,
		selectedUsersWithId,
		setSelectedUsersWithId,
		searchOptionsLoaded,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
	} = props;

	const [ moreOptionsIsExpanded, setMoreOptionsIsExpanded ] =
		useState( false );
	const [ dateOptions, setDateOptions ] = useState( OPTIONS_LOADING );

	// Load search options when component mounts.
	useEffect( () => {
		apiFetch( {
			path: addQueryArgs( '/simple-history/v1/search-options', {} ),
		} ).then( ( searchOptions ) => {
			// Append result_months and all dates to dateOptions.
			const monthsOptions = searchOptions.dates.result_months.map(
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

			setSelectedDateOption(
				`lastdays:${ searchOptions.dates.daysToShow }`
			);

			/**
			 * Generate message types suggestions.
			 *
			 * Format is object
			 * {
			 *  key: "logger:message",
			 *  label: "WordPress and plugin updates"
			 * }
			 */
			const nextMessageTypesSuggestions = [];
			searchOptions.loggers.forEach( ( logger ) => {
				const searchData = logger.search_data || {};

				if ( ! searchData.search ) {
					return;
				}

				// "WordPress och tilläggsuppdateringar"
				nextMessageTypesSuggestions.push( {
					value: searchData.search.label,
					search_options: searchData.search.options,
					// key: logger.slug,
				} );

				// "Alla hittade uppdateringar"
				if ( searchData?.search_all?.label ) {
					nextMessageTypesSuggestions.push( {
						value: SUBITEM_PREFIX + searchData.search_all.label,
						// key: `${logger.slug}:all`,
						search_options: searchData.search_all.options,
					} );
				}

				// Each single message.
				if ( searchData?.search_options ) {
					searchData.search_options.forEach( ( option ) => {
						nextMessageTypesSuggestions.push( {
							value: SUBITEM_PREFIX + option.label,
							// key: `${logger.slug}:${option.key}`,
							search_options: option.options,
						} );
					} );
				}
			} );

			setMessageTypesSuggestions( nextMessageTypesSuggestions );
			setPagerSize( searchOptions.pager_size );
			setMapsApiKey( searchOptions.maps_api_key );

			setHasExtendedSettingsAddOn(
				searchOptions.addons.has_extended_settings_add_on
			);

			setIsExperimentalFeaturesEnabled(
				searchOptions.experimental_features_enabled
			);

			setEventsAdminPageURL( searchOptions.events_admin_page_url );

			setSearchOptionsLoaded( true );
		} );
	}, [
		setMessageTypesSuggestions,
		setPagerSize,
		setSearchOptionsLoaded,
		setSelectedDateOption,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
		setIsExperimentalFeaturesEnabled,
		setEventsAdminPageURL,
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
						messageTypesSuggestions={ messageTypesSuggestions }
						setMessageTypesSuggestions={
							setMessageTypesSuggestions
						}
						selectedLogLevels={ selectedLogLevels }
						setSelectedLogLevels={ setSelectedLogLevels }
						selectedMessageTypes={ selectedMessageTypes }
						setSelectedMessageTypes={ setSelectedMessageTypes }
						setSelectedUsersWithId={ setSelectedUsersWithId }
						selectedUsersWithId={ selectedUsersWithId }
					/>
				) : null }
				<p className="SimpleHistory__filters__filterSubmitWrap">
					<Button variant="secondary" onClick={ onReload }>
						{ __( 'Search events', 'simple-history' ) }
					</Button>

					<Button
						variant="tertiary"
						onClick={ () =>
							setMoreOptionsIsExpanded( ! moreOptionsIsExpanded )
						}
						className="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"
					>
						{ showMoreOrLessText }
					</Button>
				</p>
			</div>
		</MaybeDisabledTag>
	);
}
