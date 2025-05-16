import apiFetch from '@wordpress/api-fetch';
import {
	__experimentalConfirmDialog as ConfirmDialog,
	MenuItem,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { pin } from '@wordpress/icons';
import { useUserHasCapability } from '../hooks/useUserHasCapability';

export function EventUnstickMenuItem( { event, onClose } ) {
	const [ isConfirmDialogOpen, setIsConfirmDialogOpen ] = useState( false );
	const canManageOptions = useUserHasCapability( 'manage_options' );

	// Bail if event is not sticky or user is not an admin.
	if ( ! event.sticky || ! canManageOptions ) {
		return null;
	}

	const handleUnstickClick = () => {
		setIsConfirmDialogOpen( true );
	};

	const handleUnstickClickConfirm = async () => {
		try {
			await apiFetch( {
				path: `/simple-history/v1/events/${ event.id }/unstick`,
				method: 'POST',
			} );
		} catch ( error ) {
			// Silently fail - the user will see the event is still sticky.
		} finally {
			onClose();
		}
	};

	return (
		<>
			<MenuItem onClick={ handleUnstickClick } icon={ pin }>
				{ __( 'Unstick event…', 'simple-history' ) }
			</MenuItem>

			{ isConfirmDialogOpen ? (
				<ConfirmDialog
					cancelButtonText={ __( 'Nope', 'simple-history' ) }
					confirmButtonText={ __(
						'Yes, unstick it',
						'simple-history'
					) }
					onConfirm={ handleUnstickClickConfirm }
					onCancel={ () => setIsConfirmDialogOpen( false ) }
				>
					{ sprintf(
						/* translators: %s: The message of the event. */
						__( 'Unstick event "%s"?', 'simple-history' ),
						event.message
					) }
				</ConfirmDialog>
			) : null }
		</>
	);
}
