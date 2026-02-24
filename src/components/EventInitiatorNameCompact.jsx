import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Lightweight initiator name for compact event lists (admin bar, sidebar).
 * Shows only the display name without profile links or buttons.
 *
 * Unlike EventInitiatorName, this does not import @wordpress/components,
 * keeping the admin bar bundle small.
 *
 * @param {Object} props
 * @param {Object} props.event Event object with initiator and initiator_data.
 */
export function EventInitiatorNameCompact( { event } ) {
	const { initiator_data: initiatorData } = event;

	let label;

	switch ( event.initiator ) {
		case 'wp_user':
			label = initiatorData.user_display_name || initiatorData.user_login;
			break;
		case 'web_user':
			label = __( 'Anonymous web user', 'simple-history' );
			break;
		case 'wp_cli':
			label = __( 'WP-CLI', 'simple-history' );
			break;
		case 'wp':
			label = __( 'WordPress', 'simple-history' );
			break;
		case 'other':
			label = __( 'Other', 'simple-history' );
			break;
		default:
			label = event.initiator;
	}

	return (
		<EventHeaderItem>
			<strong>{ label }</strong>
		</EventHeaderItem>
	);
}
