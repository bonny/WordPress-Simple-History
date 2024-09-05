// Entrypoint used by wp-scripts start and build.
import domReady from '@wordpress/dom-ready';
import { createRoot, render } from '@wordpress/element';
import EventsGui from './components/EventsGui';

domReady( () => {
	const target = document.getElementById( 'simple-history-react-root' );

	if ( target ) {
		if ( createRoot ) {
			createRoot( target ).render( <EventsGui /> );
		} else {
			render( <EventsGui />, target );
		}
	}
} );
