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
 * Experimental: the chips it adds above the list are a new kind of filter
 * UI, and how negative filters fit the filter bar as a whole is still an
 * open question. Off until experimental features are enabled.
 *
 * @param {Object}   props
 * @param {Object}   props.event   The event.
 * @param {Function} props.onClose Close the dropdown menu.
 */
export function EventHideMessageTypeMenuItem( { event, onClose } ) {
	const {
		experimentalFeaturesEnabled,
		canFilterEventsInPlace,
		hideMessageType,
	} = useEventsSettings();

	// Only with experimental features on, only where this GUI owns the filters
	// (not the dashboard widget or the admin bar), and only for events that
	// have a type to hide.
	if (
		! experimentalFeaturesEnabled ||
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
