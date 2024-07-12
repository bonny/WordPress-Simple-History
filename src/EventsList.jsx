import { Spinner } from "@wordpress/components";

export function EventsList(props) {
	const { events, eventsIsLoading } = props;

	if (eventsIsLoading) {
		return (
			<p>
				<Spinner />
				Loading...
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
