import { DropdownMenu, MenuGroup, MenuItem, Slot } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile, search } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';

/**
 * Add menu item that let user view more results from
 *
 * Can add now:
 * - This user
 * - Logger and message
 *
 * Can add in the future, when support is added to search filter:
 *   - Initiator
 *   - IP address
 *
 * @param {Object} props
 * @param {Object} props.event
 * @param {string} props.eventsAdminPageURL URL to the events admin page
 */
function EventViewMoreSimilarEventsMenuItem( { event, eventsAdminPageURL } ) {
	const isWPUserInitiatorWithIdAndEmail =
		event?.initiator === 'wp_user' &&
		event?.initiator_data?.user_id &&
		event?.initiator_data?.user_email;

	const isLoggerAndMessageEvent = event?.logger && event?.message_key;

	return (
		<>
			{ isWPUserInitiatorWithIdAndEmail ? (
				<MenuItem
					icon={ search }
					iconPosition="left"
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
					iconPosition="left"
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
		</>
	);
}

/**
 * The button with three dots that opens a dropdown with actions for the event.
 *
 * @param {Object} props
 * @param {Object} props.event              The event object
 * @param {string} props.eventVariant       The variant of the event ('normal' or 'modal')
 * @param {string} props.eventsAdminPageURL URL to the events admin page
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( {
	event,
	eventVariant,
	eventsAdminPageURL,
} ) {
	// Don't show actions on modal events.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__actions">
			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreHorizontalMobile }
				popoverProps={ {
					placement: 'left-start',
					inline: true,
				} }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<EventDetailsMenuItem
								event={ event }
								eventVariant={ eventVariant }
								onClose={ onClose }
							/>

							<EventCopyLinkMenuItem event={ event } />

							<EventViewMoreSimilarEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
							/>
						</MenuGroup>

						<Slot
							name="SimpleHistorySlotEventActionsMenu"
							fillProps={ {
								onClose,
								event,
								eventVariant,
							} }
						/>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
