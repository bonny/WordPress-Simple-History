import {
	DropdownMenu,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	MenuGroup,
	MenuItem,
	__experimentalSpacer as Spacer,
	Spinner
} from "@wordpress/components";
import { __, _x } from "@wordpress/i18n";
import {
	moreVertical
} from "@wordpress/icons";
import { Event } from "./components/Event";
import { EventsPagination } from "./components/EventsPagination";

const PremiumFeatureSuffix = function () {
	return (
		<span
			style={{
				color: "darkgreen",
				fontSize: "0.8em",
				border: "1px solid darkgreen",
				borderRadius: "5px",
				padding: "0.2em 0.5em",
			}}
		>
			Premium feature
		</span>
	);
};

const MyDropdownMenu = () => (
	<DropdownMenu label={__("Actions...", "simple-history")} icon={moreVertical}>
		{({ onClose }) => (
			<>
				<MenuGroup>
					<MenuItem onClick={onClose} info="This is info">
						Save search
					</MenuItem>
					<MenuItem onClick={onClose} suffix="This is suffix">
						Copy link to search
					</MenuItem>
					<MenuItem onClick={onClose} suffix={<PremiumFeatureSuffix />}>
						Premium thing
					</MenuItem>
				</MenuGroup>
				<MenuGroup label="Export">
					<MenuItem onClick={onClose} suffix={<PremiumFeatureSuffix />}>
						Export as CSV...
					</MenuItem>
					<MenuItem onClick={onClose} suffix={<PremiumFeatureSuffix />}>
						Export as JSON
					</MenuItem>
				</MenuGroup>
				<MenuGroup>
					<MenuItem onClick={onClose} suffix={<PremiumFeatureSuffix />}>
						Add manual event/entry/message
					</MenuItem>
				</MenuGroup>
			</>
		)}
	</DropdownMenu>
);

/**
 * Control bar at the top with number of events, reload button, more actions like export and so on.
 */
function EventsControlBar(props) {
	const { eventsIsLoading, eventsTotal } = props;

	const loadingIndicator = eventsIsLoading ? (
		<div>
			<Spinner />

			{_x(
				"Loading ...",
				"Message visible while waiting for log to load from server the first time",
				"simple-history",
			)}
		</div>
	) : null;

	return (
		<div style={{ backgroundColor: "white" }}>
			<Flex gap={4}>
				<FlexItem>
					<HStack spacing={2}>
						<div style={{ marginLeft: "1rem" }}>{eventsTotal} events</div>
						<div>1 new event</div>
						{loadingIndicator}
					</HStack>
				</FlexItem>

				<FlexItem>
					<MyDropdownMenu />
				</FlexItem>
			</Flex>
		</div>
	);
}

/**
 * Renders a list of events.
 */
export function EventsList(props) {
	const { page, setPage, events, eventsIsLoading, eventsMeta } = props;
	const totalPages = eventsMeta.totalPages;

	return (
		<div style={{ backgroundColor: "white" }}>
			<EventsControlBar
				eventsIsLoading={eventsIsLoading}
				eventsTotal={eventsMeta.total}
			/>

			<Spacer margin={4} />

			<ul className="SimpleHistoryLogitems">
				{events.map((event) => (
					<Event key={event.id} event={event} />
				))}
			</ul>

			<Spacer margin={4} />

			<EventsPagination
				page={page}
				totalPages={totalPages}
				onClickPrev={() => setPage(page - 1)}
				onClickNext={() => setPage(page + 1)}
				onChangePage={(newPage) => setPage(parseInt(newPage, 10))}
			/>

			<Spacer paddingBottom={4} />
		</div>
	);
}
