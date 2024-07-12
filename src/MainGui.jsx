import apiFetch from "@wordpress/api-fetch";
import { useCallback, useEffect, useState } from "@wordpress/element";
import { addQueryArgs } from "@wordpress/url";
import { EventsList } from "./EventsList";
import { EventsSearchFilters } from "./EventsSearchFilters";

function MainGui() {
	const [eventsIsLoading, setEventsIsLoading] = useState(true);
	const [events, setEvents] = useState([]);
	const [eventsReloadTime, setEventsReloadTime] = useState(Date.now());

	const [selectedLogLevels, setSelectedLogLevels] = useState([]);
	const [selectedMessageTypes, setSelectedMessageTypes] = useState([]);
	const [selectedUsers, setSelectUsers] = useState([]);
	const [selectedDateOption, setSelectedDateOption] = useState("");
	const [enteredSearchText, setEnteredSearchText] = useState("");

	const eventsQueryParams = { _fields: ["id", "date", "message"] };

	const loadEvents = useCallback(async () => {
		setEventsIsLoading(true);
		apiFetch({
			path: addQueryArgs("/simple-history/v1/events", eventsQueryParams),
		}).then((events) => {
			setEvents(events);
			setEventsIsLoading(false);
		});
	}, []);

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
				onReload={handleReload}
			/>

			<EventsList events={events} eventsIsLoading={eventsIsLoading} />
		</div>
	);
}

export default MainGui;
