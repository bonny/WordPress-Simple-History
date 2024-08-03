import { clsx } from 'clsx';

/**
 * Outputs the main event text, i.e.:
 * - "Logged in"
 * - "Created post"
 */
export function EventText( props ) {
	const { event } = props;

	const logLevelClassNames = clsx(
		'SimpleHistoryLogitem--logleveltag',
		`SimpleHistoryLogitem--logleveltag-${ event.loglevel }`
	);

	return (
		<div className="SimpleHistoryLogitem__text">
			<span
				dangerouslySetInnerHTML={ { __html: event.message_html } }
			></span>{ ' ' }
			<span className={ logLevelClassNames }>{ event.loglevel }</span>
		</div>
	);
}
