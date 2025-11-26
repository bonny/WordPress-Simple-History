import { createContext, useContext, useState } from '@wordpress/element';
import { PremiumFeaturesUnlockModal } from './PremiumFeaturesUnlockModal';

const PremiumFeaturesModalContext = createContext( null );

export const PremiumFeaturesModalProvider = ( { children } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ modalProps, setModalProps ] = useState( {
		premiumFeatureModalTitle: '',
		premiumFeatureDescription: '',
		icon: null,
		image: '',
	} );

	/**
	 * Show the premium feature modal.
	 *
	 * @param {string} title       - The feature name (e.g., "Export results")
	 * @param {string} description - Description of the feature
	 * @param {Object} icon        - Feature-specific icon (JSX/SVG)
	 * @param {string} image       - Path to feature screenshot image
	 */
	const showModal = ( title, description, icon, image ) => {
		setModalProps( {
			premiumFeatureModalTitle: title,
			premiumFeatureDescription: description,
			icon,
			image,
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
