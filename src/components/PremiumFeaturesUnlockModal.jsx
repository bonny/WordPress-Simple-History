import {
	Button,
	__experimentalHStack as HStack,
	Icon,
	Modal,
} from '@wordpress/components';
import { unlock } from '@wordpress/icons';

/**
 * Modal that is shown when you click on a premium feature.
 *
 * @param {Object} props
 */
export const PremiumFeaturesUnlockModal = ( props ) => {
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
