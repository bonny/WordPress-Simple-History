/* global simpleHistoryReviewNotice */

jQuery( document ).ready( function ( $ ) {
	// Handle click on "Maybe Later" button.
	$( '.simple-history-review-notice .dismiss-review-notice' ).on(
		'click',
		function ( e ) {
			e.preventDefault();
			dismissNotice();
		}
	);

	// Handle click on WordPress native dismiss button.
	$( document ).on(
		'click',
		'.simple-history-review-notice .notice-dismiss',
		function () {
			dismissNotice();
		}
	);

	/**
	 * Send AJAX request to dismiss the notice.
	 */
	function dismissNotice() {
		$.post( simpleHistoryReviewNotice.ajaxurl, {
			action: simpleHistoryReviewNotice.action,
			nonce: simpleHistoryReviewNotice.nonce,
		} )
			.done( function ( response ) {
				if ( response.success ) {
					$( '.simple-history-review-notice' ).fadeOut();
				}
			} )
			.fail( function () {
				// If AJAX call fails, at least hide the notice for current page view.
				$( '.simple-history-review-notice' ).fadeOut();
			} );
	}
} );
