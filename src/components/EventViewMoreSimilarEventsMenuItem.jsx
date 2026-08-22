import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { search } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

/**
 * Add menu item that let user view more results from
 *
 * Can add now:
 * - This user
 * - Logger and message
 * - IP address
 *
 * Can add in the future, when support is added to search filter:
 *   - Initiator
 *
 * @param {Object} props
 * @param {Object} props.event
 * @param {string} props.eventsAdminPageURL URL to the events admin page
 */
export function EventViewMoreSimilarEventsMenuItem( {
	event,
	eventsAdminPageURL,
} ) {
	// Every item here builds on this URL, and addQueryArgs() would turn a missing
	// base into a bare "?users=…" — dropping the page= arg and landing the user on
	// "Sorry, you are not allowed to access this page". It is seeded at enqueue
	// time so it should always be here; show nothing rather than a broken link if
	// it somehow isn't.
	if ( ! eventsAdminPageURL ) {
		return null;
	}

	const goToEvents = ( args ) => () => {
		window.location.href = addQueryArgs( eventsAdminPageURL, args );
	};

	const isWPUserInitiatorWithIdAndEmail =
		event?.initiator === 'wp_user' &&
		event?.initiator_data?.user_id &&
		event?.initiator_data?.user_email;

	const isLoggerAndMessageEvent = event?.logger && event?.message_key;

	// An event can carry several addresses — the remote address plus whatever
	// proxy headers were present — and all of them are filterable. Prefer the
	// remote address for this single menu item, since it is the one the web
	// server actually saw; the popover offers the rest individually.
	const ipAddresses = event?.ip_addresses || {};
	const filterableIPAddress =
		ipAddresses._server_remote_addr || Object.values( ipAddresses )[ 0 ];

	return (
		<>
			{ isWPUserInitiatorWithIdAndEmail ? (
				<MenuItem
					icon={ search }
					// The users filter takes an array of objects with id and value keys:
					// …&users=[{%22id%22:%221%22,%22value%22:%22P%C3%A4r+(par@earthpeople.se)%22}]
					onClick={ goToEvents( {
						users: JSON.stringify( [
							{
								id: event.initiator_data.user_id,
								value: event.initiator_data.user_email,
							},
						] ),
					} ) }
				>
					{ __( 'Find events by the same user', 'simple-history' ) }
				</MenuItem>
			) : null }

			{ isLoggerAndMessageEvent ? (
				<MenuItem
					icon={ search }
					// …&messages=[{"value":"+-+All+found+updates","search_options":["AvailableUpdatesLogger:core_update_available"]}]
					onClick={ goToEvents( {
						messages: JSON.stringify( [
							{
								value: event.message_key,
								search_options: [
									`${ event.logger }:${ event.message_key }`,
								],
							},
						] ),
					} ) }
				>
					{ __(
						'Filter event by this event type',
						'simple-history'
					) }
				</MenuItem>
			) : null }

			{ filterableIPAddress ? (
				<MenuItem
					icon={ search }
					onClick={ goToEvents( { ip: filterableIPAddress } ) }
				>
					{ __(
						'Find events from the same IP address',
						'simple-history'
					) }
				</MenuItem>
			) : null }
		</>
	);
}
