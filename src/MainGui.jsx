import { addQueryArgs } from "@wordpress/url";
import { __, _x } from "@wordpress/i18n";
import {
	SearchControl,
	Modal,
	Icon,
	SVG,
	Path,
	ToggleControl,
	ExternalLink,
	DatePicker,
	CustomSelectControl,
	Button,
	Card,
	CardDivider,
	CardMedia,
	CardHeader,
	CardBody,
	CardFooter,
	__experimentalText as Text,
	__experimentalHeading as Heading,
	Animate,
	Notice,
	Tip,
	TextHighlight,
	Spinner,
	SelectControl,
	__experimentalVStack as VStack,
	Flex,
	FlexBlock,
	FlexItem,
	__experimentalHStack as HStack,
	CheckboxControl,
	FormTokenField,
	BaseControl,
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { format, dateI18n, getSettings } from "@wordpress/date";

const LOGLEVELS_OPTIONS = [
	{
		label: __("Info", "simple-history"),
		value: "info",
	},
	{
		label: __("Warning", "simple-history"),
		value: "warning",
	},
	{
		label: __("Error", "simple-history"),
		value: "error",
	},
	{
		label: __("Critical", "simple-history"),
		value: "critical",
	},
	{
		label: __("Alert", "simple-history"),
		value: "alert",
	},
	{
		label: __("Emergency", "simple-history"),
		value: "emergency",
	},
	{
		label: __("Debug", "simple-history"),
		value: "debug",
	},
];

function MoreFilters(props) {
	const { messageTypesSuggestions, userSuggestions, setUserSuggestions } =
		props;
	const [selectedLogLevels, setSelectedLogLevels] = useState([]);
	const [selectedMessageTypes, setSelectedMessageTypes] = useState([]);
	const [selectedUsers, setSelectUsers] = useState([]);

	// Generate loglevels suggestions based on LOGLEVELS_OPTIONS.
	// This way we can find the original untranslated label.
	const LOGLEVELS_SUGGESTIONS = LOGLEVELS_OPTIONS.map((logLevel) => {
		return logLevel.label;
	});

	const searchUsers = async (searchText) => {
		if (searchText.length < 2) {
			return;
		}

		apiFetch({
			path: addQueryArgs("/simple-history/v1/search-user", {
				q: searchText,
			}),
		}).then((searchUsersResponse) => {
			console.log("searchUsersResponse", searchUsersResponse);
			let userSuggestions = [];
			searchUsersResponse.map((user) => {
				userSuggestions.push(`${user.display_name} (${user.user_email})`);
			});
			console.log("userSuggestions", userSuggestions);
			setUserSuggestions(userSuggestions);
		});

		console.log("searchUsers", searchText);
	};

	return (
		<div className="">
			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Log levels", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All log levels", "simple-history")}
							onChange={(nextValue) => {
								setSelectedLogLevels(nextValue);
							}}
							suggestions={LOGLEVELS_SUGGESTIONS}
							value={selectedLogLevels}
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Message types", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All message types", "simple-history")}
							onChange={(nextValue) => {
								setSelectedMessageTypes(nextValue);
							}}
							suggestions={messageTypesSuggestions}
							value={selectedMessageTypes}
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0">
				<FlexItem style={{ margin: "1em 0" }}>
					<label className="SimpleHistory__filters__filterLabel">
						{__("Users", "simple-history")}
					</label>
				</FlexItem>
				<FlexBlock>
					<div
						class="SimpleHistory__filters__loglevels__select"
						style={{
							width: "310px",
							backgroundColor: "white",
						}}
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={false}
							label=""
							placeholder={__("All users", "simple-history")}
							onChange={(nextValue) => {
								setSelectUsers(nextValue);
							}}
							onInputChange={(value) => {
								searchUsers(value);
							}}
							suggestions={userSuggestions}
							value={selectedUsers}
						/>
					</div>
					<BaseControl
						__nextHasNoMarginBottom
						help="Enter 2 or more characters to search for users."
					/>
				</FlexBlock>
			</Flex>
		</div>
	);
}

