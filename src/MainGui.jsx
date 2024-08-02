import apiFetch from "@wordpress/api-fetch";
import { useDebounce } from "@wordpress/compose";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";
import { endOfDay, format, startOfDay } from "date-fns";
import { LOGLEVELS_OPTIONS, TIMEZONELESS_FORMAT } from "./constants";
import { EventsList } from "./EventsList";
import { EventsSearchFilters } from "./EventsSearchFilters";

const defaultStartDate = format(startOfDay(new Date()), TIMEZONELESS_FORMAT);
const defaultEndDate = format(endOfDay(new Date()), TIMEZONELESS_FORMAT);

function MainGui() {
	const [eventsIsLoading, setEventsIsLoading] = useState(true);
	const [events, setEvents] = useState([]);
	const [eventsMeta, setEventsMeta] = useState({});
	const [eventsReloadTime, setEventsReloadTime] = useState(Date.now());
	const [searchOptionsLoaded, setSearchOptionsLoaded] = useState(false);
	const [page, setPage] = useState(1);
	const [pagerSize, setPagerSize] = useState({});
	const [selectedDateOption, setSelectedDateOption] = useState("");
	const [selectedCustomDateFrom, setSelectedCustomDateFrom] =
		useState(defaultStartDate);
	const [selectedCustomDateTo, setSelectedCustomDateTo] =
		useState(defaultEndDate);
	const [enteredSearchText, setEnteredSearchText] = useState("");
	const [selectedLogLevels, setSelectedLogLevels] = useState([]);
	const [messageTypesSuggestions, setMessageTypesSuggestions] = useState([]);
	const [selectedMessageTypes, setSelectedMessageTypes] = useState([]);
	const [userSuggestions, setUserSuggestions] = useState([]);
	const [selectedUsers, setSelectUsers] = useState([]);

	const loadEvents = useCallback(async () => {
		// Create query params based on selected filters.
		let eventsQueryParams = {
			page: page,
			per_page: pagerSize.page,
			_fields: [
				"id",
				"date",
				"date_gmt",
				"message",
				"message_html",
				"details_data",
				"details_html",
				"loglevel",
				"occasions_id",
				"subsequent_occasions_count",
				"initiator",
				"initiator_data",
				"via",
			],
		};

		if (enteredSearchText) {
			eventsQueryParams.search = enteredSearchText;
		}

		if (selectedDateOption) {
			if (selectedDateOption === "customRange") {
				eventsQueryParams.date_from = selectedCustomDateFrom;
				eventsQueryParams.date_to = selectedCustomDateTo;
			} else {
				eventsQueryParams.dates = selectedDateOption;
			}
		}

		if (selectedLogLevels.length) {
			// Values in selectedLogLevels are the labels of the log levels, not the values we can use in the API.
			// Use the LOGLEVELS_OPTIONS to find the value for the translated label.
			let selectedLogLevelsValues = [];
			selectedLogLevels.forEach((selectedLogLevel) => {
				let logLevelOption = LOGLEVELS_OPTIONS.find(
					(logLevelOption) => logLevelOption.label === selectedLogLevel,
				);
				if (logLevelOption) {
					selectedLogLevelsValues.push(logLevelOption.value);
				}
			});
			if (selectedLogLevelsValues.length) {
				eventsQueryParams.loglevels = selectedLogLevelsValues;
			}
		}

		if (selectedMessageTypes.length) {
			let selectedMessageTypesValues = [];
			selectedMessageTypes.forEach((selectedMessageType) => {
				let messageTypeOption = messageTypesSuggestions.find(
					(messageTypeOption) => {
						return (
							messageTypeOption.label.trim() === selectedMessageType.trim()
						);
					},
				);

				if (messageTypeOption) {
					selectedMessageTypesValues.push(messageTypeOption);
				}
			});

			if (selectedMessageTypesValues.length) {
				let messsagesString = "";
				selectedMessageTypesValues.forEach((selectedMessageTypesValue) => {
					selectedMessageTypesValue.search_options.forEach((searchOption) => {
						messsagesString += searchOption + ",";
					});
				});
				eventsQueryParams.messages = messsagesString;
			}
		}

		if (selectedUsers.length) {
			let selectedUsersValues = [];
			selectedUsers.forEach((selectedUserNameAndEmail) => {
				let userSuggestion = userSuggestions.find((userSuggestion) => {
					return (
						userSuggestion.label.trim() === selectedUserNameAndEmail.trim()
					);
				});

				if (userSuggestion) {
					selectedUsersValues.push(userSuggestion.id);
				}
			});

			if (selectedUsersValues.length) {
				eventsQueryParams.users = selectedUsersValues;
			}
		}

		setEventsIsLoading(true);

		const eventsResponse = await apiFetch({
			path: addQueryArgs("/simple-history/v1/events", eventsQueryParams),
			// Skip parsing to be able to retrieve headers.
			parse: false,
		});

		const eventsJson = await eventsResponse.json();

		setEventsMeta({
			total: parseInt(eventsResponse.headers.get("X-Wp-Total"), 10),
			totalPages: parseInt(eventsResponse.headers.get("X-Wp-Totalpages"), 10),
			link: eventsResponse.headers.get("Link"),
		});

		setEvents(eventsJson);
		setEventsIsLoading(false);
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsers,
		enteredSearchText,
		selectedDateOption,
		selectedCustomDateFrom,
		selectedCustomDateTo,
		page,
	]);

	// Debounce the loadEvents function to avoid multiple calls when user types fast.
	const debouncedLoadEvents = useDebounce(loadEvents, 500);

	useEffect(() => {
		// Wait for search options to be loaded before loading events,
		// or the loadEvents will be called twice.
		if (!searchOptionsLoaded) {
			return;
		}

		debouncedLoadEvents();
	}, [debouncedLoadEvents, searchOptionsLoaded, eventsReloadTime]);

	/**
	 * Function to set reload time to current time,
	 * which will trigger a reload of the events.
	 * This is used as a callback function for child components,
	 * for example for the search button in the search component.
	 */
	const handleReload = () => {
		setEventsReloadTime(Date.now());
	};

	return (
		<div>
			<EventsSearchFilters
				selectedLogLevels={selectedLogLevels}
				setSelectedLogLevels={setSelectedLogLevels}
				selectedMessageTypes={selectedMessageTypes}
				setSelectedMessageTypes={setSelectedMessageTypes}
				selectedUsers={selectedUsers}
				setSelectUsers={setSelectUsers}
				selectedDateOption={selectedDateOption}
				setSelectedDateOption={setSelectedDateOption}
				enteredSearchText={enteredSearchText}
				setEnteredSearchText={setEnteredSearchText}
				selectedCustomDateFrom={selectedCustomDateFrom}
				setSelectedCustomDateFrom={setSelectedCustomDateFrom}
				selectedCustomDateTo={selectedCustomDateTo}
				setSelectedCustomDateTo={setSelectedCustomDateTo}
				messageTypesSuggestions={messageTypesSuggestions}
				setMessageTypesSuggestions={setMessageTypesSuggestions}
				userSuggestions={userSuggestions}
				setUserSuggestions={setUserSuggestions}
				searchOptionsLoaded={searchOptionsLoaded}
				setSearchOptionsLoaded={setSearchOptionsLoaded}
				pagerSize={pagerSize}
				setPagerSize={setPagerSize}
				page={page}
				setPage={setPage}
				onReload={handleReload}
			/>

			<EventsList
				eventsIsLoading={eventsIsLoading}
				events={events}
				eventsMeta={eventsMeta}
				page={page}
				setPage={setPage}
			/>
		</div>
	);
}

export default MainGui;