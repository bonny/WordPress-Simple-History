import { Icon, Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { viewAgenda, viewHeadline } from '../icons';

// Two stacked cards for the detailed rows, plain lines for the compact ones.
// @wordpress/icons has no pair that reads as one family here: postList is a
// bordered box and menu is a hamburger, which says "opens a menu".
const VIEWS = [
	{ value: 'detailed', icon: viewAgenda },
	{ value: 'compact', icon: viewHeadline },
];

/**
 * Switch between the detailed and the compact event list.
 *
 * Segmented control built the way GitHub builds theirs: a labelled list of
 * plain buttons carrying aria-pressed. Buttons answer to both Enter and Space,
 * and each one is its own tab stop.
 *
 * A radio group was tried first and was wrong here: it implies a form you
 * submit, Tab lands on the already-selected option, and Space there does
 * nothing — so the control looked dead to anyone who did not guess the arrow
 * keys. Arrow keys are deliberately not handled, same as GitHub: focus moves
 * with Tab only.
 *
 * @param {Object}   props
 * @param {string}   props.view     Current view, "detailed" or "compact".
 * @param {Function} props.onChange Called with the newly chosen view.
 */
export function EventsViewToggle( { view, onChange } ) {
	const labels = {
		detailed: __( 'Detailed view', 'simple-history' ),
		compact: __( 'Compact view', 'simple-history' ),
	};

	return (
		<ul
			className="sh-EventsViewToggle"
			aria-label={ __( 'Event list view', 'simple-history' ) }
		>
			{ VIEWS.map( ( option ) => {
				const isPressed = view === option.value;

				return (
					<li
						key={ option.value }
						className="sh-EventsViewToggle__item"
					>
						<Tooltip text={ labels[ option.value ] }>
							<button
								type="button"
								className={ clsx(
									'sh-EventsViewToggle__option',
									{ 'is-checked': isPressed }
								) }
								aria-pressed={ isPressed }
								aria-label={ labels[ option.value ] }
								onClick={ () => onChange( option.value ) }
							>
								<Icon icon={ option.icon } size={ 16 } />
							</button>
						</Tooltip>
					</li>
				);
			} ) }
		</ul>
	);
}
