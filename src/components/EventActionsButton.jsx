import apiFetch from '@wordpress/api-fetch';
import {
	__experimentalConfirmDialog as ConfirmDialog,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Slot,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { moreHorizontalMobile, pin } from '@wordpress/icons';
import { EventCopyDetails, EventCopyDetailsDetailed } from './EventCopyDetails';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';

function EventUnStickMenuItem( { event, onClose } ) {
	const [ isConfirmDialogOpen, setIsConfirmDialogOpen ] = useState( false );

	// Bail if event is not sticky.
	if ( ! event.sticky ) {
		return null;
	}

	const handleUnstickClick = () => {
		setIsConfirmDialogOpen( true );
	};

	const handleUnstickClickConfirm = async () => {
		try {
			const response = await apiFetch( {
				path: `/simple-history/v1/events/${ event.id }/unstick`,
				method: 'POST',
			} );

			console.log( 'unstick success response', response );
		} catch ( error ) {
			// Silently fail - the user will see the event is still sticky
			console.error( 'unstick error', error );
		} finally {
			onClose();
		}
	};

	return (
		<>
			<MenuItem onClick={ handleUnstickClick } icon={ pin }>
				{ __( 'Unstick…', 'simple-history' ) }
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
					{ __(
						`Unstick event "${ event.message }"?`,
						'simple-history'
					) }
				</ConfirmDialog>
			) : null }
		</>
	);
}

/**
 * The button with three dots that opens a dropdown with actions for the event.
 *
 * @param {Object} props
 * @param {Object} props.event              The event object
 * @param {string} props.eventVariant       The variant of the event ('normal' or 'modal')
 * @param {string} props.eventsAdminPageURL URL to the events admin page
 * @return {Object|null} React element or null if variant is modal
 */
export function EventActionsButton( {
	event,
	eventVariant,
	eventsAdminPageURL,
} ) {
	// Don't show actions on modal events.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__actions">
			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreHorizontalMobile }
				popoverProps={ {
					placement: 'left-start',
					inline: true,
				} }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<EventDetailsMenuItem
								event={ event }
								eventVariant={ eventVariant }
								onClose={ onClose }
							/>
							<EventCopyLinkMenuItem event={ event } />
						</MenuGroup>

						<MenuGroup>
							<EventCopyDetails event={ event } />
							<EventCopyDetailsDetailed event={ event } />
						</MenuGroup>

						<MenuGroup>
							<EventViewMoreSimilarEventsMenuItem
								event={ event }
								eventsAdminPageURL={ eventsAdminPageURL }
							/>
						</MenuGroup>

						<MenuGroup>
							<EventUnStickMenuItem
								event={ event }
								onClose={ onClose }
							/>
						</MenuGroup>

						<Slot
							name="SimpleHistorySlotEventActionsMenu"
							fillProps={ {
								onClose,
								event,
								eventVariant,
							} }
						/>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
