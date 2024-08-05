import { Button, __experimentalText as Text } from '@wordpress/components';
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
			const userDisplay = (
				<>
					<strong>{ initiatorData.user_login }</strong>&nbsp;
					<span>({ initiatorData.user_email })</span>
				</>
			);

			return (
				<EventHeaderItem>
					{ eventVariant === 'modal' ? (
						<Text>{ userDisplay }</Text>
					) : (
						<Button
							href={ initiatorData.user_profile_url }
							variant="link"
						>
							{ userDisplay }
						</Button>
					) }
				</EventHeaderItem>
			);

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
