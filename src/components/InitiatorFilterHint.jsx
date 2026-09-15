import { ExternalLink } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';

/**
 * Get the hint text for an initiator, or null when there is none.
 *
 * Only initiators where Premium has something concrete to offer get a hint.
 * WP-CLI and "other" would only get event counts, which is too thin to pitch.
 *
 * @param {string} initiatorKey Initiator key, for example "web_user".
 * @return {string|null} Hint text with an <a> placeholder, or null.
 */
function getHintText( initiatorKey ) {
	switch ( initiatorKey ) {
		case 'web_user':
			return __(
				'<a>Premium</a> can store full IP addresses, so you can trace the exact address behind anonymous events like failed logins.',
				'simple-history'
			);
		case 'wp':
			return __(
				'<a>Premium</a> can alert you by email, Slack, Discord or Telegram when WordPress updates core, plugins or themes.',
				'simple-history'
			);
		default:
			return null;
	}
}

/**
 * One-line Premium hint shown above the events when the log is filtered to a
 * single non-user initiator.
 *
 * This is where the initiator card's "View … events" link lands, so the hint
 * reaches people who just asked to see these events, instead of intercepting
 * them on the card. It replaced the card's premium sales link (issue 280).
 *
 * @param {Object}  props
 * @param {Array}   props.selectedInitiator Selected initiator filter items.
 * @param {boolean} props.eventsIsLoading   Whether events are loading.
 * @param {Array}   props.events            Loaded events.
 * @return {JSX.Element|null} Hint element.
 */
export function InitiatorFilterHint( {
	selectedInitiator,
	eventsIsLoading,
	events,
} ) {
	const { hasPremiumAddOn } = useEventsSettings();

	if (
		hasPremiumAddOn ||
		eventsIsLoading ||
		! events?.length ||
		selectedInitiator?.length !== 1
	) {
		return null;
	}

	const initiatorKey = selectedInitiator[ 0 ].initiator_key;
	const hintText = getHintText( initiatorKey );

	if ( ! hintText ) {
		return null;
	}

	return (
		<p className="sh-InitiatorFilterHint">
			{ createInterpolateElement( hintText, {
				a: (
					<ExternalLink
						href={ getTrackingUrl(
							'https://simple-history.com/add-ons/premium/',
							'premium_initiator_filter',
							'wpadmin',
							'plugin',
							initiatorKey
						) }
					/>
				),
			} ) }
		</p>
	);
}
