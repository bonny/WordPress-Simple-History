import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { EventOccasionsList } from './EventOccasionsList';

/**
 * Displays some text for failed login attempts.
 * If the Extended Settings add-on is active, the text will be a link to the settings page.
 * If the Extended Settings add-on is not active, the text will be a link to the add-on page.
 *
 * @param {Object} props
 */
function EventOcassionsAddonsContent( props ) {
	const { event, hasExtendedSettingsAddOn } = props;

	// Bail if the event is not from the SimpleUserLogger.
	if ( event.logger !== 'SimpleUserLogger' ) {
		return null;
	}

	// Bail if the event is not a failed login attempt.
	if (
		event.message_key !== 'user_login_failed' &&
		event.message_key !== 'user_unknown_login_failed'
	) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__occasionsAddOns">
			<p className="SimpleHistoryLogitem__occasionsAddOnsText">
				{ hasExtendedSettingsAddOn ? (
					<a href="options-general.php?page=simple_history_settings_menu_slug&selected-sub-tab=failed-login-attempts">
						{ __(
							'Configure failed login attempts',
							'simple-history'
						) }
					</a>
				) : (
					<ExternalLink href="https://simple-history.com/add-ons/extended-settings/?utm_source=wpadmin#limit-number-of-failed-login-attempts">
						{ __(
							'Limit logged login attempts',
							'simple-history'
						) }
					</ExternalLink>
				) }
			</p>
		</div>
	);
}

/**
 * Outputs a button that when clicked will load and show similar events.
 *
 * @param {Object} props
 */
export function EventOccasions( props ) {
	const { event, eventVariant, hasExtendedSettingsAddOn } = props;
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

	const showOcassionsEventsContent = (
		<>
			<div className="SimpleHistoryLogitem__occasions">
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

				<EventOcassionsAddonsContent
					event={ event }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				/>
			</div>
		</>
	);

	return (
		<div>
			{ ! isShowingOccasions && ! isLoadingOccasions
				? showOcassionsEventsContent
				: null }

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
