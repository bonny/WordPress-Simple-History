/**
 * When the clear-log-button in admin is clicked then check that users really wants to clear the log
 */
jQuery( '.js-SimpleHistory-Settings-ClearLog' ).on( 'click', function ( e ) {
	if ( ! confirm( simpleHistoryScriptVars.settingsConfirmClearLog ) ) {
		e.preventDefault();
	}
} );

/**
 * Handle premium plugin toggle button click (dev mode only)
 */
jQuery( document ).ready( function( $ ) {
	$( '#sh-premium-toggle' ).on( 'click', function( e ) {
		e.preventDefault();

		const $button = $( this );
		const plugin = $button.data( 'plugin' );
		const nonce = $button.data( 'nonce' );

		// Disable button during request
		$button.prop( 'disabled', true );

		// Get REST API root URL - try wpApiSettings first, fallback to relative path
		let apiRoot = '/wp-json/';
		if ( typeof wpApiSettings !== 'undefined' && wpApiSettings.root ) {
			apiRoot = wpApiSettings.root;
		}

		// Make API request to toggle plugin
		$.ajax( {
			url: apiRoot + 'simple-history/v1/dev-tools/toggle-plugin',
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', nonce );
			},
			data: {
				plugin: plugin
			},
			success: function( response ) {
				// Reload the page to reflect the new plugin state
				window.location.reload();
			},
			error: function( xhr, status, error ) {
				let errorMessage = 'Failed to toggle plugin.';

				if ( xhr.responseJSON && xhr.responseJSON.message ) {
					errorMessage = xhr.responseJSON.message;
				}

				alert( errorMessage );
				$button.prop( 'disabled', false );
			}
		} );
	} );
} );
