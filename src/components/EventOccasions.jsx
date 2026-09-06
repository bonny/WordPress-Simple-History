import apiFetch from '@wordpress/api-fetch';
import { ExternalLink } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { EventOccasionsList } from './EventOccasionsList';
import { numberFormatI18n, getTrackingUrl } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';

// Mirrors User_Logger::get_failed_login_message_keys() in PHP.
const FAILED_LOGIN_MESSAGE_KEYS = [
	'user_login_failed',
	'user_unknown_login_failed',
	'user_application_password_login_failed',
	'user_application_password_unknown_login_failed',
];

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

	// Bail if the event is not a failed login attempt. Application password
	// failures on the REST API count too; the same settings throttle them.
	if ( ! FAILED_LOGIN_MESSAGE_KEYS.includes( event.message_key ) ) {
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

	const occasionsCount = subsequentOccasionsCount - 1;

	const loadOccasions = async () => {
		setIsLoadingOccasions( true );

		const eventsQueryParams = {
			type: 'occasions',
			logRowID: event.id,
			occasionsID: event.occasions_id,
			occasionsCount,
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

	// Clicking while expanded collapses back to the count. Clicking while
	// collapsed loads and expands. This is the same button in both states —
	// see the label below — so keyboard/screen-reader focus never drops the
	// way it would if collapsed and expanded rendered two different buttons.
	const onToggleClick = ( evt ) => {
		evt.preventDefault();

		// Ignore clicks while a request is already in flight.
		if ( isLoadingOccasions ) {
			return;
		}

		if ( isShowingOccasions ) {
			setIsShowingOccasions( false );

			return;
		}

		loadOccasions();
	};

	let toggleLabel;

	if ( isLoadingOccasions ) {
		toggleLabel = __( 'Loading…', 'simple-history' );
	} else if ( isShowingOccasions ) {
		toggleLabel = sprintf(
			/* translators: %1$s: number of similar events */
			_n(
				'Hide %1$s similar event',
				'Hide %1$s similar events',
				occasionsCount,
				'simple-history'
			),
			numberFormatI18n( occasionsCount )
		);
	} else {
		toggleLabel = sprintf(
			/* translators: %1$s: number of similar events */
			_n(
				'Show %1$s similar event',
				'Show %1$s similar events',
				occasionsCount,
				'simple-history'
			),
			numberFormatI18n( occasionsCount )
		);
	}

	return (
		<div>
			<div className="SimpleHistoryLogitem__occasions">
				<button
					type="button"
					className="SimpleHistory__disclosureToggle"
					aria-expanded={ isShowingOccasions }
					aria-busy={ isLoadingOccasions }
					onClick={ onToggleClick }
				>
					{ toggleLabel }
				</button>

				{ ! isShowingOccasions && ! isLoadingOccasions ? (
					<EventOccasionsAddonsContent event={ event } />
				) : null }
			</div>

			{ isShowingOccasions ? (
				<EventOccasionsList
					isLoadingOccasions={ isLoadingOccasions }
					isShowingOccasions={ isShowingOccasions }
					occasions={ occasions }
					parentEvent={ event }
					eventVariant={ eventVariant }
					subsequent_occasions_count={ subsequentOccasionsCount }
					occasionsCountMaxReturn={ occasionsCountMaxReturn }
				/>
			) : null }
		</div>
	);
}
