// Entrypoint used by wp-scripts start and build.
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import SimpleHistoryMenu from './components/SimpleHistoryMenu';

domReady( () => {
	// Find the admin bar node
	const adminBarTarget = document.getElementById(
		'wp-admin-bar-simple-history-react-root-group'
	);

	if ( adminBarTarget ) {
		createRoot( adminBarTarget ).render( <SimpleHistoryMenu /> );
	}
} );
