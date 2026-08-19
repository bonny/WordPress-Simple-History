/**
 * Dismissal handling for the Simple History "What's new" surfaces.
 *
 * Full card dismiss: collapse height over 150ms, then reveal the small
 * single-line variant. Small dismiss: fade out over 200ms.
 */
( function () {
	'use strict';

	var settings = window.simpleHistoryWhatsNew || {};

	function sendDismiss( level ) {
		if ( ! settings.ajaxurl || ! window.fetch ) {
			return;
		}

		var data = new window.FormData();
		data.append( 'action', settings.action );
		data.append( 'nonce', settings.nonce );
		data.append( 'level', level );

		window.fetch( settings.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: data,
		} );
	}

	function collapseFullCard( card ) {
		var small = document.querySelector( '.sh-WhatsNewSmall' );

		card.style.height = card.offsetHeight + 'px';
		card.setAttribute( 'aria-hidden', 'true' );

		// Force a reflow so the height transition runs from the pixel value.
		void card.offsetHeight;

		card.classList.add( 'is-dismissed' );
		card.style.height = '0px';

		window.setTimeout( function () {
			card.remove();

			if ( small ) {
				small.hidden = false;
			}
		}, 180 );
	}

	function fadeOutSmall( small ) {
		small.setAttribute( 'aria-hidden', 'true' );
		small.classList.add( 'is-dismissed' );

		window.setTimeout( function () {
			small.remove();
		}, 220 );
	}

	document.addEventListener( 'click', function ( event ) {
		if ( ! event.target.closest ) {
			return;
		}

		var fullButton = event.target.closest( '.js-sh-whats-new-dismiss-full' );

		if ( fullButton ) {
			var card = fullButton.closest( '.sh-WhatsNewCard' );

			if ( card ) {
				collapseFullCard( card );
				sendDismiss( 'full' );
			}

			return;
		}

		var smallButton = event.target.closest( '.js-sh-whats-new-dismiss-small' );

		if ( smallButton ) {
			var small = smallButton.closest( '.sh-WhatsNewSmall' );

			if ( small ) {
				fadeOutSmall( small );
				sendDismiss( 'small' );
			}
		}
	} );
} )();
