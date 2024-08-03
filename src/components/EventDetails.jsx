/**
 * Outputs event details.
 */
export function EventDetails( props ) {
	const { event } = props;
	const { details_html } = event;

	return (
		<div
			className="SimpleHistoryLogitem__details"
			dangerouslySetInnerHTML={ { __html: details_html } }
		></div>
	);
}
