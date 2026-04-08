import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plusCircle } from '@wordpress/icons';
import { PremiumIndicator } from './PremiumIndicator';
import { usePremiumFeaturesModal } from './PremiumFeaturesModalContext';
import { useEventsSettings } from './EventsSettingsContext';
import createEntryFeatureImage from '../images/premium-feature-create-entry.svg';

/**
 * "Create log entry" button shown in the control bar.
 * Only visible to users with manage_options capability (admins).
 * No premium star indicator — let the click be the discovery.
 *
 * Uses userCanManageOptions from the EventsSettings context,
 * which is populated from the search-options API response.
 *
 * Free users: opens premium promo modal.
 * Premium users: replaced via filter with real log entry creation.
 */
export function CreateLogEntryButton() {
	const { showModal } = usePremiumFeaturesModal();
	const { userCanManageOptions } = useEventsSettings();

	if ( ! userCanManageOptions ) {
		return null;
	}

	const handleClick = () => {
		showModal(
			__( 'Add Custom Log Entries', 'simple-history' ),
			__(
				'Add custom events to your activity log with a simple form. Perfect for noting important changes or decisions that happen outside WordPress.',
				'simple-history'
			),
			plusCircle,
			createEntryFeatureImage
		);
	};

	return (
		<Tooltip
			text={ __(
				'Add a custom note to the activity log',
				'simple-history'
			) }
			delay={ 400 }
		>
			<Button
				icon={ plusCircle }
				variant="tertiary"
				size="compact"
				onClick={ handleClick }
				className="sh-ControlBarButton sh-ControlBarButton--createEntry"
			>
				{ __( 'Add log entry', 'simple-history' ) }
				<PremiumIndicator />
			</Button>
		</Tooltip>
	);
}
