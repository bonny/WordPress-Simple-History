import { MenuGroup, MenuItem } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { download, plusCircle } from '@wordpress/icons';
import { PremiumFeatureSuffix } from './PremiumFeatureSuffix';
import { useUserHasCapability } from '../hooks/useUserHasCapability';
import { usePremiumFeaturesModal } from './PremiumFeaturesModalContext';
import exportFeatureImage from '../images/premium-feature-export.svg';
import createEntryFeatureImage from '../images/premium-feature-create-entry.svg';

/**
 * Menu group with premium features menu items that are promoted.
 *
 * When an item is clicked a modal is shown that promotes the premium features.
 *
 * @param {Object} props
 */
export function PremiumAddonsPromoMenuGroup( props ) {
	const { onCloseDropdownMenu } = props;
	const { showModal } = usePremiumFeaturesModal();

	const userHasManageOptionsCapability =
		useUserHasCapability( 'manage_options' );

	// Filter to show/hide the premium addons menu group.
	// Makes it possible to hide the group from external code.
	// If the result of running this filter is false, the group is not shown.
	const showPremiumAddonsMenuGroup = applyFilters(
		'SimpleHistory.showPremiumAddonsMenuGroup',
		true
	);

	if ( showPremiumAddonsMenuGroup === false ) {
		return null;
	}

	const handleClickExportPromo = () => {
		onCloseDropdownMenu();

		showModal(
			__( 'Unlock Export', 'simple-history' ),
			__(
				'Export your current log selection to CSV or JSON. Your active filters are applied, so you get exactly the events you need.',
				'simple-history'
			),
			download,
			exportFeatureImage
		);
	};

	const handleClickAddEventManuallyPromo = () => {
		onCloseDropdownMenu();

		showModal(
			__( 'Unlock Manual Entries', 'simple-history' ),
			__(
				'Add custom events to your activity log with a simple form. Perfect for noting important changes or decisions that happen outside WordPress.',
				'simple-history'
			),
			plusCircle,
			createEntryFeatureImage
		);
	};

	// Show the "Create log entry…" menu item only if the user has the
	// "manage_options" capability.
	const customManualEntriesPrompMenuItem = userHasManageOptionsCapability ? (
		<MenuItem
			onClick={ handleClickAddEventManuallyPromo }
			suffix={ <PremiumFeatureSuffix /> }
			info={ __(
				'Manually add custom events to the activity log',
				'simple-history'
			) }
		>
			{ __( 'Create log entry…', 'simple-history' ) }
		</MenuItem>
	) : null;

	return (
		<MenuGroup>
			<MenuItem
				onClick={ handleClickExportPromo }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __( 'CSV and JSON supported', 'simple-history' ) }
			>
				{ __( 'Export results…', 'simple-history' ) }
			</MenuItem>

			{ customManualEntriesPrompMenuItem }
		</MenuGroup>
	);
}
