/**
 * When the clear-log-button in admin is clicked then check that users really wants to clear the log
 */
jQuery( '.js-SimpleHistory-Settings-ClearLog' ).on( 'click', function ( e ) {
	if ( ! confirm( simpleHistoryScriptVars.settingsConfirmClearLog ) ) {
		e.preventDefault();
	}
} );

/**
 * Handle dev mode toggle badge clicks (premium, experimental, etc.)
 */
jQuery( document ).ready( function ( $ ) {
	$( '.sh-PageHeader-badge--toggle' ).on( 'click', function ( e ) {
		e.preventDefault();

		const $button = $( this );
		const endpoint = $button.data( 'endpoint' );

		// Disable button during request.
		$button.prop( 'disabled', true );

		// Collect any extra data- attributes (e.g. data-plugin).
		const data = {};
		$.each( $button.data(), function ( key, value ) {
			if ( key !== 'endpoint' ) {
				data[ key ] = value;
			}
		} );

		wp.apiFetch( {
			path: '/simple-history/v1/dev-tools/' + endpoint,
			method: 'POST',
			data: data,
		} )
			.then( function () {
				window.location.reload();
			} )
			.catch( function ( error ) {
				alert( error.message || 'Toggle failed.' );
				$button.prop( 'disabled', false );
			} );
	} );
} );
