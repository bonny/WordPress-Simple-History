import { clsx } from 'clsx';
import {
	dateI18n,
	__experimentalGetSettings as dateSettings,
} from '@wordpress/date';

function getEventDividerLabel( { event, loopIndex } ) {
	let label = '';

	// Bail if not event.
	if ( ! event ) {
		return '';
	}

	if ( loopIndex === 0 && event.sticky ) {
		label = 'Sticky events';
	} else {
		// Current event have date label.
		label = dateI18n( dateSettings().formats.date, event.date_local );
	}

	return label;
}

export function EventSeparator( { event, prevEvent, loopIndex } ) {
	const label = getEventDividerLabel( { event, loopIndex } );

	const prevEventLabel = getEventDividerLabel( {
		event: prevEvent,
		loopIndex: loopIndex - 1,
	} );

	const outputLabel = label !== prevEventLabel && prevEventLabel !== 'Sticky events';

	const separatorClassNames = clsx( {
		SimpleHistoryEventSeparator: true,
		'SimpleHistoryEventSeparator--hasLabel': outputLabel,
	} );

	const containerStyles = {
		width: '100%',
		display: 'flex',
		justifyContent: 'center',
		alignItems: 'center',
		borderTop: '1px solid var(--sh-color-separator)',
		marginTop: 'calc( var(--sh-spacing-medium) * -1)',
		minHeight: '30px',
		fontWeight: '600',
	};

	const labelStyles = {
		padding: 'var(--sh-spacing-small) var(--sh-spacing-medium)',
		borderRadius: '1rem',
		lineHeight: 1,
		backgroundColor: 'var(--sh-color-white)',
		border: '1px solid var(--sh-color-separator)',
		fontSize: 'var(--sh-font-size-small)',
		transform: 'translateY(-50%)',
	};

	return (
		<div className={ separatorClassNames } style={ containerStyles }>
			{ /* Dont show albel if current label has same value as the previous event had. */ }
			{ outputLabel ? (
				<span style={ labelStyles }>{ label }</span>
			) : null }
		</div>
	);
}
