import apiFetch from '@wordpress/api-fetch';
import { Button, ExternalLink } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { EventOccasionsList } from './EventOccasionsList';

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
	const {
		event,
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		eventsSettingsPageURL,
	} = props;

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

	const configureLoginAttemptsLinkDependingOnAddOns =
		hasExtendedSettingsAddOn || hasPremiumAddOn ? (
			<a
				href={ `${ eventsSettingsPageURL }&selected-sub-tab=failed-login-attempts` }
			>
				{ __( 'Configure failed login attempts', 'simple-history' ) }
			</a>
		) : (
			<ExternalLink href="https://simple-history.com/add-ons/premium/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=login-attempts-limit#limit-number-of-failed-login-attempts">
				{ __(
					'Limit logged login attempts (Premium)',
					'simple-history'
				) }
			</ExternalLink>
		);

	return (
		<div className="SimpleHistoryLogitem__occasionsAddOns">
			<p className="SimpleHistoryLogitem__occasionsAddOnsText">
				{ configureLoginAttemptsLinkDependingOnAddOns }
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
		hasExtendedSettingsAddOn,
		hasPremiumAddOn,
		eventsSettingsPageURL,
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

	const showOccasionsEventsContent = (
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

				<EventOccasionsAddonsContent
					event={ event }
					eventsSettingsPageURL={ eventsSettingsPageURL }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
					hasPremiumAddOn={ hasPremiumAddOn }
				/>
			</div>
		</>
	);

	return (
		<div>
			{ ! isShowingOccasions && ! isLoadingOccasions
				? showOccasionsEventsContent
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
