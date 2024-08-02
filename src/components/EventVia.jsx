export function EventVia(props) {
	const { event } = props;
	const { via } = event;

	if (!via) {
		return null;
	}

	return <span className="SimpleHistoryLogitem__inlineDivided">{via}</span>;
}
