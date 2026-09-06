import { createContext, useContext, useState } from '@wordpress/element';
import { PremiumFeaturesUnlockModal } from './PremiumFeaturesUnlockModal';

const PremiumFeaturesModalContext = createContext( null );

// Fallback campaign for callers that don't name their trigger.
// Every trigger-specific campaign extends this prefix, so a
// `BEGINS_WITH premium_global_modal` filter still catches them all.
const DEFAULT_UTM_CAMPAIGN = 'premium_global_modal';

export const PremiumFeaturesModalProvider = ( { children } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ modalProps, setModalProps ] = useState( {
		premiumFeatureModalTitle: '',
		premiumFeatureDescription: '',
		icon: null,
		image: '',
		utmCampaign: DEFAULT_UTM_CAMPAIGN,
	} );

	/**
	 * Show the premium feature modal.
	 *
	 * @param {string} title       - The feature name (e.g., "Export results")
	 * @param {string} description - Description of the feature
	 * @param {Object} icon        - Feature-specific icon (JSX/SVG)
	 * @param {string} image       - Path to feature screenshot image
	 * @param {string} utmCampaign - Campaign for the "Get Premium" link
	 */
	const showModal = (
		title,
		description,
		icon,
		image,
		utmCampaign = DEFAULT_UTM_CAMPAIGN
	) => {
		setModalProps( {
			premiumFeatureModalTitle: title,
			premiumFeatureDescription: description,
			icon,
			image,
			utmCampaign,
		} );
		setIsOpen( true );
	};

	const handleClose = () => {
		setIsOpen( false );
	};

	return (
		<PremiumFeaturesModalContext.Provider value={ { showModal } }>
			{ children }
			{ isOpen && (
				<PremiumFeaturesUnlockModal
					{ ...modalProps }
					handleModalClose={ handleClose }
				/>
			) }
		</PremiumFeaturesModalContext.Provider>
	);
};

export const usePremiumFeaturesModal = () => {
	const context = useContext( PremiumFeaturesModalContext );
	if ( ! context ) {
		throw new Error(
			'usePremiumFeaturesModal must be used within a PremiumFeaturesModalProvider'
		);
	}
	return context;
};
