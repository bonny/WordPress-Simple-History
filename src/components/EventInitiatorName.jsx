import { __ } from '@wordpress/i18n';
import { Button, __experimentalText as Text } from '@wordpress/components';

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
				<span className="SimpleHistoryLogitem__inlineDivided">
					<strong>{ initiatorData.user_login }</strong>{ ' ' }
					<span>({ initiatorData.user_email })</span>
				</span>
			);

			return (
				<>
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
				</>
			);

		case 'web_user':
			return (
				<strong className="SimpleHistoryLogitem__inlineDivided">
					{ __( 'Anonymous web user', 'simple-history' ) }
				</strong>
			);
		case 'wp_cli':
			return (
				<strong className="SimpleHistoryLogitem__inlineDivided">
					{ __( 'WP-CLI', 'simple-history' ) }
				</strong>
			);
		case 'wp':
			return (
				<strong className="SimpleHistoryLogitem__inlineDivided">
					{ __( 'WordPress', 'simple-history' ) }
				</strong>
			);
		case 'other':
			return (
				<strong className="SimpleHistoryLogitem__inlineDivided">
					{ __( 'Other', 'simple-history' ) }
				</strong>
			);
		default:
			return (
				<p>
					Unknown initiator: <code>{ event.initiator }</code>
				</p>
			);
	}
}
