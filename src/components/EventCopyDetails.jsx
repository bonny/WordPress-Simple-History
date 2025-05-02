import { MenuItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { info } from '@wordpress/icons';

function formatEventDetails( event ) {
	let details = '';
	details += `ID: ${ event.id }\n`;
	details += `Logger: ${ event.logger }\n`;
	details += `Level: ${ event.loglevel }\n`;
	details += `Date (local): ${ event.date_local }\n`;
	details += `Date (GMT): ${ event.date_gmt }\n`;
	details += `Message: ${ event.message }\n`;
	details += `Initiator: ${ event.initiator }\n`;
	details += `Occasions ID: ${ event.occasions_id }\n`;
	details += `Subsequent Occasions Count: ${ event.subsequent_occasions_count }\n`;
	details += `Via: ${ event.via }\n`;
	if ( event.context && typeof event.context === 'object' ) {
		details += `Context:`;
		for ( const [ key, value ] of Object.entries( event.context ) ) {
			details += `\n  ${ key }: ${ value }`;
		}
		details += '\n';
	}
	return details;
}

export function EventCopyDetails( { event } ) {
	const copyText = __( 'Copy event details', 'simple-history' );
	const copiedText = __( 'Event details copied', 'simple-history' );
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