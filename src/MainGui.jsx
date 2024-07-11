import { addQueryArgs } from "@wordpress/url";
import { __ } from "@wordpress/i18n";
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
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

console.log("Hello, world from Simple History index.js!");

console.log("Fetching posts from the REST API...");
apiFetch({ path: "/wp/v2/posts" }).then((posts) => {
	console.log(posts);
});

console.log("Fetching events from the REST API...");
apiFetch({ path: "/simple-history/v1/events" }).then((events) => {
	console.log(events);
});

const queryParams = { _fields: ["id", "date", "message"] };

console.log("Fetching events with args");
apiFetch({ path: addQueryArgs("/simple-history/v1/events", queryParams) }).then(
	(posts) => {
		console.log(posts);
	},
);

function MoreFilters() {
	const LOGLEVELS_OPTIONS = [
		{
			label: __("Debug", "simple-history"),
			value: "debug",
		},
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
	];

	return (
		<div className="">
			<p>
				<label className="SimpleHistory__filters__filterLabel">
					Log levels:
				</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						onBlur={function noRefCheck() {}}
						onChange={function noRefCheck() {}}
						onFocus={function noRefCheck() {}}
						options={LOGLEVELS_OPTIONS}
					/>
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

/**
 * Search component with a search input visible by default.
 * A "Show search options" button is visible where the user can expand the search to show more options/filters.
 */
function Filters() {
	const [showMoreOptions, setShowMoreOptions] = useState(false);

	const datesOptions = [
		{
			label: __("Today", "simple-history"),
			value: "today",
		},
		{
			label: __("Yesterday", "simple-history"),
			value: "yesterday",
		},
		{
			label: __("Last 7 days", "simple-history"),
			value: "last_7_days",
		},
		{
			label: __("Last 30 days", "simple-history"),
			value: "last_30_days",
		},
		{
			label: __("Custom", "simple-history"),
			value: "custom",
		},
	];

	const showMoreOrLessText = showMoreOptions
		? __("Collapse search options", "simple-history")
		: __("Show search options", "simple-history");

	return (
		<div style={{}}>
			<p>
				<label className="SimpleHistory__filters__filterLabel">Dates:</label>
				<div style={{ display: "inline-block", width: "310px" }}>
					<SelectControl
						options={datesOptions}
						onBlur={function noRefCheck() {}}
						onChange={function noRefCheck() {}}
						onFocus={function noRefCheck() {}}
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
				/>
			</p>

			{showMoreOptions ? <MoreFilters /> : null}

			<p class="SimpleHistory__filters__filterSubmitWrap">
				<button className="button" onClick={function noRefCheck() {}}>
					{__("Search events", "simple-history")}
				</button>

				<button
					type="button"
					onClick={() => setShowMoreOptions(!showMoreOptions)}
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
