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
		<span
			style={ {
				display: 'flex',
				alignItems: 'center',
				color: '#fff',
				backgroundColor: 'var(--sh-color-green-2)',
				textTransform: 'uppercase',
				fontSize: '0.8em',
				border: '1px solid darkgreen',
				borderRadius: '4px',
				padding: '0 0.5em',
				opacity: '0.75',
				lineHeight: 1,
				margin: '0 0 0 1em',
			} }
		>
			<span
				style={ {
					padding: '.5em 0',
				} }
			>
				{ __( 'Premium', 'simple-history' ) }
			</span>

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
