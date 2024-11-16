// Entrypoint used by wp-scripts start and build.
import domReady from '@wordpress/dom-ready';
import { createRoot, render } from '@wordpress/element';
import EventsGui from './components/EventsGui';
import { SlotFillProvider } from '@wordpress/components';

domReady( () => {
	const target = document.getElementById( 'simple-history-react-root' );

	if ( target ) {
		if ( createRoot ) {
			createRoot( target ).render(
				<SlotFillProvider>
					<EventsGui />
				</SlotFillProvider>
			);
		}
		//  else {
		// 	render( <EventsGui />, target );
		// }
	}
} );
