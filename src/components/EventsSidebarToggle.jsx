import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { sidebar } from '@wordpress/icons';

/**
 * Show or hide the page sidebar, for the view currently being read.
 *
 * Per view, not per page. A table can turn the sidebar's width into columns —
 * measured at 1280px, the table overflows its own column by 69px with the
 * sidebar in place and has room to spare without it — while the detailed list
 * is a column of text that gains nothing from being wider. So the table starts
 * without the sidebar, the other two start with it, and anyone can disagree
 * per view. See REST_API::HIDDEN_SIDEBAR_VIEWS_USER_META_KEY.
 *
 * A single button carrying aria-pressed rather than two, matching the editor's
 * own sidebar control: there is one thing here that is either on or off, and
 * the label says which way pressing it goes.
 *
 * @param {Object}   props
 * @param {boolean}  props.isHidden Whether the sidebar is hidden right now.
 * @param {Function} props.onToggle Called with no arguments to flip it.
 */
export function EventsSidebarToggle( { isHidden, onToggle } ) {
	const label = isHidden
		? __( 'Show sidebar', 'simple-history' )
		: __( 'Hide sidebar', 'simple-history' );

	return (
		<Tooltip text={ label }>
			<Button
				className="sh-EventsSidebarToggle"
				icon={ sidebar }
				variant="tertiary"
				size="compact"
				// The label is the action, so it changes with the state; the
				// pressed state is what says which state we are in now.
				// Saying both in the label would read it out twice.
				aria-pressed={ isHidden }
				aria-label={ label }
				onClick={ onToggle }
			/>
		</Tooltip>
	);
}
