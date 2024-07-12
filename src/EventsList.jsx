import { Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export function EventsList(props) {
	const { events, eventsIsLoading, eventsMeta } = props;

	if (eventsIsLoading) {
		return (
			<p>
				<Spinner />
				{__("Loading history...", "simple-history")}
			</p>
		);
	}

	return (
		<div>
			<p>
				Total events: {eventsMeta.total}, Total pages: {eventsMeta.totalPages}
			</p>
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
