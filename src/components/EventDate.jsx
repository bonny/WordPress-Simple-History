import {
	Button,
	__experimentalText as Text,
	Tooltip,
} from '@wordpress/components';
import {
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
	const dateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;

	const formattedDateFormatAbbreviated = dateI18n(
		dateFormatAbbreviated,
		event.date_gmt
	);

	const [ formattedDateLiveUpdated, setFormattedDateLiveUpdated ] = useState(
		() => {
			return humanTimeDiff( event.date_gmt );
		}
	);

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

	return (
		<>
			<EventHeaderItem className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when">
				<Tooltip text={ tooltipText } delay={ 500 }>
					{ eventVariant === 'modal' ? (
						<Text>{ time }</Text>
					) : (
						<Button variant="link" onClick={ handleDateClick }>
							{ time }
						</Button>
					) }
				</Tooltip>
			</EventHeaderItem>
		</>
	);
}
