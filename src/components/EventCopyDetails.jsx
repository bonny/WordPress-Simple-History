import { MenuItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { info } from '@wordpress/icons';

/**
 * Format event details for copying.
 *
 * @param {Object} event
 * @return {string} Formatted event details
 */
function formatEventDetails( event ) {
	const initiatorData = event.initiator_data || {};
	const username =
		initiatorData.user_display_name || initiatorData.user_login || '';
	const userEmail = initiatorData.user_email
		? `(${ initiatorData.user_email })`
		: '';

	// Format date: YYYY-MM-DD (month d yyyy) at HH:mm:ss
	let formattedDate = '';
	if ( event.date_local ) {
		const dateObj = new Date( event.date_local.replace( ' ', 'T' ) );
		const year = dateObj.getFullYear();
		const month = dateObj.toLocaleString( 'default', { month: 'long' } );
		const day = dateObj.getDate();
		const time = dateObj.toLocaleTimeString( [], {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
			hour12: false,
		} );
		formattedDate =
			`${ year }-` +
			`${ String( dateObj.getMonth() + 1 ).padStart( 2, '0' ) }-` +
			`${ String( day ).padStart( 2, '0' ) }` +
			` (${ month.toLowerCase() } ${ day } ${ year }) at ${ time }`;
	}

	const via = event.via ? `• Via ${ event.via }` : '';
	const line1 =
		[ username, userEmail ].filter( Boolean ).join( ' ' ) +
		( formattedDate ? ` • ${ formattedDate }` : '' ) +
		( via ? ` ${ via }` : '' );
	const message = event.message || '';

	return `${ line1 }\n${ message }`;
}

/**
 * Format event context as a markdown table.
 *
 * @param {Object} context
 * @return {string} Markdown table or empty string
 */
function formatContextAsMarkdownTable( context ) {
	if (
		! context ||
		typeof context !== 'object' ||
		Object.keys( context ).length === 0
	) {
		return '';
	}

	let table = '\n\n| Key | Value |\n| --- | ----- |';
	for ( const [ key, value ] of Object.entries( context ) ) {
		table += `\n| ${ key } | ${ value } |`;
	}
	return table;
}

/**
 * Escape pipe characters so they don't break markdown tables.
 *
 * @param {string} value
 * @return {string}
 */
function mdEscape( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	return String( value ).replace( /\|/g, '\\|' ).replace( /\n/g, ' ' );
}

/**
 * Render the structured details_data array as Markdown.
 *
 * details_data has shape: [{ title, items: [{ name, prev_value, new_value, ... }] }]
 *
 * @param {Array} detailsData
 * @return {string}
 */
function formatDetailsDataAsMarkdown( detailsData ) {
	if ( ! Array.isArray( detailsData ) || detailsData.length === 0 ) {
		return '';
	}

	const sections = [];
	for ( const group of detailsData ) {
		if (
			! group ||
			! Array.isArray( group.items ) ||
			group.items.length === 0
		) {
			continue;
		}

		const lines = [];
		if ( group.title ) {
			lines.push( `### ${ group.title }` );
			lines.push( '' );
		}

		const hasPrev = group.items.some(
			( item ) =>
				item.prev_value !== undefined &&
				item.prev_value !== null &&
				item.prev_value !== ''
		);

		if ( hasPrev ) {
			lines.push( '| Field | Previous | New |' );
			lines.push( '| --- | --- | --- |' );
			for ( const item of group.items ) {
				lines.push(
					`| ${ mdEscape( item.name ) } | ${ mdEscape(
						item.prev_value
					) } | ${ mdEscape( item.new_value ) } |`
				);
			}
		} else {
			lines.push( '| Field | Value |' );
			lines.push( '| --- | --- |' );
			for ( const item of group.items ) {
				lines.push(
					`| ${ mdEscape( item.name ) } | ${ mdEscape(
						item.new_value
					) } |`
				);
			}
		}

		sections.push( lines.join( '\n' ) );
	}

	return sections.join( '\n\n' );
}

/**
 * Strip HTML tags and decode entities for a plain-text fallback.
 *
 * Only used when details_data is empty but details_html is present (older
 * loggers that haven't migrated to the Event Details API yet).
 *
 * @param {string} html
 * @return {string}
 */
function htmlToPlainText( html ) {
	if ( ! html || typeof html !== 'string' ) {
		return '';
	}
	const tmp = document.createElement( 'div' );
	tmp.innerHTML = html;
	return ( tmp.textContent || tmp.innerText || '' )
		.replace( /\n{3,}/g, '\n\n' )
		.trim();
}

/**
 * Format a complete event as a self-contained Markdown block,
 * suitable for pasting into a ticket, Slack, or notes app.
 *
 * @param {Object} event
 * @return {string}
 */
function formatEventAsMarkdown( event ) {
	const initiatorData = event.initiator_data || {};
	const username =
		initiatorData.user_display_name || initiatorData.user_login || '';
	const userEmail = initiatorData.user_email || '';
	const who = [ username, userEmail ? `(${ userEmail })` : '' ]
		.filter( Boolean )
		.join( ' ' );

	const message = event.message || '';
	const heading = message ? message.replace( /\n/g, ' ' ) : 'Event';

	const rows = [];
	if ( event.date_local ) {
		rows.push( [ 'When', event.date_local ] );
	}
	if ( who ) {
		rows.push( [ 'Who', who ] );
	}
	if ( event.via ) {
		rows.push( [ 'Via', event.via ] );
	}
	if ( event.logger ) {
		rows.push( [ 'Logger', event.logger ] );
	}
	if ( event.loglevel ) {
		rows.push( [ 'Level', event.loglevel ] );
	}
	if ( event.id ) {
		rows.push( [ 'Event ID', event.id ] );
	}
	if ( event.permalink ) {
		rows.push( [ 'Permalink', event.permalink ] );
	}

	let md = `**${ heading }**\n\n`;

	if ( rows.length ) {
		md += '| Field | Value |\n| --- | --- |\n';
		md += rows
			.map(
				( [ k, v ] ) => `| ${ mdEscape( k ) } | ${ mdEscape( v ) } |`
			)
			.join( '\n' );
		md += '\n';
	}

	// Structured details (Event Details API): rendered as a Markdown table.
	const detailsMd = formatDetailsDataAsMarkdown( event.details_data );
	if ( detailsMd ) {
		md += '\n**Details**\n\n' + detailsMd + '\n';
	} else if ( event.details_html ) {
		// Legacy loggers that still emit raw HTML — strip and inline as text.
		const detailsText = htmlToPlainText( event.details_html );
		if ( detailsText ) {
			md += '\n**Details**\n\n' + detailsText + '\n';
		}
	}

	const objectTable = formatContextAsMarkdownTable( event.context );
	if ( objectTable ) {
		md += `\n**Context**${ objectTable }\n`;
	}

	return md;
}

/**
 * Menu Item to copy event details to clipboard.
 *
 * @param {Object} props
 * @param {Object} props.event
 * @return {Object} React element
 */
export function EventCopyDetails( { event } ) {
	const copyText = __( 'Copy event message', 'simple-history' );
	const copiedText = __( 'Event message copied', 'simple-history' );
	const [ dynamicCopyText, setDynamicCopyText ] = useState( copyText );
	const formattedDetails = formatEventDetails( event );
	const ref = useCopyToClipboard( formattedDetails, () => {
		setDynamicCopyText( copiedText );
		setTimeout( () => {
			setDynamicCopyText( copyText );
		}, 2000 );
	} );

	return (
		<MenuItem icon={ info } ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}

/**
 * Menu Item to copy event details (detailed) to clipboard.
 *
 * @param {Object} props
 * @param {Object} props.event
 * @return {Object} React element
 */
export function EventCopyDetailsDetailed( { event } ) {
	const copyText = __( 'Copy as Markdown', 'simple-history' );
	const copiedText = __( 'Copied as Markdown', 'simple-history' );

	const [ dynamicCopyText, setDynamicCopyText ] = useState( copyText );
	const formattedDetails = formatEventAsMarkdown( event );
	const ref = useCopyToClipboard( formattedDetails, () => {
		setDynamicCopyText( copiedText );
		setTimeout( () => {
			setDynamicCopyText( copyText );
		}, 2000 );
	} );

	return (
		<MenuItem icon={ info } ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}

/**
 * Menu Item to copy the full event as JSON, including all context.
 *
 * @param {Object} props
 * @param {Object} props.event
 * @return {Object} React element
 */
export function EventCopyDetailsJson( { event } ) {
	const copyText = __( 'Copy as JSON', 'simple-history' );
	const copiedText = __( 'Copied as JSON', 'simple-history' );

	const [ dynamicCopyText, setDynamicCopyText ] = useState( copyText );
	const json = JSON.stringify( event, null, 2 );
	const ref = useCopyToClipboard( json, () => {
		setDynamicCopyText( copiedText );
		setTimeout( () => {
			setDynamicCopyText( copyText );
		}, 2000 );
	} );

	return (
		<MenuItem icon={ info } ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}
