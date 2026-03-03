import { MenuItem } from '@wordpress/components';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { capturePhoto } from '@wordpress/icons';
import { snapdom } from '@zumer/snapdom';

const CARD_MAX_WIDTH = 560;

/**
 * Get the Simple History logo URL from the existing page header image.
 *
 * @return {string} The logo image src URL, or empty string if not found.
 */
function getLogoUrl() {
	const logoImg = document.querySelector( 'img.sh-PageHeader-logo' );
	return logoImg ? logoImg.src : '';
}

/**
 * Trigger a file download from a Blob.
 *
 * @param {Blob}   blob
 * @param {string} filename
 */
function downloadBlob( blob, filename ) {
	const url = URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.appendChild( a );
	a.click();
	document.body.removeChild( a );
	URL.revokeObjectURL( url );
}

/**
 * Temporarily modify the live event element for screenshot capture.
 * Wraps the <li> in a card-styled container with constrained width,
 * rounded corners, shadow, and a branded footer.
 *
 * Returns a restore function that undoes all DOM changes.
 *
 * @param {HTMLElement} logItem The .SimpleHistoryLogitem element.
 * @param {Object}      event   The event object.
 * @return {{ wrapper: HTMLElement, restore: Function }} The wrapper to capture and a restore function.
 */
function prepareForCapture( logItem, event ) {
	const restoreFns = [];

	// Wrap the <li> in a card container.
	const wrapper = document.createElement( 'div' );
	Object.assign( wrapper.style, {
		width: CARD_MAX_WIDTH + 'px',
		backgroundColor: '#fff',
		borderRadius: '12px',
		boxShadow: '0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06)',
		overflow: 'hidden',
	} );

	// Insert wrapper before the <li>, then move <li> into it.
	const parent = logItem.parentNode;
	const nextSibling = logItem.nextSibling;
	parent.insertBefore( wrapper, logItem );
	wrapper.appendChild( logItem );
	restoreFns.push( () => {
		parent.insertBefore( logItem, nextSibling );
		wrapper.remove();
	} );

	// Add branded footer bar matching the Simple History banner aesthetic:
	// cream background, logo wordmark PNG, minimal and editorial.
	const footer = document.createElement( 'div' );
	Object.assign( footer.style, {
		backgroundColor: '#fcf9ec',
		padding: '14px 20px',
		display: 'flex',
		alignItems: 'center',
		gap: '10px',
	} );

	const logoUrl = getLogoUrl();
	if ( logoUrl ) {
		const logoImg = document.createElement( 'img' );
		logoImg.src = logoUrl;
		logoImg.alt = 'Simple History';
		Object.assign( logoImg.style, {
			height: '18px',
			width: 'auto',
		} );
		footer.appendChild( logoImg );
	}

	const taglineEl = document.createElement( 'span' );
	Object.assign( taglineEl.style, {
		color: '#646970',
		fontSize: '11px',
		fontWeight: '400',
		fontFamily:
			"-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif",
	} );
	taglineEl.textContent = 'WordPress Activity Log';
	footer.appendChild( taglineEl );

	wrapper.appendChild( footer );
	restoreFns.push( () => footer.remove() );

	// Hide elements that shouldn't appear in the screenshot.
	const selectorsToHide = [
		'.SimpleHistoryLogitem__actions',
		'.SimpleHistoryEventSeparator',
		'.SimpleHistoryLogitem__actionLinks',
	];
	selectorsToHide.forEach( ( selector ) => {
		logItem.querySelectorAll( selector ).forEach( ( el ) => {
			const prev = el.style.display;
			el.style.display = 'none';
			restoreFns.push( () => {
				el.style.display = prev;
			} );
		} );
	} );

	// Replace relative date with full absolute date.
	const timeEl = logItem.querySelector(
		'.SimpleHistoryLogitem__when__liveRelative'
	);
	if ( timeEl && event.date_gmt ) {
		const originalText = timeEl.textContent;
		const dateSettings = getDateSettings();
		const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
		timeEl.textContent = dateI18n(
			dateSettings.formats.datetimeAbbreviated,
			event.date_gmt + '+0000',
			tz
		);
		restoreFns.push( () => {
			timeEl.textContent = originalText;
		} );
	}

	const restore = () => {
		// Restore in reverse order.
		for ( let i = restoreFns.length - 1; i >= 0; i-- ) {
			restoreFns[ i ]();
		}
	};

	return { wrapper, restore };
}

