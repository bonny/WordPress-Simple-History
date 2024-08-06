import apiFetch from '@wordpress/api-fetch';
import { Disabled } from '@wordpress/components';
import { dateI18n } from '@wordpress/date';
import { useEffect, useState, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import {
	DEFAULT_DATE_OPTIONS,
	OPTIONS_LOADING,
	SUBITEM_PREFIX,
} from './constants';
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
		selectedUsers,
		setSelectUsers,
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
		userSuggestions,
		setUserSuggestions,
		searchOptionsLoaded,
		setSearchOptionsLoaded,
		setPagerSize,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
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
					label: searchData.search,
					// key: logger.slug,
					// search_options: search_data.search
				} );

				// "Alla hittade uppdateringar"
				if ( searchData?.search_all?.label ) {
					nextMessageTypesSuggestions.push( {
						label: SUBITEM_PREFIX + searchData.search_all.label,
						// key: `${logger.slug}:all`,
						search_options: searchData.search_all.options,
					} );
				}

				// Each single message.
				if ( searchData?.search_options ) {
					searchData.search_options.forEach( ( option ) => {
						nextMessageTypesSuggestions.push( {
							label: SUBITEM_PREFIX + option.label,
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

			setSearchOptionsLoaded( true );
		} );
	}, [
		setMessageTypesSuggestions,
		setPagerSize,
		setSearchOptionsLoaded,
		setSelectedDateOption,
		setMapsApiKey,
		setHasExtendedSettingsAddOn,
	] );

	const showMoreOrLessText = moreOptionsIsExpanded
		? __( 'Collapse search options', 'simple-history' )
		: __( 'Show search options', 'simple-history' );

	// Dynamic created <Disabled> elements. Used to disable the whole search component while loading.
	const MaybeDisabledTag = searchOptionsLoaded ? Fragment : Disabled;

	return (
		<MaybeDisabledTag>
			<div>
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
						selectedUsers={ selectedUsers }
						setSelectUsers={ setSelectUsers }
						userSuggestions={ userSuggestions }
						setUserSuggestions={ setUserSuggestions }
					/>
				) : null }
				<p className="SimpleHistory__filters__filterSubmitWrap">
					<button className="button" onClick={ onReload }>
						{ __( 'Search events', 'simple-history' ) }
					</button>

					<button
						type="button"
						onClick={ () =>
							setMoreOptionsIsExpanded( ! moreOptionsIsExpanded )
						}
						className="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"
					>
						{ showMoreOrLessText }
					</button>
				</p>
			</div>
		</MaybeDisabledTag>
	);
}
