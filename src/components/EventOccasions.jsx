import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { EventOccasionsList } from './EventOccasionsList';
import { getTrackingUrl } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';

/**
 * Displays some text for failed login attempts.
 *
 * If the Extended Settings add-on is active, the text will be a link to the settings page.
 * If the Premium add-on is active, the text will be a link to the settings page.
 * If the Extended Settings add-on is not active, the text will be a link to the add-on page.
 *
 * @param {Object} props
 */
function EventOccasionsAddonsContent( props ) {
	const { event } = props;
	const {
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		hasFailedLoginLimit,
		eventsSettingsPageURL,
	} = useEventsSettings();

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

	let content;

	if ( hasExtendedSettingsAddOn || hasPremiumAddOn ) {
		// Premium/Extended Settings: link to configure.
		content = (
			<a
				href={ `${ eventsSettingsPageURL }&selected-tab=general_settings_subtab_general&selected-sub-tab=failed-login-attempts` }
			>
				{ __( 'Configure failed login attempts', 'simple-history' ) }
			</a>
		);
	} else if ( ! hasFailedLoginLimit ) {
		// No limiting active: upsell the feature.
		content = (
			<ExternalLink
				href={ getTrackingUrl(
					'https://simple-history.com/add-ons/premium/#limit-number-of-failed-login-attempts',
					'premium_events_loginlimit'
				) }
			>
				{ __(
					'Limit logged login attempts (Premium)',
					'simple-history'
				) }
			</ExternalLink>
		);
	}
	// When hasFailedLoginLimit is active, the banner handles the messaging.

	if ( ! content ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__occasionsAddOns">
			<p className="SimpleHistoryLogitem__occasionsAddOnsText">
				{ content }
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
	const {
		event,
		eventVariant,
	} = props;
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
				'date_local',
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
				'ip_addresses',
				'via',
			],
		};

		try {
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
			setIsShowingOccasions( true );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Simple History: Failed to load occasions', error );
		} finally {
			setIsLoadingOccasions( false );
		}
	};

	const showOccasionsEventsContent = (
		<div className="SimpleHistoryLogitem__occasions">
			<Button
				variant="link"
				aria-expanded={ false }
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
						subsequentOccasionsCount - 1,
						'simple-history'
					),
					subsequentOccasionsCount - 1
				) }
			</Button>

			<EventOccasionsAddonsContent
				event={ event }
			/>
		</div>
	);

	return (
		<div>
			{ ! isShowingOccasions && ! isLoadingOccasions
				? showOccasionsEventsContent
				: null }

			{ isLoadingOccasions ? (
				<div className="SimpleHistoryLogitem__occasions">
					{ __( 'Loading…', 'simple-history' ) }
				</div>
			) : null }

			{ isShowingOccasions ? (
				<>
					<div className="SimpleHistoryLogitem__occasions">
						<Button
							variant="link"
							aria-expanded={ true }
							onClick={ () => setIsShowingOccasions( false ) }
						>
							{ sprintf(
								/* translators: %s: number of similar events */
								__( 'Showing %1$s more', 'simple-history' ),
								subsequentOccasionsCount - 1
							) }
						</Button>
					</div>

					<EventOccasionsList
						isLoadingOccasions={ isLoadingOccasions }
						isShowingOccasions={ isShowingOccasions }
						occasions={ occasions }
						parentEvent={ event }
						eventVariant={ eventVariant }
						subsequent_occasions_count={ subsequentOccasionsCount }
						occasionsCountMaxReturn={ occasionsCountMaxReturn }
					/>
				</>
			) : null }
		</div>
	);
}
