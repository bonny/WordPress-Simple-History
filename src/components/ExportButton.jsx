import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { download } from '@wordpress/icons';
import { PremiumIndicator } from './PremiumIndicator';
import { usePremiumFeaturesModal } from './PremiumFeaturesModalContext';
import exportFeatureImage from '../images/premium-feature-export.svg';

/**
 * Export button shown in the control bar.
 * Free users: opens premium promo modal.
 * Premium users: replaced via Slot/filter with real export functionality.
 */
export function ExportButton() {
	const { showModal } = usePremiumFeaturesModal();

	const handleClick = () => {
		showModal(
			__( 'Export to CSV or JSON', 'simple-history' ),
			__(
				'Export your current log selection to CSV or JSON. Your active filters are applied, so you get exactly the events you need.',
				'simple-history'
			),
			download,
			exportFeatureImage
		);
	};

	return (
		<Tooltip
			text={ __(
				'Export events to CSV or JSON',
				'simple-history'
			) }
			delay={ 400 }
		>
			<Button
				icon={ download }
				variant="tertiary"
				size="compact"
				onClick={ handleClick }
				className="sh-ControlBarButton sh-ControlBarButton--export"
				label={ __( 'Export', 'simple-history' ) }
			>
				{ __( 'Export', 'simple-history' ) }
				<PremiumIndicator />
			</Button>
		</Tooltip>
	);
}
