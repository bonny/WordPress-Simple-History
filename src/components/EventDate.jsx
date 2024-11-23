import {
	Button,
	__experimentalText as Text,
	Tooltip,
} from '@wordpress/components';
import {
	date,
	dateI18n,
	getSettings as getDateSettings,
	humanTimeDiff,
} from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { navigateToEventPermalink } from '../functions';
import { EventHeaderItem } from './EventHeaderItem';

export function EventDate( props ) {
	const { event, eventVariant } = props;
	const dateSettings = getDateSettings();
	const wpDateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;
	const wpDateFormatTime = dateSettings.formats.time;
	const wpTimezoneString = dateSettings.timezone.string;
	const browserTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
	const eventDateTimeInGMTTimeZone = event.date_gmt + '+0000';
	const eventDateYMD = date( 'Y-m-d', eventDateTimeInGMTTimeZone );
	const eventIsToday = eventDateYMD === date( 'Y-m-d', undefined, 'GMT' );

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

	const [ formattedDateLiveUpdated, setFormattedDateLiveUpdated ] = useState(
		() => {
			return humanTimeDiff( event.date_local );
		}
	);

	// Update live time every second.
	useEffect( () => {
		const intervalId = setInterval( () => {
			setFormattedDateLiveUpdated( humanTimeDiff( event.date_local ) );
		}, 1000 );

		return () => {
			clearInterval( intervalId );
		};
	}, [ event.date_local ] );

	const tooltipText = (
		<>
			<table>
				<thead>
					<tr>
						<th>Date</th>
						<th>Description</th>
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
		output = <div>{ formattedDateLiveUpdated }</div>;
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
