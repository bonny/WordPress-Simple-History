/* global simpleHistoryLicenseReminder */

jQuery( document ).ready( function ( $ ) {
	$( document ).on( 'click', '.sh-LicenseReminder-dismiss', function ( e ) {
		e.preventDefault();

		const $card = $( this ).closest( '.sh-LicenseReminder' );

		$.post( simpleHistoryLicenseReminder.ajaxurl, {
			action: simpleHistoryLicenseReminder.action,
			nonce: simpleHistoryLicenseReminder.nonce,
		} )
			.done( function ( response ) {
				if ( response && response.success ) {
					$card.fadeOut();
				}
			} )
			.fail( function () {
				$card.fadeOut();
			} );
	} );
} );
