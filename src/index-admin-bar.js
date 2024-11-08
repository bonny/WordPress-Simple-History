// Entrypoint used by wp-scripts start and build.
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import AdminBarQuickView from './components/AdminBarQuickView';

domReady( () => {
	// Tmp to ease, styling, show the menu in the admin bar without the need to hover.
	setInterval( () => {
		const elm = document.querySelector( '#wp-admin-bar-simple-history' );
		if ( ! elm.classList.contains( 'hover' ) ) {
			// elm.classList.add( 'hover' );
		}
	}, 100 );

	// Find the admin bar node
	const adminBarTarget = document.getElementById(
		'wp-admin-bar-simple-history-react-root-group'
	);

	if ( adminBarTarget ) {
		createRoot( adminBarTarget ).render( <AdminBarQuickView /> );
	}
} );
