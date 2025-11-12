/**
 * JavaScript for the email promo card dismissal functionality.
 *
 * @param {Object} $ jQuery object
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		const $card = $( '#simple-history-email-promo-card' );

		if ( ! $card.length ) {
			return;
		}

		// Handle "Subscribe now" button click
		$card.on( 'click', '.sh-EmailPromoCard-cta', function () {
			// Don't prevent default - let the link navigate
			// But dismiss the card in the background via AJAX
			dismissPromo();
		} );

		// Handle "No thanks, not interested" button click
		$card.on( 'click', '.sh-EmailPromoCard-dismiss', function ( e ) {
			e.preventDefault();

			// Fade out the card first for better UX
			$card.fadeOut( 300, function () {
				dismissPromo();
			} );
		} );

		/**
		 * Send AJAX request to dismiss the promo card.
		 */
		function dismissPromo() {
			$.ajax( {
				url: window.simpleHistoryEmailPromo.ajaxUrl,
				type: 'POST',
				data: {
					action: window.simpleHistoryEmailPromo.action,
					nonce: window.simpleHistoryEmailPromo.nonce,
				},
				success( response ) {
					if ( response.success ) {
						// Card successfully dismissed
						// eslint-disable-next-line no-console
						// console.log( 'Email promo card dismissed' );
					} else {
						// eslint-disable-next-line no-console
						console.error(
							'Failed to dismiss email promo card:',
							response.data
						);
					}
				},
				error( xhr, status, error ) {
					// eslint-disable-next-line no-console
					console.error(
						'AJAX error dismissing email promo card:',
						error
					);
				},
			} );
		}
	} );
} )( jQuery );
