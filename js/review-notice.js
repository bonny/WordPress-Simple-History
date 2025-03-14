/* global simpleHistoryReviewNotice */

jQuery( document ).ready( function ( $ ) {
	const $dismissButton = $( '.simple-history-review-notice-dismiss-button' );

	// Handle click on "Maybe Later" button.
	$dismissButton.on( 'click', function ( e ) {
		e.preventDefault();
		dismissNotice();
	} );

	/**
	 * Send AJAX request to dismiss the notice.
	 */
	function dismissNotice() {
		const $notice = $dismissButton.closest( '.notice' );

		$.post( simpleHistoryReviewNotice.ajaxurl, {
			action: simpleHistoryReviewNotice.action,
			nonce: simpleHistoryReviewNotice.nonce,
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$notice.fadeOut();
				}
			} )
			.fail( function () {
				// If AJAX call fails, at least hide the notice for current page view.
				$notice.fadeOut();
			} );
	}
} );
