import { Button, Icon, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';

/**
 * Modal that is shown when you click on a premium feature.
 *
 * @param {Object}   props
 * @param {string}   props.premiumFeatureModalTitle  - The modal title (e.g., "Export is a PREMIUM feature")
 * @param {string}   props.premiumFeatureDescription - Description of the feature
 * @param {Object}   props.icon                      - Feature-specific icon (JSX/SVG)
 * @param {string}   props.image                     - Path to feature screenshot image
 * @param {Function} props.handleModalClose
 */
export const PremiumFeaturesUnlockModal = ( props ) => {
	const {
		premiumFeatureModalTitle,
		premiumFeatureDescription,
		icon,
		image,
		handleModalClose,
	} = props;

	const handleOpenPremiumLink = () => {
		// Open URL in new tab.
		window.open(
			getTrackingUrl(
				'https://simple-history.com/add-ons/premium/',
				'premium_global_modal'
			)
		);
		handleModalClose();
	};

	return (
		<Modal
			onRequestClose={ handleModalClose }
			className="sh-PremiumFeatureModal"
			__experimentalHideHeader={ true }
		>
			<div className="sh-PremiumFeatureModal-icon">
				<Icon icon={ icon } size={ 48 } />
			</div>

			<h2 className="sh-PremiumFeatureModal-title">
				{ premiumFeatureModalTitle }
			</h2>

			<p className="sh-PremiumFeatureModal-description">
				{ premiumFeatureDescription }
			</p>

			<div className="sh-PremiumFeatureModal-imageContainer">
				<img
					src={ image }
					alt={ premiumFeatureModalTitle }
					className="sh-PremiumFeatureModal-image"
				/>
			</div>

			<div className="sh-PremiumFeatureModal-actions">
				<Button
					variant="primary"
					onClick={ handleOpenPremiumLink }
					className="sh-PremiumFeatureModal-upgradeButton"
				>
					{ __( 'Upgrade to Premium now', 'simple-history' ) }
				</Button>

				<button
					className="sh-PremiumFeatureModal-later"
					onClick={ handleModalClose }
				>
					{ __( 'Maybe later', 'simple-history' ) }
				</button>
			</div>
		</Modal>
	);
};
