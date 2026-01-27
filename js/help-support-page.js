/**
 * JavaScript for the Simple History Help & Support page.
 * Handles REST API health check, support info gathering, and clipboard copy.
 */
( function () {
	'use strict';

	/** Delay in milliseconds before clearing copy feedback message. */
	const COPY_FEEDBACK_DELAY_MS = 3000;

	/**
	 * Update status bar with connection status and stats.
	 *
	 * @param {Object} data Data from support-info endpoint.
	 */
	function updateStatusBar( data ) {
		const statusBar = document.getElementById( 'sh-status-bar-status' );
		if ( ! statusBar ) {
			return;
		}

		const info = data.info;
		const version = info.simple_history.version;
		const totalEvents = info.simple_history.total_events;
		const retention = info.simple_history.retention_days;

		// Build status bar content.
		let html =
			'<span class="sh-StatusBar-version">' +
			'<span class="dashicons dashicons-yes-alt"></span> ' +
			'Simple History ' +
			escapeHtml( version ) +
			'</span>';

		html +=
			'<span class="sh-StatusBar-stats">' +
			escapeHtml(
				formatNumber( totalEvents ) +
					' events logged \u2022 ' +
					retention +
					' retention'
			) +
			'</span>';

		statusBar.innerHTML = html;
		statusBar.classList.add( 'sh-StatusBar-connected' );
	}

	/**
	 * Show connection error in status bar.
	 *
	 * @param {string} errorMessage Error message to display.
	 */
	function showStatusBarError( errorMessage ) {
		const statusBar = document.getElementById( 'sh-status-bar-status' );
		if ( ! statusBar ) {
			return;
		}

		statusBar.innerHTML =
			'<span class="sh-StatusBar-error">' +
			'<span class="dashicons dashicons-warning"></span> ' +
			escapeHtml( window.simpleHistoryHelpPage.i18n.apiError ) +
			' ' +
			escapeHtml( errorMessage ) +
			'</span>';
		statusBar.classList.add( 'sh-StatusBar-hasError' );
	}

	/**
	 * Display warnings as WordPress admin notices.
	 *
	 * @param {Array} warnings Array of warning messages.
	 */
	function displayWarnings( warnings ) {
		const container = document.getElementById( 'sh-warnings-container' );
		if ( ! container ) {
			return;
		}

		// Clear existing warnings.
		container.innerHTML = '';

		if ( ! warnings || warnings.length === 0 ) {
			return;
		}

		warnings.forEach( function ( warning ) {
			const notice = document.createElement( 'div' );
			notice.className = 'notice notice-warning';
			notice.innerHTML =
				'<p><span class="dashicons dashicons-warning" style="color: #dba617; margin-right: 5px;"></span>' +
				escapeHtml( warning ) +
				'</p>';
			container.appendChild( notice );
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

		// Set loading state.
		textarea.value = window.simpleHistoryHelpPage.i18n.gathering;
		button.disabled = true;
		spinner.classList.add( 'is-active' );

		fetch( window.simpleHistoryHelpPage.restUrl, {
			method: 'GET',
			headers: {
				'X-WP-Nonce': window.simpleHistoryHelpPage.nonce,
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
				button.textContent = window.simpleHistoryHelpPage.i18n.refresh;

				// Display warnings as admin notices.
				if ( data.warnings ) {
					displayWarnings( data.warnings );
				}

				// Update status bar with stats.
				updateStatusBar( data );
			} )
			.catch( function ( error ) {
				textarea.value =
					window.simpleHistoryHelpPage.i18n.gatherError +
					' ' +
					error.message;

				// Show error in status bar.
				showStatusBarError( error.message );
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
					showCopyFeedback( statusEl, true );
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
			showCopyFeedback( statusEl, success );
		} catch ( err ) {
			showCopyFeedback( statusEl, false );
		}

		// Clear selection using ownerDocument to avoid global getSelection.
		const selection = textarea.ownerDocument.defaultView.getSelection();
		if ( selection ) {
			selection.removeAllRanges();
		}
	}

	/**
	 * Show copy feedback with checkmark icon next to button.
	 *
	 * @param {HTMLElement} statusEl The status element.
	 * @param {boolean}     success  Whether copy was successful.
	 */
	function showCopyFeedback( statusEl, success ) {
		if ( success ) {
			statusEl.innerHTML =
				'<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ' +
				escapeHtml( window.simpleHistoryHelpPage.i18n.copied );
			statusEl.style.color = '#00a32a';
		} else {
			statusEl.innerHTML =
				'<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' +
				escapeHtml( window.simpleHistoryHelpPage.i18n.copyError );
			statusEl.style.color = '#d63638';
		}

		// Clear status after a short delay.
		setTimeout( function () {
			statusEl.innerHTML = '';
		}, COPY_FEEDBACK_DELAY_MS );
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
	 * Format number with locale-aware thousands separator.
	 *
	 * @param {number} num The number to format.
	 * @return {string} Formatted number string.
	 */
	function formatNumber( num ) {
		return new Intl.NumberFormat().format( num );
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		// Automatically gather support info on page load (which also updates status bar).
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
