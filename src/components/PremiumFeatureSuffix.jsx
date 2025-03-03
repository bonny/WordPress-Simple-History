import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { lockSmall, starEmpty, starFilled, unlock } from '@wordpress/icons';
import verifiedSvgImage from '../../css/icons/verified_FILL0_wght400_GRAD0_opsz48.svg';

export const PremiumFeatureSuffix = function ( props ) {
	const { variant, char = null } = props;

	let icon;
	let svgIcon;

	switch ( variant ) {
		case 'unlocked':
			icon = starFilled;
			break;
		case 'unlocked2':
			icon = starEmpty;
			break;
		case 'unlocked3':
			icon = unlock;
			break;
		case 'locked':
			icon = lockSmall;
			break;
		case 'verified':
			svgIcon = verifiedSvgImage;
			break;
		default:
			icon = null;
	}

	return (
		<span className="sh-PremiumFeatureBadge">
			{ __( 'Premium', 'simple-history' ) }

			{ icon ? <Icon icon={ icon } size={ 20 } /> : null }

			{ svgIcon ? (
				<img
					src={ svgIcon }
					alt=""
					style={ { width: '20px', padding: '0 0 0 .5em' } }
				/>
			) : null }

			{ char ? (
				<span style={ { padding: '0 0 0 .5em' } }>{ char }</span>
			) : null }
		</span>
	);
};
