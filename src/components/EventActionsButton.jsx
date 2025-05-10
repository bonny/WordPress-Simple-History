import apiFetch from '@wordpress/api-fetch';
import {
	__experimentalConfirmDialog as ConfirmDialog,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Slot,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { moreHorizontalMobile, pin } from '@wordpress/icons';
import { EventCopyDetails, EventCopyDetailsDetailed } from './EventCopyDetails';
import { EventCopyLinkMenuItem } from './EventCopyLinkMenuItem';
import { EventDetailsMenuItem } from './EventDetailsMenuItem';
import { EventViewMoreSimilarEventsMenuItem } from './EventViewMoreSimilarEventsMenuItem';
import { PremiumFeaturesUnlockModal } from './PremiumFeaturesUnlockModal';
/**
 * Menu item to promote Stick events.
 * When clicked the premium version of Simple History is promoted.
 *
 * @param {Object}   props
 * @param {Function} props.onClose Callback to close the dropdown
 * @param {Object}   props.event   The event object
 * @return {Object|null} React element or null if variant is modal
 */
function EventStickMenuItem( {
	event,
	onClose,
	setShowPremiumFeaturesUnlockModal,
	setPremiumModalTitle,
	setPremiumModalDescription,
} ) {
	// Bail if event is sticky already.
	if ( event.sticky ) {
		return null;
	}

	const handleStickClick = () => {
		setPremiumModalTitle( __( 'Stick events', 'simple-history' ) );
		setPremiumModalDescription(
			<>
				<p
					style={ {
						backgroundColor: 'var(--sh-color-yellow)',
						fontSize: 'var(--sh-font-size-large)',
						padding: '1rem 2rem',
					} }
				>
					<strong>Sticking events</strong> using the GUI is a premium
					feature.
				</p>

				<p
					style={ {
						fontSize: 'var(--sh-font-size-large)',
					} }
				>
					This feature lets you stick any event to the top of the
					list.
				</p>
			</>
		);
		setShowPremiumFeaturesUnlockModal( true );

		onClose();
	};

	const handleModalClose = () => {
		setShowPremiumFeaturesUnlockModal( false );
	};

	return (
		<MenuItem onClick={ handleStickClick } icon={ pin }>
			{ __( 'Stick…', 'simple-history' ) }
		</MenuItem>
	);
}
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
					{ sprintf(
						/* translators: %s: The message of the event. */
						__( 'Unstick event “%s”?', 'simple-history' ),
						event.message
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
	const [
		showPremiumFeaturesUnlockModal,
		setShowPremiumFeaturesUnlockModal,
	] = useState( false );

	const [ premiumModalTitle, setPremiumModalTitle ] = useState(
		__( 'Stick events', 'simple-history' )
	);

	const [ premiumModalDescription, setPremiumModalDescription ] = useState(
		__( 'Stick events to the top of the list.', 'simple-history' )
	);

	const handlePremiumModalClose = () => {
		setShowPremiumFeaturesUnlockModal( false );
	};

	// Don't show actions on modal events.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__actions">
			{ showPremiumFeaturesUnlockModal ? (
				<PremiumFeaturesUnlockModal
					premiumFeatureModalTitle={ premiumModalTitle }
					premiumFeatureDescription={ premiumModalDescription }
					handleModalClose={ handlePremiumModalClose }
				/>
			) : null }

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
							<EventStickMenuItem
								event={ event }
								onClose={ onClose }
								setShowPremiumFeaturesUnlockModal={
									setShowPremiumFeaturesUnlockModal
								}
								setPremiumModalTitle={ setPremiumModalTitle }
								setPremiumModalDescription={
									setPremiumModalDescription
								}
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