/**
 * Build a descriptive filename slug from the event.
 *
 * @param {Object} event The event object.
 * @return {string} Filename without extension.
 */
function buildFilename( event ) {
	const parts = [];

	// Add user display name if available.
	const userName = event.initiator_data?.user_display_name;
	if ( userName ) {
		parts.push( userName );
	}

	// Add plain-text message, strip HTML tags.
	if ( event.message ) {
		const tmp = document.createElement( 'div' );
		tmp.innerHTML = event.message;
		parts.push( tmp.textContent || '' );
	}

	// Join, slugify, and truncate.
	const slug = parts
		.join( ' ' )
		.toLowerCase()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-|-$/g, '' )
		.slice( 0, 80 )
		.replace( /-$/, '' );

	return slug || `event-${ event.id }`;
}

/**
 * Copy a PNG blob to the clipboard, or download as fallback.
 *
 * @param {Blob}   blob  The image blob.
 * @param {Object} event The event object.
 * @return {Promise<string>} The result label to show.
 */
async function copyOrDownload( blob, event ) {
	if (
		typeof ClipboardItem !== 'undefined' &&
		navigator.clipboard &&
		navigator.clipboard.write
	) {
		await navigator.clipboard.write( [
			new ClipboardItem( { 'image/png': blob } ),
		] );
		return LABEL_COPIED;
	}

	downloadBlob( blob, `${ buildFilename( event ) }.png` );
	return LABEL_DOWNLOADED;
}

const LABEL_CAPTURING = __( 'Capturing\u2026', 'simple-history' );
const LABEL_COPIED = __( 'Image copied!', 'simple-history' );
const LABEL_DOWNLOADED = __( 'Image downloaded!', 'simple-history' );

/**
 * Menu item that captures the event as a branded PNG card image
 * and copies it to the clipboard (or downloads it as fallback).
 *
 * @param {Object} props
 * @param {Object} props.event      The event object.
 * @param {Object} props.actionsRef Ref to the actions wrapper inside the event li.
 * @return {Object} React element
 */
export function EventCopyScreenshotMenuItem( { event, actionsRef } ) {
	const labelDefault = __( 'Copy as image', 'simple-history' );
	const [ label, setLabel ] = useState( labelDefault );
	const [ isCapturing, setIsCapturing ] = useState( false );

	const handleClick = useCallback( async () => {
		if ( isCapturing ) {
			return;
		}

		setIsCapturing( true );
		setLabel( LABEL_CAPTURING );

		const logItem = actionsRef.current?.closest( '.SimpleHistoryLogitem' );
		if ( ! logItem ) {
			setLabel( labelDefault );
			setIsCapturing( false );
			return;
		}

		const { wrapper, restore } = prepareForCapture( logItem, event );

		try {
			const blob = await snapdom.toBlob( wrapper, {
				scale: 2,
				type: 'png',
			} );
			restore();
			const result = await copyOrDownload( blob, event );
			setLabel( result );
		} catch {
			restore();
			setLabel( labelDefault );
		}

		setIsCapturing( false );
		setTimeout( () => setLabel( labelDefault ), 2000 );
	}, [ event, actionsRef, isCapturing, labelDefault ] );

	return (
		<MenuItem icon={ capturePhoto } onClick={ handleClick }>
			{ label }
		</MenuItem>
	);
}
