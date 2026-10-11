/**
 * Dismissals for the "What's new" box, billing messages and the lapsed card.
 *
 * Opening the release post counts as having seen the "What's new" box, like dismissing it.
 */
( function () {
	'use strict';

	var settings = window.simpleHistoryWhatsNew || {};

	function send( kind, value ) {
		if ( ! settings.ajaxurl || ! window.fetch ) {
			return;
		}

		var data = new window.FormData();
		data.append( 'action', settings.action );
		data.append( 'nonce', settings.nonce );
		data.append( 'kind', kind );
		data.append( 'value', value || '' );

		window.fetch( settings.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} );
	}

	function getContainer( element ) {
		return element.closest
			? element.closest( '[data-sh-whats-new-kind]' )
			: null;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest
			? event.target.closest( '.js-sh-whats-new-dismiss' )
			: null;
		var container = button ? getContainer( button ) : null;

		if ( ! container ) {
			return;
		}

		send(
			container.dataset.shWhatsNewKind,
			container.dataset.shWhatsNewValue
		);
		container.remove();
	} );

	// Opening the release post counts as having seen the box.
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest
			? event.target.closest( '.js-sh-whats-new-seen' )
			: null;

		if ( link ) {
			send( 'whats_new', '' );
		}
	} );
} )();
