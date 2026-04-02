import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { bell } from '@wordpress/icons';
import { PremiumIndicator } from './PremiumIndicator';
import { usePremiumFeaturesModal } from './PremiumFeaturesModalContext';
import alertsFeatureImage from '../images/premium-feature-alerts.svg';

/**
 * "Create alert" button shown in the control bar.
 * Always visible for maximum discoverability — this is the
 * highest-conversion premium upsell moment.
 *
 * Modal copy adapts based on whether filters are active:
 * - With filters: acknowledges the user's intent ("events like these")
 * - Without filters: educates about the feature and suggests filtering
 *
 * Free users: opens premium promo modal.
 * Premium users: replaced via filter with real alert creation.
 *
 * @param {Object}  props
 * @param {boolean} props.hasActiveFilters Whether any search filters are active.
 */
export function CreateAlertButton( { hasActiveFilters } ) {
	const { showModal } = usePremiumFeaturesModal();

	const handleClick = () => {
		const description = hasActiveFilters
			? __(
					'Get notified when events like these happen. Receive instant alerts via email, Slack, Discord, or Telegram — based on the filters you just set.',
					'simple-history'
			  )
			: __(
					'Get notified when important events happen. Set up filters to target specific activity — like failed logins or plugin changes — then receive instant alerts via email, Slack, Discord, or Telegram.',
					'simple-history'
			  );

		showModal(
			__( 'Get Instant Alerts', 'simple-history' ),
			description,
			bell,
			alertsFeatureImage
		);
	};

	const tooltipText = hasActiveFilters
		? __( 'Get alerts when events like these happen', 'simple-history' )
		: __( 'Get alerts via email, Slack, and more', 'simple-history' );

	return (
		<Tooltip text={ tooltipText } delay={ 400 }>
			<Button
				icon={ bell }
				variant="tertiary"
				size="compact"
				onClick={ handleClick }
				className="sh-ControlBarButton sh-ControlBarButton--alert"
			>
				{ __( 'Create alert', 'simple-history' ) }
				<PremiumIndicator />
			</Button>
		</Tooltip>
	);
}
