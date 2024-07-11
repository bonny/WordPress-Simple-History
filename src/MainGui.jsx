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
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { format, dateI18n, getSettings } from "@wordpress/date";

function MoreFilters() {
	const [selectedLogLevels, setSelectedLogLevels] = useState([]);

	const LOGLEVELS_OPTIONS = [
		// {
		// 	label: __("All", "simple-history"),
		// 	value: "",
		// },
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

	const LOGLEVELS_SUGGESTIONS = LOGLEVELS_OPTIONS.map((logLevel) => {
		return logLevel.label;
	});

	console.log("selectedLogLevel", selectedLogLevels);

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

			<p>
				<label className="SimpleHistory__filters__filterLabel">
					{__("Log levels", "simple-history")}
				</label>
				<div style={{ display: "inline-block", xwidth: "310px" }}>
					<HStack spacing="5">
						{LOGLEVELS_OPTIONS.map((logLevel) => (
							<CheckboxControl
								label={logLevel.label}
								checked={false}
								onChange={function noRefCheck() {}}
							/>
						))}
					</HStack>
				</div>
			</p>

			<p>
				<label className="SimpleHistory__filters__filterLabel">
					Message types:
				</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						style={{ width: "310px" }}
						onBlur={function noRefCheck() {}}
						onChange={function noRefCheck() {}}
						onFocus={function noRefCheck() {}}
						options={[
							{
								disabled: true,
								label: "Select an Option",
								value: "",
							},
							{
								label: "WordPress updates",
								value: "wordpress_updates",
							},
						]}
					/>
				</div>
			</p>

			<p>
				<label className="SimpleHistory__filters__filterLabel">Users</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						style={{ width: "310px" }}
						onBlur={function noRefCheck() {}}
						onChange={function noRefCheck() {}}
						onFocus={function noRefCheck() {}}
						options={[
							{
								disabled: true,
								label: "Select an Option",
								value: "",
							},
							{
								label: "User a",
								value: "",
							},
						]}
					/>
				</div>
			</p>
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

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 */
function Filters() {
	const [moreOptionsIsExpanded, setMoreOptionsIsExpanded] = useState(true);
	const [dateOptions, setDateOptions] = useState(DEFAULT_DATE_OPTIONS);
	const [selectedDateOption, setSelectedDateOption] = useState();
	const [searchText, setSearchText] = useState("");

	// Load search options when component mounts.
	useEffect(() => {
		apiFetch({
			path: addQueryArgs("/simple-history/v1/search-options"),
		}).then((searchOptions) => {
			setSelectedDateOption(`lastdays:${searchOptions.dates.daysToShow}`);

			// Append result_months and all dates to dateOptions.
			const monthsOptions = searchOptions.dates.result_months.map((row) => {
				// Format the date according to the locale
				const formattedDate = dateI18n("F Y", row.yearMonth);

				return {
					label: `${formattedDate}`,
					value: `month:${row.yearMonth}`,
				};
			});

			const allDatesOption = {
				label: __("All dates", "simple-history"),
				value: "allDates",
			};

			setDateOptions([
				...DEFAULT_DATE_OPTIONS,
				...monthsOptions,
				allDatesOption,
			]);
		});
	}, []);

	const showMoreOrLessText = moreOptionsIsExpanded
		? __("Collapse search options", "simple-history")
		: __("Show search options", "simple-history");

	return (
		<div>
			<p>
				<label className="SimpleHistory__filters__filterLabel">Dates:</label>
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
			{moreOptionsIsExpanded ? <MoreFilters /> : null}
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
		}).then((posts) => {
			console.log(posts);
			setEvents(posts);
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
