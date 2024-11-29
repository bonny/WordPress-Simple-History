import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { lockSmall, starEmpty, starFilled, unlock } from '@wordpress/icons';

export const PremiumFeatureSuffix = function ( props ) {
	const { variant, char = null } = props;

	let icon;

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
		default:
			icon = null;
	}

	return (
		<span
			style={ {
				display: 'flex',
				alignItems: 'center',
				color: 'darkgreen',
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
			{ char ? (
				<span style={ { padding: '0 0 0 .5em' } }>{ char }</span>
			) : null }
		</span>
	);
};
