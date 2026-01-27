/**
 * JavaScript for the Simple History Debug page.
 * Handles REST API health check, support info gathering, and clipboard copy.
 */
( function () {
	'use strict';

	/**
	 * Check REST API health on page load.
	 */
	function checkRestApiHealth() {
		const statusContainer = document.getElementById( 'sh-rest-api-status' );
		if ( ! statusContainer ) {
			return;
		}

		fetch( window.simpleHistoryDebugPage.healthUrl, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': window.simpleHistoryDebugPage.nonce,
			},
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error(
						response.status + ' ' + response.statusText
					);
				}
				return response.json();
			} )
			.then( function ( data ) {
				if ( data.status === 'ok' ) {
					statusContainer.innerHTML =
						'<span class="sh-DebugPage-restApiStatus-success">' +
						'<span class="dashicons dashicons-yes-alt"></span> ' +
						escapeHtml( window.simpleHistoryDebugPage.i18n.apiOk ) +
						'</span>';
				} else {
					throw new Error( 'Unexpected response' );
				}
			} )
			.catch( function ( error ) {
				statusContainer.innerHTML =
					'<span class="sh-DebugPage-restApiStatus-error">' +
					'<span class="dashicons dashicons-warning"></span> ' +
					escapeHtml( window.simpleHistoryDebugPage.i18n.apiError ) +
					' ' +
					escapeHtml( error.message ) +
					'</span>';
			} );
	}

	/**
	 * Gather support information when button is clicked or on page load.
	 */
	function gatherSupportInfo() {
		const button = document.getElementById( 'sh-gather-support-info' );
		const spinner = document.getElementById(
			'sh-gather-support-info-spinner'
		);
		const container = document.getElementById(
			'sh-support-info-container'
		);
		const textarea = document.getElementById( 'sh-support-info-textarea' );

		if ( ! button || ! spinner || ! container || ! textarea ) {
			return;
		}

		// Show container immediately with loading state.
		container.style.display = 'block';
		textarea.value = window.simpleHistoryDebugPage.i18n.gathering;
		button.disabled = true;
		spinner.classList.add( 'is-active' );

		fetch( window.simpleHistoryDebugPage.restUrl, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': window.simpleHistoryDebugPage.nonce,
			},
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error(
						response.status + ' ' + response.statusText
					);
				}
				return response.json();
			} )
			.then( function ( data ) {
				textarea.value = data.plain_text;
				button.textContent =
					window.simpleHistoryDebugPage.i18n.reloadData;
			} )
			.catch( function ( error ) {
				textarea.value =
					window.simpleHistoryDebugPage.i18n.gatherError +
					' ' +
					error.message;
			} )
			.finally( function () {
				button.disabled = false;
				spinner.classList.remove( 'is-active' );
			} );
	}

	/**
	 * Copy support info to clipboard.
	 */
	function copyToClipboard() {
		const textarea = document.getElementById( 'sh-support-info-textarea' );
		const statusEl = document.getElementById( 'sh-copy-status' );

		if ( ! textarea || ! statusEl ) {
			return;
		}

		// Try using modern Clipboard API first.
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard
				.writeText( textarea.value )
				.then( function () {
					showCopyStatus( statusEl, true );
				} )
				.catch( function () {
					// Fallback to legacy method.
					legacyCopy( textarea, statusEl );
				} );
		} else {
			// Fallback for older browsers.
			legacyCopy( textarea, statusEl );
		}
	}

	/**
	 * Legacy copy method using selection and execCommand.
	 *
	 * @param {HTMLTextAreaElement} textarea The textarea element.
	 * @param {HTMLElement}         statusEl The status element.
	 */
	function legacyCopy( textarea, statusEl ) {
		textarea.select();
		textarea.setSelectionRange( 0, 99999 );

		try {
			const success = document.execCommand( 'copy' );
			showCopyStatus( statusEl, success );
		} catch ( err ) {
			showCopyStatus( statusEl, false );
		}

		// Clear selection.
		window.getSelection().removeAllRanges();
	}

	/**
	 * Show copy status message.
	 *
	 * @param {HTMLElement} statusEl The status element.
	 * @param {boolean}     success  Whether copy was successful.
	 */
	function showCopyStatus( statusEl, success ) {
		if ( success ) {
			statusEl.textContent = window.simpleHistoryDebugPage.i18n.copied;
			statusEl.className =
				'sh-DebugPage-copyStatus sh-DebugPage-copyStatus--success';
		} else {
			statusEl.textContent = window.simpleHistoryDebugPage.i18n.copyError;
			statusEl.className =
				'sh-DebugPage-copyStatus sh-DebugPage-copyStatus--error';
		}

		// Clear status after a short delay.
		setTimeout( function () {
			statusEl.textContent = '';
			statusEl.className = 'sh-DebugPage-copyStatus';
		}, 2000 );
	}

	/**
	 * Escape HTML entities to prevent XSS.
	 *
	 * @param {string} str The string to escape.
	 * @return {string} The escaped string.
	 */
	function escapeHtml( str ) {
		const div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		// Check REST API health on page load.
		checkRestApiHealth();

		// Automatically gather support info on page load.
		gatherSupportInfo();

		// Gather support info button (for refresh).
		const gatherButton = document.getElementById(
			'sh-gather-support-info'
		);
		if ( gatherButton ) {
			gatherButton.addEventListener( 'click', gatherSupportInfo );
		}

		// Copy to clipboard button.
		const copyButton = document.getElementById( 'sh-copy-support-info' );
		if ( copyButton ) {
			copyButton.addEventListener( 'click', copyToClipboard );
		}
	}

	// Run init when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
