import apiFetch from '@wordpress/api-fetch';
import {
	__experimentalConfirmDialog as ConfirmDialog,
	MenuItem,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { pin } from '@wordpress/icons';

/**
 * Menu item to unstick a sticky event.
 *
 * @param {Object}   props
 * @param {Object}   props.event                The event object
 * @param {Function} props.onClose              Callback to close the dropdown
 * @param {boolean}  props.userCanManageOptions Whether the user can manage options (is admin)
 */
export function EventUnstickMenuItem( {
	event,
	onClose,
	userCanManageOptions,
} ) {
	const [ isConfirmDialogOpen, setIsConfirmDialogOpen ] = useState( false );

	// Bail if event is not sticky or user is not an admin.
	if ( ! event.sticky || ! userCanManageOptions ) {
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
