import { __ } from "@wordpress/i18n";

/**
 * Outputs "WordPress" or "John Doe - erik@example.com".
 */
export function EventInitiatorName(props) {
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
