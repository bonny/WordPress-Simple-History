import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Outputs "WordPress" or "John Doe - erik@example.com".
 *
 * @param {Object} props
 */
export function EventInitiatorName( props ) {
	const { event, eventVariant } = props;
	const { initiator_data: initiatorData } = event;

	switch ( event.initiator ) {
		case 'wp_user':
			const nameToDisplay =
				initiatorData.user_display_name || initiatorData.user_login;

			let userDisplay;

			if ( eventVariant === 'compact' ) {
				userDisplay = <strong>{ nameToDisplay }</strong>;
			} else if ( eventVariant === 'modal' ) {
				userDisplay = <strong>{ nameToDisplay }</strong>;
			} else {
				userDisplay = (
					<Button
						href={ initiatorData.user_profile_url }
						variant="link"
					>
						<>
							<strong>{ nameToDisplay }</strong>&nbsp;
							<span>({ initiatorData.user_email })</span>
						</>
					</Button>
				);
			}

			return <EventHeaderItem>{ userDisplay }</EventHeaderItem>;

		case 'web_user':
			return (
				<EventHeaderItem>
					<strong>
						{ __( 'Anonymous web user', 'simple-history' ) }
					</strong>
				</EventHeaderItem>
			);
		case 'wp_cli':
			return (
				<EventHeaderItem>
					<strong>{ __( 'WP-CLI', 'simple-history' ) }</strong>
				</EventHeaderItem>
			);
		case 'wp':
			return (
				<EventHeaderItem>
					<strong>{ __( 'WordPress', 'simple-history' ) }</strong>
				</EventHeaderItem>
			);
		case 'other':
			return (
				<EventHeaderItem>
					<strong>{ __( 'Other', 'simple-history' ) }</strong>
				</EventHeaderItem>
			);
		default:
			return (
				<EventHeaderItem>
					Unknown initiator: <code>{ event.initiator }</code>
				</EventHeaderItem>
			);
	}
}
