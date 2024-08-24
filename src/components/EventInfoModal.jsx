import apiFetch from '@wordpress/api-fetch';
import { Modal, __experimentalText as Text } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Event } from './Event';

export function EventInfoModal( props ) {
	const { eventId, closeModal = null } = props;
	const [ loadedEvent, setLoadedEvent ] = useState( null );
	const [ isLoadingContext, setIsLoadingContext ] = useState( true );

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
					'message',
					'message_html',
					'message_key',
					'details_data',
					'details_html',
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
					'/simple-history/v1/events/' + eventId,
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
	}, [ eventId ] );

	return (
		<Modal
			title={ __( 'Event details', 'simple-history' ) }
			onRequestClose={ closeModal }
		>
			<div className="SimpleHistory__modal">
				{ isLoadingContext ? (
					__( 'Loading detailed events data…', 'simple-history' )
				) : (
					<>
						<Event event={ loadedEvent } variant="modal" />
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
									<td>
										{ loadedEvent.message_uninterpolated }
									</td>
								</tr>
								<tr>
									<td>initiator</td>
									<td>{ loadedEvent.initiator }</td>
								</tr>
								<tr>
									<td>subsequent_occasions_count</td>
									<td>
										{
											loadedEvent.subsequent_occasions_count
										}
									</td>
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
								{ Object.entries(
									loadedEvent.context || {}
								).map( ( [ key, value ] ) => {
									return (
										<tr key={ key }>
											<td>{ key }</td>
											<td>{ JSON.stringify( value ) }</td>
										</tr>
									);
								} ) }
							</tbody>
						</table>
					</>
				) }
			</div>
		</Modal>
	);
}
