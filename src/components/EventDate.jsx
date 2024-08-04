import { Tooltip, Button, Modal } from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { intlFormatDistance } from 'date-fns';

function EventInfoModal( props ) {
	const { event, closeModal } = props;
	return (
		<Modal title="This is my modal" onRequestClose={ closeModal }>
			<Button variant="secondary" onClick={ closeModal }>
				My custom close button
			</Button>
		</Modal>
	);
}

export function EventDate( props ) {
	const { event } = props;

	const dateSettings = getDateSettings();
	// const dateFormat = dateSettings.formats.datetime;
	const dateFormatAbbreviated = dateSettings.formats.datetimeAbbreviated;

	const formattedDateFormatAbbreviated = dateI18n(
		dateFormatAbbreviated,
		event.date_gmt
	);

	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const openModal = () => setIsModalOpen( true );
	const closeModal = () => setIsModalOpen( false );

	const [ formattedDateLiveUpdated, setFormattedDateLiveUpdated ] = useState(
		() => {
			return intlFormatDistance( event.date_gmt, new Date() );
		}
	);

	useEffect( () => {
		const intervalId = setInterval( () => {
			setFormattedDateLiveUpdated(
				intlFormatDistance( event.date_gmt, new Date() )
			);
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

	const handleClick = () => {
		window.location.href = `#/event/${ event.id }`;
		openModal();
	};

	return (
		<>
			<span className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided">
				<Tooltip text={ tooltipText } delay={ 500 }>
					<Button variant="link" onClick={ handleClick }>
						<time
							dateTime={ event.date_gmt }
							className="SimpleHistoryLogitem__when__liveRelative"
						>
							{ formattedDateFormatAbbreviated } (
							{ formattedDateLiveUpdated })
						</time>
					</Button>
				</Tooltip>
			</span>

			{ isModalOpen ? (
				<EventInfoModal event={ event } closeModal={ closeModal } />
			) : null }
		</>
	);
}
