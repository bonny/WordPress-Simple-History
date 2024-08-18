/**
 * Outputs event details.
 *
 * @param {Object} props
 */
export function EventDetails( props ) {
	const { event } = props;
	const { details_html: detailsHtml } = event;

	return (
		<div
			className="SimpleHistoryLogitem__details"
			dangerouslySetInnerHTML={ { __html: detailsHtml } }
		></div>
	);
}