const DEFAULT_DATE_OPTIONS = [
	{
		label: __("Custom date range...", "simple-history"),
		value: "customRange",
	},
	{
		label: __("Last day", "simple-history"),
		value: "lastdays:1",
	},
	{
		label: __("Last 7 days", "simple-history"),
		value: "lastdays:7",
	},
	{
		label: __("Last 14 days", "simple-history"),
		value: "lastdays:14",
	},
	{
		label: __("Last 30 days", "simple-history"),
		value: "lastdays:30",
	},
	{
		label: __("Last 60 days", "simple-history"),
		value: "lastdays:60",
	},
];

const OPTIONS_LOADING = [
	{
		label: __("Loading...", "simple-history"),
		value: "",
	},
];

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 */
function Filters() {
	const [moreOptionsIsExpanded, setMoreOptionsIsExpanded] = useState(true);
	const [dateOptions, setDateOptions] = useState(OPTIONS_LOADING);
	const [selectedDateOption, setSelectedDateOption] = useState();
	const [messageTypes, setMessageTypes] = useState(OPTIONS_LOADING);
	const [searchText, setSearchText] = useState("");
	const [messageTypesSuggestions, setMessageTypesSuggestions] = useState([]);
	const [userSuggestions, setUserSuggestions] = useState([]);

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

			//let messageTypes = setMessageTypes(searchOptions.loggers);
			let messageTypesSuggestions = [];
			searchOptions.loggers.map((logger) => {
				const search_data = logger.search_data || {};
				if (!search_data.search) {
					return;
				}

				// "WordPress och tilläggsuppdateringar"
				messageTypesSuggestions.push(search_data.search);

				const subitemPrefix = " – ";

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
		});
	}, []);

	const showMoreOrLessText = moreOptionsIsExpanded
		? __("Collapse search options", "simple-history")
		: __("Show search options", "simple-history");

	return (
		<div>
			<p>
				<label className="SimpleHistory__filters__filterLabel">
					{__("Dates", "simple-history")}
				</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						options={dateOptions}
						value={selectedDateOption}
						onChange={(value) => setSelectedDateOption(value)}
					/>
				</div>
			</p>
			<p>
				<label className="SimpleHistory__filters__filterLabel">
					Containing words:
				</label>
				<input
					type="search"
					className="SimpleHistoryFilterDropin-searchInput"
					value={searchText}
					onChange={(event) => setSearchText(event.target.value)}
				/>
			</p>

			{moreOptionsIsExpanded ? (
				<MoreFilters
					messageTypes={messageTypes}
					messageTypesSuggestions={messageTypesSuggestions}
					userSuggestions={userSuggestions}
					setUserSuggestions={setUserSuggestions}
				/>
			) : null}

			<p class="SimpleHistory__filters__filterSubmitWrap">
				<button className="button" onClick={function noRefCheck() {}}>
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
	);
}

function TestCard() {
	return (
		<Card>
			<React.Fragment>
				<CardHeader>
					<Heading>CardHeader</Heading>
				</CardHeader>
				<CardBody>
					<Text>CardBody</Text>
				</CardBody>
				<CardBody>
					<Text>CardBody (before CardDivider)</Text>
				</CardBody>
				<CardDivider />
				<CardBody>
					<Text>CardBody (after CardDivider)</Text>
				</CardBody>
				<CardMedia>
					<img
						alt="Card Media"
						src="https://images.unsplash.com/photo-1566125882500-87e10f726cdc?ixlib=rb-1.2.1&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1867&q=80"
					/>
				</CardMedia>
				<CardFooter>
					<Text>CardFooter</Text>
					<Button variant="secondary">Action Button</Button>
				</CardFooter>
			</React.Fragment>
		</Card>
	);
}

function EventsList(props) {
	const { events } = props;

	if (!events) {
		return <p>Loading...</p>;
	}

	return (
		<div>
			<h2>Events</h2>
			<ul>
				{events.map((event) => (
					<li key={event.id}>
						{event.date} - {event.message}
					</li>
				))}
			</ul>
		</div>
	);
}

function TestApp() {
	const [events, setEvents] = useState([]);
	const [date, setDate] = useState(new Date());
	const queryParams = { _fields: ["id", "date", "message"] };

	useEffect(() => {
		apiFetch({
			path: addQueryArgs("/simple-history/v1/events", queryParams),
		}).then((events) => {
			setEvents(events);
		});
	}, []);

	return (
		<div>
			<Filters />
			<EventsList events={events} />
		</div>
	);
}

export default TestApp;
