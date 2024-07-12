import apiFetch from "@wordpress/api-fetch";
import { Disabled } from "@wordpress/components";
import { dateI18n } from "@wordpress/date";
import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { addQueryArgs } from "@wordpress/url";
import { DEFAULT_DATE_OPTIONS, OPTIONS_LOADING } from "./constants";
import { DefaultFilters } from "./DefaultFilters";
import { ExpandedFilters } from "./ExpandedFilters";

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 */
export function EventsSearchFilters(props) {
	const {
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
		onReload,
	} = props;

	const [moreOptionsIsExpanded, setMoreOptionsIsExpanded] = useState(false);
	const [dateOptions, setDateOptions] = useState(OPTIONS_LOADING);
	const [messageTypesSuggestions, setMessageTypesSuggestions] = useState([]);
	const [searchOptionsLoaded, setSearchOptionsLoaded] = useState(false);

	// Load search options when component mounts.
	useEffect(() => {
		apiFetch({
			path: addQueryArgs("/simple-history/v1/search-options", {}),
		}).then((searchOptions) => {
			// Append result_months and all dates to dateOptions.
			const monthsOptions = searchOptions.dates.result_months.map((row) => ({
				label: dateI18n("F Y", row.yearMonth),
				value: `month:${row.yearMonth}`,
			}));

			const allDatesOption = {
				label: __("All dates", "simple-history"),
				value: "allDates",
			};

			setDateOptions([
				...DEFAULT_DATE_OPTIONS,
				...monthsOptions,
				allDatesOption,
			]);

			setSelectedDateOption(`lastdays:${searchOptions.dates.daysToShow}`);

			let messageTypesSuggestions = [];
			searchOptions.loggers.map((logger) => {
				const search_data = logger.search_data || {};
				if (!search_data.search) {
					return;
				}

				// "WordPress och tilläggsuppdateringar"
				messageTypesSuggestions.push(search_data.search);

				const subitemPrefix = " - ";

				// "Alla hittade uppdateringar"
				if (search_data?.search_all?.label) {
					messageTypesSuggestions.push(
						subitemPrefix + search_data.search_all.label,
					);
				}

				// Each single message.
				if (search_data?.search_options) {
					search_data.search_options.forEach((option) => {
						messageTypesSuggestions.push(subitemPrefix + option.label);
					});
				}
			});

			setMessageTypesSuggestions(messageTypesSuggestions);
			setSearchOptionsLoaded(true);
		});
	}, []);

	const showMoreOrLessText = moreOptionsIsExpanded
		? __("Collapse search options", "simple-history")
		: __("Show search options", "simple-history");

	// Dynamic created <Disabled> elements. Used to disable the whole search component while loading.
	let MaybeDisabledTag = searchOptionsLoaded ? React.Fragment : Disabled;

	return (
		<MaybeDisabledTag>
			<div>
				<DefaultFilters
					dateOptions={dateOptions}
					selectedDateOption={selectedDateOption}
					setSelectedDateOption={setSelectedDateOption}
					searchText={enteredSearchText}
					setSearchText={setEnteredSearchText}
				/>

				{moreOptionsIsExpanded ? (
					<ExpandedFilters
						messageTypesSuggestions={messageTypesSuggestions}
						selectedLogLevels={selectedLogLevels}
						setSelectedLogLevels={setSelectedLogLevels}
						selectedMessageTypes={selectedMessageTypes}
						setSelectedMessageTypes={setSelectedMessageTypes}
						selectedUsers={selectedUsers}
						setSelectUsers={setSelectUsers}
					/>
				) : null}

				<p class="SimpleHistory__filters__filterSubmitWrap">
					<button className="button" onClick={onReload}>
						{__("Search events", "simple-history")}
					</button>

					<button
						type="button"
						onClick={() => setMoreOptionsIsExpanded(!moreOptionsIsExpanded)}
						className="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"
					>
						{showMoreOrLessText}
					</button>
				</p>
			</div>
		</MaybeDisabledTag>
	);
}
