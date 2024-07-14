import { Spinner, Tooltip } from "@wordpress/components";
import { dateI18n, getSettings as getDateSettings } from "@wordpress/date";
import { useEffect, useState } from "@wordpress/element";
import { __, _x, _n, _nx, sprintf } from "@wordpress/i18n";
import { clsx } from "clsx";
import { intlFormatDistance } from "date-fns";
import { addQueryArgs } from "@wordpress/url";
import apiFetch from "@wordpress/api-fetch";

function EventInitiatorImageWPUser(props) {
	const { event } = props;
	const { initiator_data } = event;

	return (
		<img
			className="SimpleHistoryLogitem__senderImage"
			src={initiator_data.user_avatar_url}
			alt=""
		/>
	);
}
function EventInitiatorImageWebUser(props) {
	const { event } = props;
	const { initiator_data } = event;

	return (
		<img
			className="SimpleHistoryLogitem__senderImage"
			src={initiator_data.user_avatar_url}
			alt=""
		/>
	);
}

/**
 * Initiator is "other" or "wp" or "wp_cli".
 * Image is added using CSS.
 */
function EventInitiatorImageFromCSS(props) {
	return <div className="SimpleHistoryLogitem__senderImage"></div>;
}

function EventInitiatorImage(props) {
	const { event } = props;
	const { initiator } = event;

	switch (initiator) {
		case "wp_user":
			return <EventInitiatorImageWPUser event={event} />;
		case "web_user":
			return <EventInitiatorImageWebUser event={event} />;
		case "wp_cli":
		case "wp":
		case "other":
			return <EventInitiatorImageFromCSS event={event} />;
		default:
			return <p>Add image for initiator "{initiator}"</p>;
	}
}

/**
 * Outputs "WordPress" or "John Doe - erik@example.com".
 */
function EventInitiatorName(props) {
	const { event } = props;
	const { initiator_data } = event;

	switch (event.initiator) {
		case "wp_user":
			return (
				<>
					<a href={initiator_data.user_profile_url}>
						<span className="SimpleHistoryLogitem__inlineDivided">
							<strong>{initiator_data.user_login}</strong>{" "}
							<span>({initiator_data.user_email})</span>
						</span>
					</a>
				</>
			);
		case "web_user":
			return (
				<>
					<strong className="SimpleHistoryLogitem__inlineDivided">
						{__("Anonymous web user", "simple-history")}
					</strong>
				</>
			);
		case "wp_cli":
			return (
				<>
					<strong className="SimpleHistoryLogitem__inlineDivided">
						{__("WP-CLI", "simple-history")}
					</strong>
				</>
			);
		case "wp":
			return (
				<>
					<strong className="SimpleHistoryLogitem__inlineDivided">
						{__("WordPress", "simple-history")}
					</strong>
				</>
			);
		case "other":
			return (
				<>
					<strong className="SimpleHistoryLogitem__inlineDivided">
						{__("Other", "simple-history")}
					</strong>
				</>
			);
		default:
			return <p>Add output for initiator "{event.initiator}"</p>;
	}
}

function EventDate(props) {
	const { event } = props;

	const dateSettings = getDateSettings();
	const dateFormat = dateSettings.formats.datetime;
	const dateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;

	const formattedDateFormatAbbreviated = dateI18n(
		dateFormatAbbreviated,
		event.date_gmt,
	);

	const [formattedDateLiveUpdated, setFormattedDateLiveUpdated] = useState(
		() => {
			return intlFormatDistance(event.date_gmt, new Date());
		},
	);

	useEffect(() => {
		const intervalId = setInterval(() => {
			setFormattedDateLiveUpdated(
				intlFormatDistance(event.date_gmt, new Date()),
			);
		}, 1000);

		return () => {
			clearInterval(intervalId);
		};
	}, [event.date_gmt]);

	const tooltipText = sprintf(
		__("%1$s local time %3$s (%2$s GMT time)", "simple-history"),
		event.date,
		event.date_gmt,
		"\n",
	);

	return (
		<span className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided">
			<Tooltip text={tooltipText} delay={500}>
				<a href="#">
					<time
						datetime={event.date_gmt}
						className="SimpleHistoryLogitem__when__liveRelative"
					>
						{formattedDateFormatAbbreviated} ({formattedDateLiveUpdated})
					</time>
				</a>
			</Tooltip>
		</span>
	);
}

