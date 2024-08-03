import apiFetch from "@wordpress/api-fetch";
import { useState } from "@wordpress/element";
import { __, _n, sprintf } from "@wordpress/i18n";
import { addQueryArgs } from "@wordpress/url";
import { EventOccasionsList } from "./EventOccasionsList";

export function EventOccasions(props) {
	const { event } = props;
	const { subsequent_occasions_count } = event;
	const [isLoadingOccasions, setIsLoadingOccasions] = useState(false);
	const [isShowingOccasions, setIsShowingOccasions] = useState(false);
	const [occasions, setOccasions] = useState([]);
	const occasionsCountMaxReturn = 15;

	// The current event is the only occasion.
	if (subsequent_occasions_count === 1) {
		return null;
	}

	const loadOccasions = async (evt) => {
		/*
		Old request data:
		action: simple_history_api
		type: occasions
		format: html
		logRowID: 18990
		occasionsID: 6784b67ada1c0e81d8fae41f591a00d3
		occasionsCount: 225
		occasionsCountMaxReturn: 15
		*/
		console.log(
			"loadOccasions for event with id, occasions_id, subsequent_occasions_count",
			event.id,
			event.occasions_id,
			subsequent_occasions_count,
		);

		setIsLoadingOccasions(true);

		let eventsQueryParams = {
			type: "occasions",
			logRowID: event.id,
			occasionsID: event.occasions_id,
			occasionsCount: subsequent_occasions_count - 1,
			occasionsCountMaxReturn: occasionsCountMaxReturn,
			per_page: 5,
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

		const eventsResponse = await apiFetch({
			path: addQueryArgs("/simple-history/v1/events", eventsQueryParams),
			// Skip parsing to be able to retrieve headers.
			parse: false,
		});

		const responseJson = await eventsResponse.json();

		console.log("eventsResponseJson", responseJson);

		setOccasions(responseJson);
		setIsLoadingOccasions(false);
		setIsShowingOccasions(true);
	};

	return (
		<div class="">
			{!isShowingOccasions && !isLoadingOccasions ? (
				<a
					href="#"
					class=""
					onClick={(evt) => {
						loadOccasions();
						evt.preventDefault();
					}}
				>
					{sprintf(
						_n(
							"+%1$s similar event",
							"+%1$s similar events",
							subsequent_occasions_count,
							"simple-history",
						),
						subsequent_occasions_count,
					)}
				</a>
			) : null}

			{isLoadingOccasions ? (
				<span class="">{__("Loading...", "simple-history")}</span>
			) : null}

			{isShowingOccasions ? (
				<>
					<span class="">
						{sprintf(
							__("Showing %1$s more", "simple-history"),
							subsequent_occasions_count - 1,
						)}
					</span>

					<EventOccasionsList
						isLoadingOccasions={isLoadingOccasions}
						isShowingOccasions={isShowingOccasions}
						occasions={occasions}
						subsequent_occasions_count={subsequent_occasions_count}
						occasionsCountMaxReturn={occasionsCountMaxReturn}
					/>
				</>
			) : null}
		</div>
	);
}
