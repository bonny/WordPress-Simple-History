import { clsx } from 'clsx';
import {
	dateI18n,
	__experimentalGetSettings as dateSettings,
} from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { pinSmall } from '@wordpress/icons';

/**
 * Get the date key for an event (used for comparison logic).
 *
 * @param {Object} event - The event object.
 * @return {string} A comparable string key for the event date.
 */
function getEventDateKey( event ) {
	// Bail if not event.
	if ( ! event ) {
		return '';
	}

	if ( event.sticky_appended ) {
		return 'sticky';
	}

	// Get the date key for comparison
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
		return 'today';
	} else if ( eventYmd === yesterdayYmd ) {
		return 'yesterday';
	}

	// Return the formatted date for other dates
	return dateI18n( dateSettings().formats.date, event.date_local );
}

/**
 * Get the label for the event divider.
 *
 * @param {Object} root0       - The parameter object.
 * @param {Object} root0.event - The event object.
 * @return {string|Object} The label for the event divider.
 */
function getEventDividerLabel( { event } ) {
	// Bail if not event.
	if ( ! event ) {
		return '';
	}

	// Get the date key and convert to display format
	const dateKey = getEventDateKey( event );

	if ( dateKey === 'sticky' ) {
		return (
			<>
				<Icon icon={ pinSmall } />
				{ __( 'Sticky', 'simple-history' ) }
			</>
		);
	} else if ( dateKey === 'today' ) {
		return __( 'Today', 'simple-history' );
	} else if ( dateKey === 'yesterday' ) {
		return __( 'Yesterday', 'simple-history' );
	}

	// For other dates, return the formatted date
	return dateKey;
}

/**
 * Get a comparable string key for the event divider label.
 * Used for comparison logic to determine if label should be shown.
 *
 * @param {Object} root0       - The parameter object.
 * @param {Object} root0.event - The event object.
 * @return {string} A comparable string key for the label.
 */
function getEventDividerLabelKey( { event } ) {
	return getEventDateKey( event );
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

	const labelKey = getEventDividerLabelKey( { event, loopIndex } );
	const prevEventLabelKey = getEventDividerLabelKey( {
		event: prevEvent,
		loopIndex: loopIndex - 1,
	} );

	const outputLabel = labelKey !== prevEventLabelKey;

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
