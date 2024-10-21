import {
	Button,
	__experimentalText as Text,
	Tooltip,
} from '@wordpress/components';
import {
	dateI18n,
	getSettings as getDateSettings,
	humanTimeDiff,
	date,
} from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { navigateToEventPermalink } from '../functions';
import { EventHeaderItem } from './EventHeaderItem';

export function EventDate( props ) {
	const { event, eventVariant } = props;
	const dateSettings = getDateSettings();
	const dateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;
	const dateFormatTime = dateSettings.formats.time;

	// Show date as "Sep 2, 2024 8:36 pm".
	// If the event is today, show "Today instead".
	// Today is determined by the date in GMT.
	const eventDateYMD = date( 'Y-m-d', event.date_gmt );
	const nowDateYMD = date( 'Y-m-d' );
	const eventIsToday = eventDateYMD === nowDateYMD;

	let formattedDateFormatAbbreviated;
	if ( eventIsToday ) {
		formattedDateFormatAbbreviated = sprintf(
			// translators: %s is the time, like 8:36 pm.
			__( 'Today %s', 'simple-history' ),
			dateI18n( dateFormatTime, event.date_gmt )
		);
	} else {
		formattedDateFormatAbbreviated = dateI18n(
			dateFormatAbbreviated,
			event.date_gmt
		);
	}

	const [ formattedDateLiveUpdated, setFormattedDateLiveUpdated ] = useState(
		() => {
			return humanTimeDiff( event.date_gmt );
		}
	);

	// Update live time every second.
	useEffect( () => {
		const intervalId = setInterval( () => {
			setFormattedDateLiveUpdated( humanTimeDiff( event.date_gmt ) );
		}, 1000 );

		return () => {
			clearInterval( intervalId );
		};
	}, [ event.date_gmt ] );

	const tooltipText = sprintf(
		/* translators: 1: date in local time, 2: date in GMT time */
		__( '%1$s local time (%2$s GMT time)', 'simple-history' ),
		event.date,
		event.date_gmt
	);

	const handleDateClick = () => {
		navigateToEventPermalink( { event } );
	};

	const time = (
		<>
			<time
				dateTime={ event.date_gmt }
				className="SimpleHistoryLogitem__when__liveRelative"
			>
				{ formattedDateFormatAbbreviated } ({ formattedDateLiveUpdated }
				)
			</time>
		</>
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
