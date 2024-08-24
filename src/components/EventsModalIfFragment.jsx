import { useEffect, useState } from '@wordpress/element';
import { EventInfoModal } from './EventInfoModal';
import { useURLFrament } from '../functions.js';

/**
 * Opens a modal with event details when URL contains a fragment.
 *
 * Show a modal when the URL contains a fragment.
 * Removes the fragment from the URL after the modal is closed.
 */
export function EventsModalIfFragment() {
	const fragment = useURLFrament();
	const [ showModal, setShowModal ] = useState( false );
	const [ matchedEventId, setMatchedEventId ] = useState( null );

	// Open modal with info when URL changes and contains fragment.
	useEffect( () => {
		// Match only some fragments, that begins with
		// '#simple-history/event/'
		const matchedEventFragment = fragment.match(
			/^#simple-history\/event\/(\d+)/
		);

		if ( matchedEventFragment === null ) {
			setShowModal( false );
			return;
		}

		setMatchedEventId( parseInt( matchedEventFragment[ 1 ], 10 ) );
		setShowModal( true );
	}, [ fragment ] );

	const closeModal = () => {
		setShowModal( false );
		window.location.hash = '';
	};

	if ( showModal ) {
		return (
			<EventInfoModal
				eventId={ matchedEventId }
				closeModal={ closeModal }
			/>
		);
	}

	return null;
}
