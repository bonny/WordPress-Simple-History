import { Button, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { drawerRight } from '@wordpress/icons';

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
				// sh-ControlBarButton as well, which is what the four
				// buttons to its left carry. Without it this inherited
				// @wordpress/components' raw tertiary blue while Export,
				// Create alert, Add log entry and Share view all sat at
				// --sh-color-gray-3, and the quietest control in the row was
				// drawn as the loudest. It also brings the icon down from
				// WP's unset 24px to the 1.25em the rest of the row uses.
				className="sh-ControlBarButton sh-EventsSidebarToggle"
				// drawerRight, not sidebar. The `sidebar` glyph puts its
				// solid mass on the LEFT — it draws a left-hand sidebar
				// under a header bar — and this sidebar is on the right, so
				// the icon described the mirror image of the thing it
				// toggles. drawerRight is a panel down the right edge, which
				// is the actual layout, and it still reads at 15px.
				//
				// @wordpress/icons is bundled rather than loaded from a WP
				// script handle, so which icons exist is a question about
				// this package, not about the WordPress 6.3 floor.
				icon={ drawerRight }
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
