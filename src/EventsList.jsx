import { Spinner, Tooltip } from "@wordpress/components";
import { dateI18n, getSettings as getDateSettings } from "@wordpress/date";
import { useEffect, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { clsx } from "clsx";
import { intlFormatDistance } from "date-fns";

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

	const logLevelClassNames = clsx(
		"SimpleHistoryLogitem--logleveltag",
		`SimpleHistoryLogitem--logleveltag-${event.loglevel}`,
	);

	return (
		<li key={event.id} className={containerClassNames}>
			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={event} />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<div className="SimpleHistoryLogitem__header">
					<EventInitiatorName event={event} />

					<EventDate event={event} />

					{event.via ? (
						<span className="SimpleHistoryLogitem__inlineDivided">
							{event.via}
						</span>
					) : null}
				</div>
				<div className="SimpleHistoryLogitem__text">
					<div dangerouslySetInnerHTML={{ __html: event.message_html }} />
					<span className={logLevelClassNames}>{event.loglevel}</span>
				</div>
				<div className="SimpleHistoryLogitem__details">
					...get details from api response
				</div>

				<div className="SimpleHistoryLogitem__occasions">
					{event.subsequent_occasions_count} occasions
				</div>
			</div>
		</li>
	);
}

export function EventsList(props) {
	const { events, eventsIsLoading, eventsMeta } = props;

	if (eventsIsLoading) {
		return (
			<div style={{ backgroundColor: "white", padding: "1rem" }}>
				<p>
					<Spinner />
					{__("Loading history...", "simple-history")}
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
