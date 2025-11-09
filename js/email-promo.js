/**
 * JavaScript for the email promo card dismissal functionality.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $card = $('#simple-history-email-promo-card');

		if (!$card.length) {
			return;
		}

		// Handle "Subscribe now" button click
		$card.on('click', '.sh-EmailPromoCard-cta', function(e) {
			// Don't prevent default - let the link navigate
			// But dismiss the card in the background via AJAX
			dismissPromo();
		});

		// Handle "No thanks, not interested" button click
		$card.on('click', '.sh-EmailPromoCard-dismiss', function(e) {
			e.preventDefault();

			// Fade out the card first for better UX
			$card.fadeOut(300, function() {
				dismissPromo();
			});
		});

		/**
		 * Send AJAX request to dismiss the promo card.
		 */
		function dismissPromo() {
			$.ajax({
				url: simpleHistoryEmailPromo.ajaxUrl,
				type: 'POST',
				data: {
					action: simpleHistoryEmailPromo.action,
					nonce: simpleHistoryEmailPromo.nonce
				},
				success: function(response) {
					if (response.success) {
						// Card successfully dismissed
						console.log('Email promo card dismissed');
					} else {
						console.error('Failed to dismiss email promo card:', response.data);
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error dismissing email promo card:', error);
				}
			});
		}
	});
})(jQuery);
