import { MenuGroup, MenuItem } from '@wordpress/components';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { PremiumFeatureSuffix } from './PremiumFeatureSuffix';
import { useUserHasCapability } from '../hooks/useUserHasCapability';

/**
 * Menu group with premium features menu items that are promoted.
 *
 * When an item is clicked a modal is shown that promotes the premium features.
 *
 * @param {Object} props
 */
export function PremiumAddonsPromoMenuGroup( props ) {
	const { handleOnClickPremiumFeature, onCloseDropdownMenu } = props;

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

		handleOnClickPremiumFeature( {
			featureTitle: 'Export results',
			featureDescription: (
				<>
					<p
						style={ {
							backgroundColor: 'var(--sh-color-yellow)',
							fontSize: 'var(--sh-font-size-large)',
							padding: '1rem 2rem',
						} }
					>
						<strong>Export results</strong> is a premium feature.
					</p>

					<p
						style={ {
							fontSize: 'var(--sh-font-size-large)',
						} }
					>
						The export function supports CSV and JSON and gives you
						a downloaded file of the current search result.
					</p>
				</>
			),
		} );
	};

	const handleClickAddEventManuallyPromo = () => {
		onCloseDropdownMenu();

		handleOnClickPremiumFeature( {
			featureTitle: 'Create log entry',
			featureDescription: (
				<>
					<p
						style={ {
							backgroundColor: 'var(--sh-color-yellow)',
							fontSize: '1.1rem',
							padding: '1rem 2rem',
						} }
					>
						<strong>Create log entry manually</strong> is a premium
						feature.
					</p>

					<p style={ { fontSize: 'var(--sh-font-size-large)' } }>
						This feature allows you to manually add custom events to
						the activity log, using a simple GUI.
					</p>

					<p style={ { fontSize: 'var(--sh-font-size-large)' } }>
						Only administrators can add events, but all users who
						can view the log can see the added entries.
					</p>
				</>
			),
		} );
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
