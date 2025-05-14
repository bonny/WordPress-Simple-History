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
		// Not sticky event for first item.
		// Current event have date label.
		label = dateI18n( dateSettings().formats.date, event.date_local );

		// Output "Today" and "Yesterday" if the date is today or yesterday.
		const eventYmd = new Date( event.date_local )
			.toISOString()
			.split( 'T' )[ 0 ];
		const todayYmd = new Date().toISOString().split( 'T' )[ 0 ];
		const yesterdayYmd = new Date(
			new Date().setDate( new Date().getDate() - 1 )
		)
			.toISOString()
			.split( 'T' )[ 0 ];

		if ( eventYmd === todayYmd ) {
			label = 'Today';
		} else if ( eventYmd === yesterdayYmd ) {
			label = 'Yesterday';
		}
	}

	return label;
}

export function EventSeparator( {
	event,
	eventVariant,
	prevEvent,
	loopIndex,
} ) {
	if ( eventVariant === 'modal' ) {
		return null;
	}

	const label = getEventDividerLabel( { event, loopIndex } );

	const prevEventLabel = getEventDividerLabel( {
		event: prevEvent,
		loopIndex: loopIndex - 1,
	} );

	const outputLabel =
		label !== prevEventLabel && prevEventLabel !== 'Sticky events';

	const separatorClassNames = clsx( {
		SimpleHistoryEventSeparator: true,
		'SimpleHistoryEventSeparator--hasLabel': outputLabel,
	} );

	const labelClasses = 'SimpleHistoryEventSeparator__label';

	return (
		<div className={ separatorClassNames }>
			{ outputLabel ? (
				<span className={ labelClasses }>{ label }</span>
			) : null }
		</div>
	);
}
