import { clsx } from 'clsx';
import {
	dateI18n,
	__experimentalGetSettings as dateSettings,
} from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { pinSmall } from '@wordpress/icons';

/**
 * Get the label for the event divider.
 *
 * @param {Object} root0       - The parameter object.
 * @param {Object} root0.event - The event object.
 * @return {string} The label for the event divider.
 */
function getEventDividerLabel( { event } ) {
	let label = '';

	// Bail if not event.
	if ( ! event ) {
		return '';
	}

	if ( event.sticky_appended ) {
		label = (
			<>
				<Icon icon={ pinSmall } />
				{ __( 'Sticky', 'simple-history' ) }
			</>
		);
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
			label = __( 'Today', 'simple-history' );
		} else if ( eventYmd === yesterdayYmd ) {
			label = __( 'Yesterday', 'simple-history' );
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

	const outputLabel = label !== prevEventLabel;

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
