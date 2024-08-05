import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Modal,
	__experimentalText as Text,
	Tooltip,
} from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { intlFormatDistance } from 'date-fns';
import { Event } from './Event';

function EventInfoModal( props ) {
	const { event, closeModal } = props;
	const [ loadedEvent, setLoadedEvent ] = useState( {} );
	const [ isLoadingContext, setIsLoadingContext ] = useState( false );

	/**
	 * Load event from the REST API.
	 */
	useEffect( () => {
		const loadEventContext = async () => {
			setIsLoadingContext( true );

			const eventsQueryParams = {
				_fields: [
					'id',
					'logger',
					'occasions_id',
					'subsequent_occasions_count',
					'initiator_data',
					'loglevel',
					'message_key',
					'message_uninterpolated',
					'date',
					'date_gmt',
					'message',
					'context',
					'ip_addresses',
					'details_data',
					'via',
					'initiator',
				],
			};

			const eventResponse = await apiFetch( {
				path: addQueryArgs(
					'/simple-history/v1/events/' + event.id,
					eventsQueryParams
				),
				// Skip parsing to be able to retrieve headers.
				parse: false,
			} );

			const eventJson = await eventResponse.json();

			setLoadedEvent( eventJson );
			setIsLoadingContext( false );
		};

		loadEventContext();
	}, [ event ] );

	return (
		<Modal
			title={ __( 'Event details', 'simple-history' ) }
			onRequestClose={ closeModal }
		>
			<div className="SimpleHistory-modal">
				<Event event={ event } variant="modal" />

				<p>
					<Text>
						{ __(
							'This is potentially useful information and meta data that a logger has saved.',
							'simple-history'
						) }
					</Text>
				</p>

				<h2>{ __( 'Event details', 'simple-history' ) }</h2>

				<table className="SimpleHistoryLogitem__moreDetailsContext">
					<thead>
						<tr>
							<th>{ __( 'Key', 'simple-history' ) }</th>
							<th>{ __( 'Value', 'simple-history' ) }</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>id</td>
							<td>{ loadedEvent.id }</td>
						</tr>
						<tr>
							<td>logger</td>
							<td>{ loadedEvent.logger }</td>
						</tr>
						<tr>
							<td>level</td>
							<td>{ loadedEvent.loglevel }</td>
						</tr>
						<tr>
							<td>date</td>
							<td>{ loadedEvent.date }</td>
						</tr>
						<tr>
							<td>message</td>
							<td>{ loadedEvent.message }</td>
						</tr>
						<tr>
							<td>message_uninterpolated</td>
							<td>{ loadedEvent.message_uninterpolated }</td>
						</tr>
						<tr>
							<td>initiator</td>
							<td>{ loadedEvent.initiator }</td>
						</tr>
						<tr>
							<td>subsequent_occasions_count</td>
							<td>{ loadedEvent.subsequent_occasions_count }</td>
						</tr>
						<tr>
							<td>via</td>
							<td>{ loadedEvent.via }</td>
						</tr>
					</tbody>
				</table>

				<h2>{ __( 'Event context', 'simple-history' ) }</h2>

				<table className="SimpleHistoryLogitem__moreDetailsContext">
					<thead>
						<tr>
							<th>{ __( 'Key', 'simple-history' ) }</th>
							<th>{ __( 'Value', 'simple-history' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ /* All key, values from context */ }
						{ Object.entries( loadedEvent.context || {} ).map(
							( [ key, value ] ) => {
								return (
									<tr key={ key }>
										<td>{ key }</td>
										<td>{ JSON.stringify( value ) }</td>
									</tr>
								);
							}
						) }
					</tbody>
				</table>
			</div>
		</Modal>
	);
}

export function EventDate( props ) {
	const { event, eventVariant } = props;

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

	const time = (
		<time
			dateTime={ event.date_gmt }
			className="SimpleHistoryLogitem__when__liveRelative"
		>
			{ formattedDateFormatAbbreviated } ({ formattedDateLiveUpdated })
		</time>
	);

	return (
		<>
			<span className="SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided">
				<Tooltip text={ tooltipText } delay={ 500 }>
					{ eventVariant === 'modal' ? (
						<Text>{ time }</Text>
					) : (
						<Button variant="link" onClick={ handleClick }>
							{ time }
						</Button>
					) }
				</Tooltip>
			</span>

			{ isModalOpen ? (
				<EventInfoModal event={ event } closeModal={ closeModal } />
			) : null }
		</>
	);
}
