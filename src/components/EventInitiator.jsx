import { UserCard } from './UserCard';

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

	let image;

	switch ( initiator ) {
		case 'wp_user':
			image = <EventInitiatorImageWPUser event={ event } />;
			break;
		case 'web_user':
			image = <EventInitiatorImageWebUser event={ event } />;
			break;
		case 'wp_cli':
		case 'wp':
		case 'other':
			image = <EventInitiatorImageFromCSS event={ event } />;
			break;
		default:
			return <p>Add image for initiator &quot;{ initiator }&quot;</p>;
	}

	return <UserCard event={ event }>{ image }</UserCard>;
}
