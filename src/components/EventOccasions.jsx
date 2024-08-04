import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { EventOccasionsList } from './EventOccasionsList';
import { Button } from '@wordpress/components';

export function EventOccasions( props ) {
	const { event, eventVariant } = props;
	const { subsequent_occasions_count: subsequentOccasionsCount } = event;
	const [ isLoadingOccasions, setIsLoadingOccasions ] = useState( false );
	const [ isShowingOccasions, setIsShowingOccasions ] = useState( false );
	const [ occasions, setOccasions ] = useState( [] );
	const occasionsCountMaxReturn = 15;

	// Bail if the current event is the only occasion.
	if ( subsequentOccasionsCount === 1 ) {
		return null;
	}

	// Bail if variant is modal.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	const loadOccasions = async () => {
		setIsLoadingOccasions( true );

		const eventsQueryParams = {
			type: 'occasions',
			logRowID: event.id,
			occasionsID: event.occasions_id,
			occasionsCount: subsequentOccasionsCount - 1,
			occasionsCountMaxReturn,
			per_page: 5,
			_fields: [
				'id',
				'date',
				'date_gmt',
				'message',
				'message_html',
				'details_data',
				'details_html',
				'loglevel',
				'occasions_id',
				'subsequent_occasions_count',
				'initiator',
				'initiator_data',
				'via',
			],
		};

		const eventsResponse = await apiFetch( {
			path: addQueryArgs(
				'/simple-history/v1/events',
				eventsQueryParams
			),
			// Skip parsing to be able to retrieve headers.
			parse: false,
		} );

		const responseJson = await eventsResponse.json();

		setOccasions( responseJson );
		setIsLoadingOccasions( false );
		setIsShowingOccasions( true );
	};

	return (
		<div>
			{ ! isShowingOccasions && ! isLoadingOccasions ? (
				<Button
					variant="link"
					onClick={ ( evt ) => {
						loadOccasions();
						evt.preventDefault();
					} }
				>
					{ sprintf(
						/* translators: %s: number of similar events */
						_n(
							'+%1$s similar event',
							'+%1$s similar events',
							subsequentOccasionsCount,
							'simple-history'
						),
						subsequentOccasionsCount
					) }
				</Button>
			) : null }

			{ isLoadingOccasions ? (
				<span>{ __( 'Loading…', 'simple-history' ) }</span>
			) : null }

			{ isShowingOccasions ? (
				<>
					<span>
						{ sprintf(
							/* translators: %s: number of similar events */
							__( 'Showing %1$s more', 'simple-history' ),
							subsequentOccasionsCount - 1
						) }
					</span>

					<EventOccasionsList
						isLoadingOccasions={ isLoadingOccasions }
						isShowingOccasions={ isShowingOccasions }
						occasions={ occasions }
						subsequent_occasions_count={ subsequentOccasionsCount }
						occasionsCountMaxReturn={ occasionsCountMaxReturn }
					/>
				</>
			) : null }
		</div>
	);
}
