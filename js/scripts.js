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
		const nonce = $button.data( 'nonce' );
		const endpoint = $button.data( 'endpoint' );

		// Disable button during request.
		$button.prop( 'disabled', true );

		// Get REST API root URL.
		let apiRoot = '/wp-json/';
		if ( typeof wpApiSettings !== 'undefined' && wpApiSettings.root ) {
			apiRoot = wpApiSettings.root;
		}

		// Collect any extra data- attributes (e.g. data-plugin).
		const data = {};
		$.each( $button.data(), function ( key, value ) {
			if ( key !== 'nonce' && key !== 'endpoint' ) {
				data[ key ] = value;
			}
		} );

		$.ajax( {
			url: apiRoot + 'simple-history/v1/dev-tools/' + endpoint,
			method: 'POST',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			},
			data: data,
			success: function () {
				window.location.reload();
			},
			error: function ( xhr ) {
				let errorMessage = 'Toggle failed.';

				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					errorMessage = xhr.responseJSON.message;
				}

				alert( errorMessage );
				$button.prop( 'disabled', false );
			},
		} );
	} );
} );
