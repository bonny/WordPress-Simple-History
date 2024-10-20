export function EventInitiatorImageWPUser( props ) {
	const { event } = props;
	const { initiator_data: initiatorData } = event;

	return (
		<img
			className="SimpleHistoryLogitem__senderImage"
			src={ initiatorData.user_avatar_url }
			alt=""
		/>
	);
}

export function EventInitiatorImageWebUser( props ) {
	const { event } = props;
	const { initiator_data: initiatorData } = event;

	return (
		<img
			className="SimpleHistoryLogitem__senderImage"
			src={ initiatorData.user_avatar_url }
			alt=""
		/>
	);
}

/**
 * Initiator is "other" or "wp" or "wp_cli".
 * Image is added using CSS.
 */
export function EventInitiatorImageFromCSS() {
	return <div className="SimpleHistoryLogitem__senderImage"></div>;
}

export function EventInitiatorImage( props ) {
	const { event } = props;
	const { initiator } = event;

	switch ( initiator ) {
		case 'wp_user':
			return <EventInitiatorImageWPUser event={ event } />;
		case 'web_user':
			return <EventInitiatorImageWebUser event={ event } />;
		case 'wp_cli':
		case 'wp':
		case 'other':
			return <EventInitiatorImageFromCSS event={ event } />;
		default:
			return <p>Add image for initiator &quot;{ initiator }&quot;</p>;
	}
}
