import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { pin } from '@wordpress/icons';

/**
 * Menu item to promote Stick events.
 * When clicked the premium version of Simple History is promoted.
 *
 * @param {Object}   props
 * @param {Function} props.onClose          Callback to close the dropdown
 * @param {Object}   props.event            The event object
 * @param {Function} props.showPremiumModal Function to show the premium modal
 * @return {Object|null} React element or null if variant is modal
 */
export function EventStickMenuItem( { event, onClose, showPremiumModal } ) {
	// Bail if event is sticky already.
	if ( event.sticky ) {
		return null;
	}

	const handleStickClick = () => {
		showPremiumModal(
			__( 'Stick events', 'simple-history' ),
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

		onClose();
	};

	return (
		<MenuItem onClick={ handleStickClick } icon={ pin }>
			{ __( 'Stick event to top…', 'simple-history' ) }
		</MenuItem>
	);
}
