import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { info } from '@wordpress/icons';
import { navigateToEventPermalink } from '../functions';

export function EventDetailsMenuItem( { event, onClose } ) {
	return (
		<MenuItem
			icon={ info }
			iconPosition="left"
			onClick={ () => {
				navigateToEventPermalink( { event } );
				onClose();
			} }
		>
			{ __( 'View event details', 'simple-history' ) }
		</MenuItem>
	);
} 