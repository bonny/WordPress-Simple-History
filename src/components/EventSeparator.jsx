import { clsx } from 'clsx';
import { date, dateI18n, getSettings as dateSettings } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';
import { pinSmall } from '@wordpress/icons';

// Event times are shown in the visitor's own time zone, so group the days by
// that zone too. A divider must never disagree with the time printed under it.
const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

// Building today's and yesterday's keys costs a moment parse each, and this
// module runs twice per row on every render of the list. They only change at
// midnight, so reuse them and rebuild on a short interval instead.
const DAY_KEYS_MAX_AGE_MS = 10 * 1000;

let cachedDayKeys = null;
let cachedDayKeysAtMs = 0;

/**
 * Get today's and yesterday's date keys, in the visitor's time zone.
 *
 * @return {{todayYmd: string, yesterdayYmd: string}} The two date keys.
 */
function getDayKeys() {
	const nowMs = Date.now();

	if ( cachedDayKeys && nowMs - cachedDayKeysAtMs < DAY_KEYS_MAX_AGE_MS ) {
		return cachedDayKeys;
	}

	const todayYmd = date( 'Y-m-d', new Date(), browserTimeZone );

	// Step back one calendar day on the date string itself. Subtracting 24
	// hours from a timestamp would land on the same day across a DST change.
	const yesterday = new Date( `${ todayYmd }T12:00:00Z` );
	yesterday.setUTCDate( yesterday.getUTCDate() - 1 );

	cachedDayKeys = {
		todayYmd,
		yesterdayYmd: yesterday.toISOString().split( 'T' )[ 0 ],
	};
	cachedDayKeysAtMs = nowMs;

	return cachedDayKeys;
}

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

	// date_gmt carries no offset, so mark it as UTC. A bare date_local string
	// would be read as browser-local and then merely converted, which moves
	// the day whenever the visitor is not in the website's time zone.
	const eventDateTimeInGMTTimeZone = event.date_gmt + 'Z';

	const eventYmd = date(
		'Y-m-d',
		eventDateTimeInGMTTimeZone,
		browserTimeZone
	);
	const { todayYmd, yesterdayYmd } = getDayKeys();

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
 * Get the label to display for an event divider date key.
 *
 * @param {string} dateKey - A key from getEventDateKey().
 * @return {string|Object} The label for the event divider.
 */
function getEventDividerLabel( dateKey ) {
	// Bail if there is no key, ie. there is no event.
	if ( ! dateKey ) {
		return '';
	}

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

export function EventSeparator( { event, eventVariant, prevEvent } ) {
	if ( eventVariant === 'modal' ) {
		return null;
	}

	// The key doubles as the label source, so derive it once per event rather
	// than recomputing it for the label and again for the comparison.
	const labelKey = getEventDateKey( event );
	const prevEventLabelKey = getEventDateKey( prevEvent );

	const label = getEventDividerLabel( labelKey );

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
