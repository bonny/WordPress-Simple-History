import { createContext, useContext, useState } from '@wordpress/element';
import { PremiumFeaturesUnlockModal } from './PremiumFeaturesUnlockModal';

const PremiumFeaturesModalContext = createContext( null );

export const PremiumFeaturesModalProvider = ( { children } ) => {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ modalProps, setModalProps ] = useState( {
		premiumFeatureModalTitle: '',
		premiumFeatureDescription: '',
	} );

	const showModal = ( title, description ) => {
		setModalProps( {
			premiumFeatureModalTitle: title,
			premiumFeatureDescription: description,
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
