import { __ } from '@wordpress/i18n';
import { link } from '@wordpress/icons';
import { CopyMenuItem } from './CopyMenuItem';

export function EventCopyLinkMenuItem( { event } ) {
	return (
		<CopyMenuItem
			icon={ link }
			label={ __( 'Copy link to event details', 'simple-history' ) }
			labelCopied={ __( 'Link copied to clipboard', 'simple-history' ) }
			payload={ event.permalink }
		/>
	);
}
