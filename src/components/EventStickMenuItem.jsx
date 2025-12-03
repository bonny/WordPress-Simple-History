import { MenuItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { pin } from '@wordpress/icons';
import { usePremiumFeaturesModal } from './PremiumFeaturesModalContext';
import stickEventsFeatureImage from '../images/premium-feature-stick-events.svg';

/**
 * Menu item to promote Stick events.
 * When clicked the premium version of Simple History is promoted.
 *
 * @param {Object}   props
 * @param {Function} props.onClose         Callback to close the dropdown
 * @param {Object}   props.event           The event object
 * @param {boolean}  props.hasPremiumAddOn Whether the premium add-on is installed
 * @return {Object|null} React element or null if variant is modal
 */
export function EventStickMenuItem( { event, onClose, hasPremiumAddOn } ) {
	const { showModal } = usePremiumFeaturesModal();
	// Bail if premium add-on is installed.
	if ( hasPremiumAddOn ) {
		return null;
	}

	// Bail if event is sticky already.
	if ( event.sticky ) {
		return null;
	}

	const handleStickClick = () => {
		showModal(
			__( 'Unlock Sticky Events', 'simple-history' ),
			__(
				'Pin important events to the top of your log. Great for keeping critical changes visible, like security incidents or major updates.',
				'simple-history'
			),
			pin,
			stickEventsFeatureImage
		);

		onClose();
	};

	return (
		<MenuItem onClick={ handleStickClick } icon={ pin }>
			{ __( 'Stick event to top…', 'simple-history' ) }
		</MenuItem>
	);
}
