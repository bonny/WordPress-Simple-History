import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';
import { UserCard } from './UserCard';

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
			} else if ( eventVariant === 'dashboard' ) {
				userDisplay = (
					<UserCard event={ event }>
						<strong>{ nameToDisplay }</strong>
					</UserCard>
				);
			} else if ( eventVariant === 'modal' ) {
				userDisplay = <strong>{ nameToDisplay }</strong>;
			} else {
				userDisplay = (
					<UserCard event={ event }>
						<>
							<strong>{ nameToDisplay }</strong>&nbsp;
							<span>({ initiatorData.user_email })</span>
						</>
					</UserCard>
				);
			}

			return <EventHeaderItem>{ userDisplay }</EventHeaderItem>;

		case 'web_user':
			return (
				<EventHeaderItem>
					<UserCard event={ event }>
						<strong>
							{ __( 'Anonymous web user', 'simple-history' ) }
						</strong>
					</UserCard>
				</EventHeaderItem>
			);
		case 'wp_cli':
			return (
				<EventHeaderItem>
					<UserCard event={ event }>
						<strong>{ __( 'WP-CLI', 'simple-history' ) }</strong>
					</UserCard>
				</EventHeaderItem>
			);
		case 'wp':
			return (
				<EventHeaderItem>
					<UserCard event={ event }>
						<strong>{ __( 'WordPress', 'simple-history' ) }</strong>
					</UserCard>
				</EventHeaderItem>
			);
		case 'other':
			return (
				<EventHeaderItem>
					<UserCard event={ event }>
						<strong>{ __( 'Other', 'simple-history' ) }</strong>
					</UserCard>
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
