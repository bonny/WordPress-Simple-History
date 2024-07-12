import { Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { clsx } from "clsx";

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

function EventInitiatorImage(props) {
	const { event } = props;
	const { initiator } = event;

	if (initiator === "wp_user") {
		return <EventInitiatorImageWPUser event={event} />;
	}

	return <p>Add image for initiator "{initiator}"</p>;
}

function EventInitiator(props) {
	const { event } = props;
	const { initiator_data } = event;

	console.log("initiator_data", initiator_data);
	return (
		<p>
			{event.initiator} - {initiator_data.user_id} - {initiator_data.user_login}{" "}
			- {initiator_data.user_email}
		</p>
	);
}

function Event(props) {
	const { event } = props;

	/*
		erik editor par+erik@earthpeople.se 3:09 pm (för 9 minuter sedan)
		Made POST request to http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin-ajax.php felsokning
		Method	POST
	*/

	// TODO: add classes using clsx
	// SimpleHistoryLogitem SimpleHistoryLogitem--loglevel-debug SimpleHistoryLogitem--logger-WPHTTPRequestsLogger SimpleHistoryLogitem--initiator-wp_user

	return (
		<li key={event.id}>
			<EventInitiatorImage event={event} />
			<EventInitiator event={event} />
			{event.date_gmt} - {event.via} - {event.level}
			<div dangerouslySetInnerHTML={{ __html: event.message_html }} />
			{event.subsequent_occasions_count} occasions
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
		<div style={{ backgroundColor: "white", padding: "1rem" }}>
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
