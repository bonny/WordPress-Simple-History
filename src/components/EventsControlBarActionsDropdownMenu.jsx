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
import {
	moreVertical,
	moreHorizontal,
	moreHorizontalMobile,
	unlock,
} from '@wordpress/icons';
import { PremiumFeatureSuffix } from './PremiumFeatureSuffix';

function PremiumAddonsPromoMenuGroup( props ) {
	const { handleOnClickPremiumFeature, onCloseDropdownMenu } = props;

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
			featureDescription: 'Export as CSV',
		} );
	};

	const handleClickAddEventManually = () => {
		onCloseDropdownMenu();
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
 * Modal that is shown when you click on a premium feature.
 *
 * @param {Object} props
 */
const UnlockModal = ( props ) => {
	const { premiumFeatureDescription, handleModalClose } = props;

	const handleOpenPremiumLink = () => {
		// Open URL in new tab.
		window.open( 'https://simple-history.com/premium/?utm_source=wpadmin' );
		handleModalClose();
	};

	return (
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
				<Button variant="primary" onClick={ handleOpenPremiumLink }>
					Upgrade to premium
				</Button>

				<Button variant="tertiary" onClick={ handleModalClose }>
					Maybe later
				</Button>
			</HStack>
		</Modal>
	);
};

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

	return (
		<>
			{ isModalOpen ? (
				<UnlockModal
					premiumFeatureDescription={ premiumFeatureDescription }
					handleModalClose={ handleModalClose }
				/>
			) : null }

			<DropdownMenu
				label={ __(
					'Actions (Export & other tools)',
					'simple-history'
				) }
				icon={ moreVertical } // or moreHorizontal or moreHorizontalMobile
				toggleProps={ {
					iconPosition: 'right',
				} }
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
							onCloseDropdownMenu={ onClose }
						/>

						<Slot
							name="SimpleHistorySlotEventsControlBarMenu"
							fillProps={ {
								onClose,
								yo: 'lo',
							} }
						/>
					</>
				) }
			</DropdownMenu>
		</>
	);
}
