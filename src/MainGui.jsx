import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";
import { EventsList } from "./EventsList";
import { EventsSearchFilters } from "./EventsSearchFilters";
import { useDebounce } from "@wordpress/compose";
import { endOfDay, startOfDay, format } from "date-fns";
import { TIMEZONELESS_FORMAT } from "./constants";
import { LOGLEVELS_OPTIONS } from "./constants";
import { SUBITEM_PREFIX } from "./constants";

const defaultStartDate = format(startOfDay(new Date()), TIMEZONELESS_FORMAT);
const defaultEndDate = format(endOfDay(new Date()), TIMEZONELESS_FORMAT);

function MainGui() {
	const [eventsIsLoading, setEventsIsLoading] = useState(true);
	const [events, setEvents] = useState([]);
	const [eventsMeta, setEventsMeta] = useState({});
	const [eventsReloadTime, setEventsReloadTime] = useState(Date.now());

	const [selectedDateOption, setSelectedDateOption] = useState("");
	const [selectedCustomDateFrom, setSelectedCustomDateFrom] =
		useState(defaultStartDate);
	const [selectedCustomDateTo, setSelectedCustomDateTo] =
		useState(defaultEndDate);
	const [enteredSearchText, setEnteredSearchText] = useState("");
	const [selectedLogLevels, setSelectedLogLevels] = useState([]);
	const [messageTypesSuggestions, setMessageTypesSuggestions] = useState([]);
	const [selectedMessageTypes, setSelectedMessageTypes] = useState([]);
	const [selectedUsers, setSelectUsers] = useState([]);

	const loadEvents = useCallback(async () => {
		// Create query params based on selected filters.
		let eventsQueryParams = {
			per_page: 5,
			_fields: ["id", "date", "message"],
		};

		// console.log("selectedUsers", selectedUsers);
		// console.log("selectedCustomDateFrom", selectedCustomDateFrom);

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
			console.log("selectedMessageTypes", selectedMessageTypes);
			console.log("messageTypesSuggestions", messageTypesSuggestions);
			let selectedMessageTypesValues = [];
			selectedMessageTypes.forEach((selectedMessageType) => {
				console.log("selectedMessageType", selectedMessageType);
				let messageTypeOption = messageTypesSuggestions.find(
					(messageTypeOption) => {
						return (
							messageTypeOption.label.trim() === selectedMessageType.trim()
						);
					},
				);

				console.log("messageTypeOption", messageTypeOption);
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
				console.log('messsagesString', messsagesString);
				eventsQueryParams.messages = messsagesString;
			}
			console.log("selectedMessageTypesValues", selectedMessageTypesValues);
			// array with translated logger and messages, i.e:
			// "WordPress och tilläggsuppdateringar hittades"
			// "- Misslyckade inloggningar av användare"
			//eventsQueryParams.message_types = selectedMessageTypes;
		}

		setEventsIsLoading(true);
		const eventsResponse = await apiFetch({
			path: addQueryArgs("/simple-history/v1/events", eventsQueryParams),
			// Skip parsing to be able to retrieve headers.
			parse: false,
		});

		const eventsJson = await eventsResponse.json();

		setEventsMeta({
			total: eventsResponse.headers.get("X-Wp-Total"),
			totalPages: eventsResponse.headers.get("X-Wp-Totalpages"),
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
	]);

	useEffect(() => {
		// console.log("loadEvents in useEffect", loadEvents, eventsReloadTime);
		loadEvents();
	}, [loadEvents, eventsReloadTime]);

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
				onReload={handleReload}
			/>

			<EventsList
				eventsIsLoading={eventsIsLoading}
				events={events}
				eventsMeta={eventsMeta}
			/>
		</div>
	);
}

export default MainGui;
