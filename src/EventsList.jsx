import {
	Button,
	DropdownMenu,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	MenuGroup,
	MenuItem,
	__experimentalSpacer as Spacer,
	Spinner,
	__experimentalText as Text,
} from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
import { __, _x } from "@wordpress/i18n";
import { moreVertical, update } from "@wordpress/icons";
import { clsx } from "clsx";
import { Event } from "./components/Event";
import { EventsPagination } from "./components/EventsPagination";

/**
 * Renders a list of events.
 */
export function EventsList(props) {
	const { page, setPage, events, eventsIsLoading, eventsMeta } = props;
	const totalPages = eventsMeta.totalPages;

	const ulClasses = clsx({
		SimpleHistoryLogitems: true,
		"is-loading": eventsIsLoading,
	});

	return (
		<div style={{ backgroundColor: "white", minHeight: "300px" }}>
			<ul className={ulClasses}>
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
