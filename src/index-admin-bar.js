// Entrypoint for the admin bar dropdown. Loaded on EVERY frontend page
// for logged-in users, so keep this bundle as small as possible.
//
// IMPORTANT: Do NOT import from @wordpress/components or other heavy
// packages here or in any component this file imports. A single import
// like HStack or Button pulls in wp-components (~787 KB) plus 14
// transitive dependencies (~920 KB total). Use plain HTML/CSS instead.
// See EventDateCompact and EventInitiatorNameCompact for examples.

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import AdminBarQuickView from './components/AdminBarQuickView';

domReady( () => {
	// Tmp to ease, styling, show the menu in the admin bar without the need to hover.
	// setInterval( () => {
	// 	const elm = document.querySelector( '#wp-admin-bar-simple-history' );
	// 	if ( ! elm.classList.contains( 'hover' ) ) {
	// 		elm.classList.add( 'hover' );
	// 	}
	// }, 100 );

	// Find the admin bar node
	const adminBarTarget = document.getElementById(
		'wp-admin-bar-simple-history-react-root-group'
	);

	// Bail if the admin bar target is not found.
	if ( ! adminBarTarget ) {
		return;
	}

	// Bail if createRoot is not available.
	// Happens for example when using Divi theme frontend builder.
	if ( typeof createRoot !== 'function' ) {
		return;
	}

	createRoot( adminBarTarget ).render( <AdminBarQuickView /> );
} );
