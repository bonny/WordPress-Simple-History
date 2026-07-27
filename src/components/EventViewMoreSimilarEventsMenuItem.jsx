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
	const isWPUserInitiatorWithIdAndEmail =
		event?.initiator === 'wp_user' &&
		event?.initiator_data?.user_id &&
		event?.initiator_data?.user_email;

	const isLoggerAndMessageEvent = event?.logger && event?.message_key;

	// An event can carry several addresses — the remote address plus whatever
	// proxy headers were present — but the ip filter only ever matches against
	// the _server_remote_addr context key (see Log_Query). Falling back to a
	// header address would build a filter that cannot match, landing the user on
	// an empty result page, so offer the item only when the remote address is
	// there. It is also the trustworthy one: header values are client-supplied.
	const filterableIPAddress = event?.ip_addresses?._server_remote_addr;

	return (
		<>
			{ isWPUserInitiatorWithIdAndEmail ? (
				<MenuItem
					icon={ search }
					onClick={ () => {
						// Example URL when searching for user, where user key is an array of objects with id and value keys.
						// /wp-admin/admin.php?page=simple_history_admin_menu_page&users=[{%22id%22:%221%22,%22value%22:%22P%C3%A4r+(par@earthpeople.se)%22}]
						const userJsonString = JSON.stringify( [
							{
								id: event.initiator_data.user_id,
								value: event.initiator_data.user_email,
							},
						] );
						const viewUserEventsURL = addQueryArgs(
							eventsAdminPageURL,
							{
								users: userJsonString,
							}
						);
						window.location.href = viewUserEventsURL;
					} }
				>
					{ __( 'Find events by the same user', 'simple-history' ) }
				</MenuItem>
			) : null }

			{ isLoggerAndMessageEvent ? (
				<MenuItem
					icon={ search }
					onClick={ () => {
						// /wp-admin/admin.php?page=simple_history_admin_menu_page&messages=[{"value":"+-+All+found+updates","search_options":["AvailableUpdatesLogger:core_update_available","AvailableUpdatesLogger:plugin_update_available","AvailableUpdatesLogger:theme_update_available"]}]
						const messageJsonString = JSON.stringify( [
							{
								value: event.message_key,
								search_options: [
									`${ event.logger }:${ event.message_key }`,
								],
							},
						] );
						const viewUserEventsURL = addQueryArgs(
							eventsAdminPageURL,
							{
								messages: messageJsonString,
							}
						);
						window.location.href = viewUserEventsURL;
					} }
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
					onClick={ () => {
						// The events page reads the 'ip' query arg on load.
						// /wp-admin/admin.php?page=simple_history_admin_menu_page&ip=192.168.1.1
						const viewIPEventsURL = addQueryArgs(
							eventsAdminPageURL,
							{
								ip: filterableIPAddress,
							}
						);
						window.location.href = viewIPEventsURL;
					} }
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
