import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";
import { EventsList } from "./EventsList";
import { EventsSearchFilters } from "./EventsSearchFilters";

function MainGui() {
	const [eventsIsLoading, setEventsIsLoading] = useState(true);
	const [events, setEvents] = useState([]);
	const [eventsReloadTime, setEventsReloadTime] = useState(Date.now());

	const [selectedDateOption, setSelectedDateOption] = useState("");
	const [selectedCustomDateFrom, setSelectedCustomDateFrom] = useState(null);
	const [selectedCustomDateTo, setSelectedCustomDateTo] = useState(null);
	const [enteredSearchText, setEnteredSearchText] = useState("");
	const [selectedLogLevels, setSelectedLogLevels] = useState([]);
	const [selectedMessageTypes, setSelectedMessageTypes] = useState([]);
	const [selectedUsers, setSelectUsers] = useState([]);

	const loadEvents = useCallback(async () => {
		// Create query params based on selected filters.
		const eventsQueryParams = { _fields: ["id", "date", "message"] };

		console.log("selectedLogLevels", selectedLogLevels);
		console.log("selectedMessageTypes", selectedMessageTypes);
		console.log("selectedUsers", selectedUsers);
		console.log("selectedDateOption", selectedDateOption);
		console.log("enteredSearchText", enteredSearchText);

		setEventsIsLoading(true);
		apiFetch({
			path: addQueryArgs("/simple-history/v1/events", eventsQueryParams),
		}).then((events) => {
			setEvents(events);
			setEventsIsLoading(false);
		});
	}, [
		selectedLogLevels,
		selectedMessageTypes,
		selectedUsers,
		selectedDateOption,
		enteredSearchText,
		selectedCustomDateFrom,
		selectedCustomDateTo,
	]);

	useEffect(() => {
		console.log("loadEvents in useEffect");
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
				onReload={handleReload}
			/>

			<EventsList events={events} eventsIsLoading={eventsIsLoading} />
		</div>
	);
}

export default MainGui;
