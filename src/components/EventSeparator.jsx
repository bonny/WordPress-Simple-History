import { clsx } from 'clsx';
import { date, dateI18n, getSettings as dateSettings } from '@wordpress/date';
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

	// Event times are shown in the visitor's own time zone, so group the days
	// by that zone too. A divider must never disagree with the time printed
	// under it.
	const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

	// date_gmt carries no offset, so mark it as UTC. A bare date_local string
	// would be read as browser-local and then merely converted, which moves
	// the day whenever the visitor is not in the website's time zone.
	const eventDateTimeInGMTTimeZone = event.date_gmt + 'Z';

	const eventYmd = date(
		'Y-m-d',
		eventDateTimeInGMTTimeZone,
		browserTimeZone
	);
	const todayYmd = date( 'Y-m-d', new Date(), browserTimeZone );

	// Step back one calendar day on the date string itself. Subtracting 24
	// hours from a timestamp would land on the same day across a DST change.
	const yesterday = new Date( `${ todayYmd }T12:00:00Z` );
	yesterday.setUTCDate( yesterday.getUTCDate() - 1 );
	const yesterdayYmd = yesterday.toISOString().split( 'T' )[ 0 ];

	if ( eventYmd === todayYmd ) {
		return 'today';
	} else if ( eventYmd === yesterdayYmd ) {
		return 'yesterday';
	}

	// Return the formatted date for other dates
	return dateI18n(
		dateSettings().formats.date,
		eventDateTimeInGMTTimeZone,
		browserTimeZone
	);
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
	const LabelTag = eventVariant === 'dashboard' ? 'h3' : 'span';

	return (
		<div className={ separatorClassNames }>
			{ outputLabel ? (
				<LabelTag className={ labelClasses }>{ label }</LabelTag>
			) : null }
		</div>
	);
}
