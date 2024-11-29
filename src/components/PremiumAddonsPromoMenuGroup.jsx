import { MenuGroup, MenuItem } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { PremiumFeatureSuffix } from './PremiumFeatureSuffix';

/**
 * Menu group with premium features menu items that are promoted.
 *
 * When an item is clicked a modal is shown that promotes the premium features.
 *
 * @param {Object} props
 */
export function PremiumAddonsPromoMenuGroup( props ) {
	const { handleOnClickPremiumFeature, onCloseDropdownMenu } = props;

	// Filter to show/hide the premium addons menu group.
	// Makes it possible to hide the group from external code.
	const showPremiumAddonsMenuGroup = applyFilters(
		'SimpleHistory.showPremiumAddonsMenuGroup',
		true
	);

	if ( showPremiumAddonsMenuGroup === false ) {
		return null;
	}

	const handleClickExport = () => {
		onCloseDropdownMenu();
		handleOnClickPremiumFeature( {
			featureDescription: 'Export filtered events as CSV',
		} );
	};

	const handleClickAddEventManually = () => {
		onCloseDropdownMenu();
		handleOnClickPremiumFeature( {
			featureDescription: 'Add event manually!',
		} );
	};

	return (
		<MenuGroup>
			<MenuItem
				onClick={ handleClickExport }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __( 'CSV and JSON supported', 'simple-history' ) }
			>
				{ __( '📤 Export results…', 'simple-history' ) }
			</MenuItem>

			<MenuItem
				onClick={ handleClickAddEventManually }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __(
					'Broadcast messages to log viewers',
					'simple-history'
				) }
			>
				{ __( '📣 Add event manually', 'simple-history' ) }
			</MenuItem>
		</MenuGroup>
	);
}
