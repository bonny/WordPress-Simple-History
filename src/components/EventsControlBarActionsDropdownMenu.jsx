import { DropdownMenu, MenuGroup, MenuItem, Slot } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { moreVertical } from '@wordpress/icons';
import { PremiumAddonsPromoMenuGroup } from './PremiumAddonsPromoMenuGroup';
import { PremiumFeaturesUnlockModal } from './PremiumFeaturesUnlockModal';

/**
 * Dropdown menu with information about the actions you can do with the events.
 * By default is has "placeholder" items that are promoted to premium features.
 *
 * @param {Object} props
 */
export function EventsControlBarActionsDropdownMenu( props ) {
	const { eventsQueryParams, eventsTotal } = props;

	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ premiumFeatureDescription, setPremiumFeatureDescription ] =
		useState( '' );
	const [ premiumFeatureTitle, setPremiumFeatureTitle ] = useState( '' );

	const handleOnClickPremiumFeature = ( localProps ) => {
		const { featureDescription = '', featureTitle = '' } = localProps;
		setIsModalOpen( true );
		setPremiumFeatureTitle( featureTitle );
		setPremiumFeatureDescription( featureDescription );
	};

	const handleModalClose = () => {
		setIsModalOpen( false );
	};

	return (
		<>
			{ isModalOpen ? (
				<PremiumFeaturesUnlockModal
					premiumFeatureModalTitle={ premiumFeatureTitle }
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
						{ /* <MenuGroup>
							<MenuItem onClick={ onClose }>
								Copy link to search
							</MenuItem>
						</MenuGroup> */ }

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
								eventsQueryParams,
								eventsTotal,
							} }
						/>
					</>
				) }
			</DropdownMenu>
		</>
	);
}
