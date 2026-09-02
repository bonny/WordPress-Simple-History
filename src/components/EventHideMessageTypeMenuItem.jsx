import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { unseen } from '@wordpress/icons';
import { useEventsSettings } from './EventsSettingsContext';

/**
 * Menu item that hides every event of this event's type (logger + message
 * key) from the current list. Temporary and per-view: it lives in the URL
 * like the other filters and is undone from the chips above the list.
 * The permanent "stop logging this" is a different feature.
 *
 * @param {Object}   props
 * @param {Object}   props.event   The event.
 * @param {Function} props.onClose Close the dropdown menu.
 */
export function EventHideMessageTypeMenuItem( { event, onClose } ) {
	const { canFilterEventsInPlace, hideMessageType } = useEventsSettings();

	// Only where this GUI owns the filters (not the dashboard widget or the
	// admin bar), and only for events that have a type to hide.
	if (
		! canFilterEventsInPlace ||
		! hideMessageType ||
		! event?.logger ||
		! event?.message_key
	) {
		return null;
	}

	return (
		<MenuItem
			icon={ unseen }
			onClick={ () => {
				hideMessageType( event );
				onClose();
			} }
		>
			{ __( 'Hide events of this type', 'simple-history' ) }
		</MenuItem>
	);
}
