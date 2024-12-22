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
	// If the result of running this filter is false, the group is not shown.
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
			featureTitle: 'Export results',
			featureDescription: (
				<>
					<p
						style={ {
							backgroundColor: 'rgb(251 246 126)',
							fontSize: '1.1rem',
							padding: '1rem 2rem',
						} }
					>
						<strong>Export results</strong> is a premium feature.
					</p>

					<p>
						The export function supports CSV and JSON and gives you
						a downloaded file of the current search result.
					</p>
				</>
			),
		} );
	};

	const handleClickAddEventManually = () => {
		onCloseDropdownMenu();
		handleOnClickPremiumFeature( {
			featureTitle: 'Add event manually',
			// 'Add event manually is a premium feature!',
			featureDescription: (
				<>
					<p
						style={ {
							backgroundColor: 'rgb(251 246 126)',
							fontSize: '1.1rem',
							padding: '1rem 2rem',
						} }
					>
						<strong>Add event manually</strong> is a premium
						feature.
					</p>

					<p>
						This feature allows you to broadcast messages to log
						viewers.
					</p>
				</>
			),
		} );
	};

	return (
		<MenuGroup>
			<MenuItem
				onClick={ handleClickExport }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __( 'CSV and JSON supported', 'simple-history' ) }
			>
				{ __( 'Export results…', 'simple-history' ) }
			</MenuItem>

			{ /* <MenuItem
				onClick={ handleClickAddEventManually }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __(
					'Broadcast messages to log viewers',
					'simple-history'
				) }
			>
				{ __( 'Add event manually', 'simple-history' ) }
			</MenuItem> */ }
		</MenuGroup>
	);
}
