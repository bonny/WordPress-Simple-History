import {
	Button,
	DropdownMenu,
	__experimentalHStack as HStack,
	Icon,
	MenuGroup,
	MenuItem,
	Modal,
	Slot,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { moreVertical, unlock } from '@wordpress/icons';
import { PremiumFeatureSuffix } from './PremiumFeatureSuffix';

function PremiumAddonsPromoMenuGroup( props ) {
	const { handleOnClickPremiumFeature, onClose } = props;

	const showPremiumAddonsMenuGroup = applyFilters(
		'SimpleHistory.showPremiumAddonsMenuGroup',
		true
	);

	if ( showPremiumAddonsMenuGroup === false ) {
		return null;
	}

	const handleClickExport = () => {
		onClose();
		handleOnClickPremiumFeature( {
			featureDescription: 'Export as CSV',
		} );
	};

	const handleClickAddEventManually = () => {
		onClose();
		handleOnClickPremiumFeature( {
			featureDescription: 'Add event manually',
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
			<MenuItem
				onClick={ handleClickAddEventManually }
				suffix={ <PremiumFeatureSuffix /> }
			>
				{ __( 'Add event manually', 'simple-history' ) }
			</MenuItem>
		</MenuGroup>
	);
}

/**
 * Dropdown menu with information about the actions you can do with the events.
 * By default is has "placeholder" items that are promoted to premium features.
 */
export function EventsControlBarActionsDropdownMenu() {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ premiumFeatureDescription, setPremiumFeatureDescription ] =
		useState( '' );

	const handleOnClickPremiumFeature = ( localProps ) => {
		const { featureDescription = '' } = localProps;
		setIsModalOpen( true );
		setPremiumFeatureDescription( featureDescription );
	};

	const handleModalClose = () => {
		setIsModalOpen( false );
	};

	const handleOpenPremiumLink = () => {
		// Open URL in new tab.
		window.open( 'https://simple-history.com/premium/?utm_source=wpadmin' );
		setIsModalOpen( false );
	};

	return (
		<>
			{ isModalOpen ? (
				<Modal
					icon={ <Icon icon={ unlock } /> }
					title="Unlock premium feature"
					onRequestClose={ handleModalClose }
				>
					{ premiumFeatureDescription }

					<p>
						This is a very nice premium feature that you can get by
						upgrading to Simple History Premium.
					</p>

					<p>Features include:</p>
					<ul>
						<li>Export as CSV</li>
						<li>Export as JSON</li>
						<li>Custom log clear interval</li>
					</ul>

					<HStack spacing={ 3 }>
						<Button
							variant="primary"
							onClick={ handleOpenPremiumLink }
						>
							Upgrade to premium
						</Button>

						<Button variant="tertiary" onClick={ handleModalClose }>
							Maybe later
						</Button>
					</HStack>
				</Modal>
			) : null }

			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreVertical }
				// text={ __( 'Actions', 'simple-history' ) }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<MenuItem onClick={ onClose }>
								Copy link to search
							</MenuItem>
						</MenuGroup>

						<PremiumAddonsPromoMenuGroup
							handleOnClickPremiumFeature={
								handleOnClickPremiumFeature
							}
							onClose={ onClose }
						/>
						<Slot name="SimpleHistorySlotEventsControlBarMenu" />
					</>
				) }
			</DropdownMenu>
		</>
	);
}
