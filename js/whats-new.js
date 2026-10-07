/**
 * Dismissals for the "What's new" box, billing messages and the lapsed card.
 *
 * Opening the "What's new" box counts as having seen it, like dismissing it.
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

	// The toggle event doesn't bubble, so listen in the capture phase.
	document.addEventListener(
		'toggle',
		function ( event ) {
			var details = event.target;

			if (
				! details.open ||
				! details.classList ||
				! details.classList.contains( 'sh-WhatsNew-details' )
			) {
				return;
			}

			var container = getContainer( details );

			if ( container && ! container.dataset.shWhatsNewOpened ) {
				container.dataset.shWhatsNewOpened = '1';
				send( 'whats_new', '' );
			}
		},
		true
	);
} )();
