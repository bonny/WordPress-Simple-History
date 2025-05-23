import {
	Button,
	__experimentalHStack as HStack,
	Icon,
	Modal,
	__experimentalDivider as Divider,
	__experimentalSpacer as Spacer,
} from '@wordpress/components';
import { SVG } from '@wordpress/primitives';
import CheckboxImage from '../../css/icons/check_circle_24dp_3F9349_FILL0_wght400_GRAD0_opsz24.svg';
import { __ } from '@wordpress/i18n';

// Icon = Workspace Premium
// https://fonts.google.com/icons?selected=Material+Symbols+Outlined:workspace_premium:FILL@0;wght@400;GRAD@0;opsz@24&icon.query=medal&icon.size=24&icon.color=%235f6368
// workspace_premium_24dp_5F6368_FILL0_wght400_GRAD0_opsz24.svg
const modalIcon = (
	<SVG
		xmlns="http://www.w3.org/2000/svg"
		height="24px"
		viewBox="0 -960 960 960"
		width="24px"
		fill="#5f6368"
	>
		<path d="m387-412 35-114-92-74h114l36-112 36 112h114l-93 74 35 114-92-71-93 71ZM240-40v-309q-38-42-59-96t-21-115q0-134 93-227t227-93q134 0 227 93t93 227q0 61-21 115t-59 96v309l-240-80-240 80Zm240-280q100 0 170-70t70-170q0-100-70-170t-170-70q-100 0-170 70t-70 170q0 100 70 170t170 70ZM320-159l160-41 160 41v-124q-35 20-75.5 31.5T480-240q-44 0-84.5-11.5T320-283v124Zm160-62Z" />
	</SVG>
);

/**
 * Modal that is shown when you click on a premium feature.
 *
 * @param {Object}   props
 * @param {string}   props.premiumFeatureModalTitle
 * @param {string}   props.premiumFeatureDescription
 * @param {Function} props.handleModalClose
 */
export const PremiumFeaturesUnlockModal = ( props ) => {
	const {
		premiumFeatureModalTitle,
		premiumFeatureDescription,
		handleModalClose,
	} = props;

	const handleOpenPremiumLink = () => {
		// Open URL in new tab.
		window.open(
			'https://simple-history.com/add-ons/premium/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=premium-feature-modal'
		);
		handleModalClose();
	};

	const liStyles = {
		backgroundImage: `url(${ CheckboxImage })`,
		backgroundSize: '1.5rem',
		lineHeight: '1.5rem',
		backgroundRepeat: 'no-repeat',
		backgroundPosition: 'center left',
		paddingLeft: '30px',
	};

	return (
		<Modal
			icon={ <Icon icon={ modalIcon } /> }
			title={ premiumFeatureModalTitle }
			onRequestClose={ handleModalClose }
		>
			{ premiumFeatureDescription }

			<Divider
				margin={ 5 }
				style={ {
					color: 'var(--sh-color-gray)',
				} }
			/>

			<p
				style={ {
					fontWeight: '600',
					fontSize: '1rem',
				} }
			>
				{ __(
					'Simple History Premium also includes:',
					'simple-history'
				) }
			</p>

			<ul
				style={ {
					listStyle: 'disc',
					listStyleType: 'none',
				} }
			>
				<li style={ liStyles }>
					{ __(
						'More details on Stats & Summaries page',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Add events manually using a GUI',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __( 'Stealth Mode GUI', 'simple-history' ) }
				</li>
				<li style={ liStyles }>
					{ __( 'Export as CSV and JSON', 'simple-history' ) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Option to set number of days to keep the log',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Limit number of failed login attempts that are logged',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Control how to store IP Addresses (anonymized or not)',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Show a map of where a failed login attempt happened',
						'simple-history'
					) }
				</li>
				<li style={ liStyles }>
					{ __( 'Control what messages to log', 'simple-history' ) }
				</li>
				<li style={ liStyles }>
					{ __( 'Remove premium upgrade banners', 'simple-history' ) }
				</li>
				<li style={ liStyles }>
					{ __(
						'Remove review and donate banners',
						'simple-history'
					) }
				</li>
			</ul>

			<Spacer margin={ 10 } />

			<HStack spacing={ 3 }>
				<Button
					variant="primary"
					onClick={ handleOpenPremiumLink }
					style={ {
						backgroundColor: 'var(--sh-color-green)',
					} }
				>
					{ __( 'Upgrade to premium', 'simple-history' ) }
				</Button>

				<Button variant="tertiary" onClick={ handleModalClose }>
					{ __( 'Maybe later', 'simple-history' ) }
				</Button>
			</HStack>
		</Modal>
	);
};
