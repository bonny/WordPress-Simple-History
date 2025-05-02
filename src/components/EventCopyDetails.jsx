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
		<MenuItem icon={ info } iconPosition="left" ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}
