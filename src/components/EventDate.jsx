import {
	Button,
	__experimentalText as Text,
	Tooltip,
} from '@wordpress/components';
import {
	date,
	dateI18n,
	getSettings as getDateSettings,
} from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';
import { navigateToEventPermalink, useEventRelativeTime } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';
import { EventHeaderItem } from './EventHeaderItem';

export function EventDate( props ) {
	const { event, eventVariant } = props;
	const { eventsAdminPageURL } = useEventsSettings();
	const dateSettings = getDateSettings();
	const wpDateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;
	const wpDateFormatTime = dateSettings.formats.time;
	const wpTimezoneString = dateSettings.timezone.string;
	const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	const eventDateTimeInGMTTimeZone = event.date_gmt + '+0000';
	// Both sides have to be read in the zone the time below is printed in, or
	// "Today" starts and stops at a different moment than the date divider
	// above the event does.
	const eventDateYMD = date(
		'Y-m-d',
		eventDateTimeInGMTTimeZone,
		browserTimeZone
	);
	const eventIsToday =
		eventDateYMD === date( 'Y-m-d', new Date(), browserTimeZone );

	let formattedDateFormatAbbreviated;

	// Show date as "Sep 2, 2024 8:36 pm".
	// If the event is today, show "Today H:i" instead.
	if ( eventIsToday ) {
		formattedDateFormatAbbreviated = sprintf(
			// translators: %s is the time, like 8:36 pm.
			__( 'Today %s', 'simple-history' ),
			dateI18n(
				wpDateFormatTime,
				eventDateTimeInGMTTimeZone,
				browserTimeZone
			)
		);
	} else {
		formattedDateFormatAbbreviated = dateI18n(
			wpDateFormatAbbreviated,
			eventDateTimeInGMTTimeZone,
			browserTimeZone
		);
	}

	const formattedDateLiveUpdated = useEventRelativeTime( event );

	const tooltipText = (
		<>
			<table>
				<thead>
					<tr>
						<th>{ __( 'Date', 'simple-history' ) }</th>
						<th>{ __( 'Description', 'simple-history' ) }</th>
					</tr>
				</thead>

				<tbody>
					<tr>
						<td>{ event.date_gmt }</td>
						<td>{ __( `GMT time`, 'simple-history' ) }</td>
					</tr>

					<tr>
						<td>{ event.date_local }</td>
						<td>
							{ sprintf(
								/* translators: 1: timezone string */
								__(
									`Website timezone (%1$s)`,
									'simple-history'
								),
								wpTimezoneString
							) }
						</td>
					</tr>

					{ wpTimezoneString !== browserTimeZone && (
						<tr>
							<td>
								{ dateI18n(
									'Y-m-d H:i:s',
									eventDateTimeInGMTTimeZone,
									browserTimeZone
								) }
							</td>
							<td>
								{ sprintf(
									/* translators: 1: browser timezone */
									__(
										`Browser local time (%1$s)`,
										'simple-history'
									),
									browserTimeZone
								) }
							</td>
						</tr>
					) }
				</tbody>
			</table>
		</>
	);

	const handleDateClick = () => {
		navigateToEventPermalink( { event } );
	};

	const time = (
		<time
			dateTime={ event.date_gmt }
			className="SimpleHistoryLogitem__when__liveRelative"
		>
			{ formattedDateFormatAbbreviated } ({ formattedDateLiveUpdated })
		</time>
	);

	let output;
	if ( eventVariant === 'compact' ) {
		output = <span>{ formattedDateLiveUpdated }</span>;
	} else if ( eventVariant === 'dashboard' ) {
		const eventPermalink = eventsAdminPageURL
			? `${ eventsAdminPageURL }#simple-history/event/${ event.id }`
			: undefined;
		output = eventPermalink ? (
			<a href={ eventPermalink } title={ formattedDateFormatAbbreviated }>
				{ formattedDateLiveUpdated }
			</a>
		) : (
			<span title={ formattedDateFormatAbbreviated }>
				{ formattedDateLiveUpdated }
			</span>
		);
	} else {
		output = (
			<Tooltip text={ tooltipText } delay={ 500 }>
				{ eventVariant === 'modal' ? (
					<Text>{ time }</Text>
				) : (
					<Button variant="link" onClick={ handleDateClick }>
						{ time }
					</Button>
				) }
			</Tooltip>
		);
	}

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when">
			{ output }
		</EventHeaderItem>
	);
}
