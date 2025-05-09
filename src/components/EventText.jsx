import { clsx } from 'clsx';
import { __ } from '@wordpress/i18n';
import { pinSmall } from '@wordpress/icons';
import { Icon, Tooltip, VisuallyHidden } from '@wordpress/components';

/**
 * Outputs the main event text, i.e.:
 * - "Logged in"
 * - "Created post"
 *
 * @param {Object} props
 * @param {Object} props.event
 * @return {Object} React element
 */
export function EventText( { event } ) {
	const logLevelClassNames = clsx(
		'SimpleHistoryLogitem--logleveltag',
		`SimpleHistoryLogitem--logleveltag-${ event.loglevel }`
	);

	return (
		<div className="SimpleHistoryLogitem__text">
			<EventStickyIcon event={ event } />
			<span
				dangerouslySetInnerHTML={ { __html: event.message_html } }
			></span>{ ' ' }
			<span className={ logLevelClassNames }>{ event.loglevel }</span>
		</div>
	);
}

function EventStickyIcon( { event } ) {
	const stickyClassNames = clsx( 'SimpleHistoryLogitem--sticky' );

	return event.sticky ? (
		<Tooltip text={ __( 'Sticky', 'simple-history' ) }>
			<span className={ stickyClassNames }>
				<VisuallyHidden as="span">
					{ __( 'Sticky', 'simple-history' ) }
				</VisuallyHidden>
				<Icon icon={ pinSmall } />
			</span>
		</Tooltip>
	) : null;
}
