import { MenuItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link } from '@wordpress/icons';

export function EventCopyLinkMenuItem( { event } ) {
	const permalink = event.permalink;
	const copyText = __( 'Copy link to event details', 'simple-history' );
	const copiedText = __( 'Link copied to clipboard', 'simple-history' );

	const [ dynamicCopyText, setDynamicCopyText ] = useState( copyText );

	const ref = useCopyToClipboard( permalink, () => {
		setDynamicCopyText( copiedText );
		setTimeout( () => {
			setDynamicCopyText( copyText );
		}, 2000 );

		// A notice after copy link would be better but this does not work for some reason.
	} );

	return (
		<MenuItem icon={ link } ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}
