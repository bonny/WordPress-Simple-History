/**
 * When the clear-log-button in admin is clicked then check that users really wants to clear the log
 */
jQuery( '.js-SimpleHistory-Settings-ClearLog' ).on( 'click', function ( e ) {
	if ( ! confirm( simpleHistoryScriptVars.settingsConfirmClearLog ) ) {
		e.preventDefault();
	}
} );
