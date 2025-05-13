import { clsx } from 'clsx';
import {
	dateI18n,
	__experimentalGetSettings as dateSettings,
} from '@wordpress/date';
import { useState, useEffect } from '@wordpress/element';

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

export function EventSeparator( { event, prevEvent, nextEvent, loopIndex } ) {
	const label = getEventDividerLabel( { event, loopIndex } );

	// Bail if label is empty.
	if ( label === '' ) {
		return null;
	}

	const prevEventLabel = getEventDividerLabel( {
		event: prevEvent,
		loopIndex: loopIndex - 1,
	} );

	// Bail if label is the same as the previous event had.
	if ( label === prevEventLabel ) {
		return null;
	}

	const separatorClassNames = clsx( {
		SimpleHistoryEventSeparator: true,
		//'is-separator': isSeparator,
	} );

	const style = {
		// backgroundColor: 'red',
		// position: 'absolute',
		// top: 0,
		// left: 0,
		// transform: 'translateY(-60%)',
		width: '100%',
		display: 'flex',
		justifyContent: 'center',
		alignItems: 'center',
		borderTop: '1px solid var(--sh-color-separator)',
	};

	const labelStyles = {
		padding: '0.25rem 2rem',
		borderRadius: '1rem',
		lineHeight: 1,
		backgroundColor: 'var(--sh-color-white)',
		border: '1px solid var(--sh-color-separator)',
		fontSize: 'var(--sh-font-size-small)',
		transform: 'translateY(-50%)',
	};

	return (
		<div className={ separatorClassNames } style={ style }>
			<span style={ labelStyles }>{ label }</span>
		</div>
	);
}
