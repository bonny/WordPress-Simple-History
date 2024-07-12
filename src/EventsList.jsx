import { Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export function EventsList(props) {
	const { events, eventsIsLoading } = props;

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