function EventDetails(props) {
	const { event } = props;
	const { details_html } = event;

	return (
		<div
			className="SimpleHistoryLogitem__details"
			dangerouslySetInnerHTML={{ __html: details_html }}
		></div>
	);
}

function EventVia(props) {
	const { event } = props;
	const { via } = event;

	if (!via) {
		return null;
	}

	return <span className="SimpleHistoryLogitem__inlineDivided">{via}</span>;
}

function EventText(props) {
	const { event } = props;

	const logLevelClassNames = clsx(
		"SimpleHistoryLogitem--logleveltag",
		`SimpleHistoryLogitem--logleveltag-${event.loglevel}`,
	);

	return (
		<div className="SimpleHistoryLogitem__text">
			<span dangerouslySetInnerHTML={{ __html: event.message_html }}></span>{" "}
			<span className={logLevelClassNames}>{event.loglevel}</span>
		</div>
	);
}

function EventHeader(props) {
	const { event } = props;

	return (
		<div className="SimpleHistoryLogitem__header">
			<EventInitiatorName event={event} />
			<EventDate event={event} />
			<EventVia event={event} />
		</div>
	);
}

function EventOccasions(props) {
	const { event } = props;
	const { subsequent_occasions_count } = event;
	const [isLoadingOccasions, setIsLoadingOccasions] = useState(false);
	const [isShowingOccasions, setIsShowingOccasions] = useState(false);
	const [occasions, setOccasions] = useState([]);

	// The current event is the only occasion.
	if (subsequent_occasions_count === 1) {
		return null;
	}

	const loadOccasions = async () => {
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
			occasionsCountMaxReturn: 15,
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

	console.log("isLoadingOccasions", isLoadingOccasions);
	console.log("isShowingOccasions", isShowingOccasions);

	return (
		<div class="">
			{!isShowingOccasions && !isLoadingOccasions ? (
				<a
					href="#"
					class=""
					onClick={() => {
						loadOccasions();
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
					<EventOccasionsList occasions={occasions} />
				</>
			) : null}
		</div>
	);
}

function Event(props) {
	const { event } = props;

	/*
		erik editor par+erik@earthpeople.se 3:09 pm (för 9 minuter sedan)
		Made POST request to http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin-ajax.php felsokning
		Method	POST
	*/

	const containerClassNames = clsx(
		"SimpleHistoryLogitem",
		`SimpleHistoryLogitem--loglevel-${event.level}`,
		`SimpleHistoryLogitem--logger-${event.logger}`,
		`SimpleHistoryLogitem--initiator-${event.initiator}`,
	);

	return (
		<li key={event.id} className={containerClassNames}>
			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={event} />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<EventHeader event={event} />
				<EventText event={event} />
				<EventDetails event={event} />
				<EventOccasions event={event} />
			</div>
		</li>
	);
}

export function EventOccasionsList(props) {
	console.log("EventOccasionsList", props);
	const { occasions } = props;

	return (
		<div>
			<p>EventOccasionsList output</p>
			<ul className="SimpleHistoryLogitems">
				{occasions.map((event) => (
					<Event key={event.id} event={event} />
				))}
			</ul>
		</div>
	);
}

export function EventsList(props) {
	const { events, eventsIsLoading, eventsMeta } = props;

	if (eventsIsLoading) {
		return (
			<div style={{ backgroundColor: "white", padding: "1rem" }}>
				<p>
					<Spinner />

					{_x(
						"Loading history...",
						"Message visible while waiting for log to load from server the first time",
						"simple-history",
					)}
				</p>
			</div>
		);
	}

	return (
		<div style={{ backgroundColor: "white" }}>
			<p>
				Total events: {eventsMeta.total}, Total pages: {eventsMeta.totalPages}
			</p>

			<ul className="SimpleHistoryLogitems">
				{events.map((event) => (
					<Event key={event.id} event={event} />
				))}
			</ul>
		</div>
	);
}
