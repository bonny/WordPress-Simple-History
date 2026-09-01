import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Diff containers are cropped to a fixed height by CSS. Anything taller is
 * silently cut off and can only be read by scrolling inside a small box, with
 * nothing to indicate there is more below.
 *
 * This adds an expand toggle, but only to the diffs that are actually cropped.
 * That "actually" is why this is JavaScript and not markup: whether a diff
 * overflows depends on the rendered height, which neither PHP nor CSS can know.
 * `<details>` was the obvious standards-first choice and does not fit either —
 * it hides all non-summary content when closed, whereas a collapsed diff has to
 * keep showing its first few lines.
 *
 * Without JavaScript nothing is added and the previous scroll-inside behaviour
 * is untouched, which is the correct degradation for a control whose whole
 * purpose is interactive.
 */

const CONTENTS_SELECTOR = '.SimpleHistory__diff__contents';
const NO_CROP_CLASS = 'SimpleHistory__diff__contents--noContentsCrop';
const CROPPED_CLASS = 'SimpleHistory__diff__contents--isCropped';
const TOGGLE_CLASS = 'SimpleHistory__diff__expandToggle';

/**
 * A couple of pixels of slack so sub-pixel rounding does not make a diff that
 * fits exactly look croppable.
 */
const OVERFLOW_TOLERANCE = 4;

/**
 * Set the toggle's label and state to match whether its diff is expanded.
 *
 * @param {HTMLElement} button
 * @param {boolean}     isExpanded
 */
function setToggleState( button, isExpanded ) {
	button.setAttribute( 'aria-expanded', isExpanded ? 'true' : 'false' );
	button.textContent = isExpanded
		? __( 'Show less', 'simple-history' )
		: __( 'Show full diff', 'simple-history' );
}

/**
 * Attach an expand toggle to a diff container, keeping it in step with whether
 * the diff is actually cropped.
 *
 * Whether a diff overflows is not fixed: narrowing the window rewraps the text
 * and can push a diff that fitted past the height limit, and widening does the
 * reverse. Measuring once at mount would leave a silently cropped diff with no
 * way to open it — the original complaint — or a toggle on a diff that hides
 * nothing. So the measurement is repeated whenever the box is resized.
 *
 * @param {HTMLElement} contents
 * @return {Function} Cleanup that removes the toggle and observer again.
 */
function attachToggle( contents ) {
	let button = null;

	const onClick = () => {
		const isExpanded = contents.classList.toggle( NO_CROP_CLASS );
		setToggleState( button, isExpanded );
	};

	const addButton = () => {
		button = document.createElement( 'button' );
		button.type = 'button';
		button.className = TOGGLE_CLASS;
		setToggleState( button, false );
		button.addEventListener( 'click', onClick );
		contents.after( button );
		contents.classList.add( CROPPED_CLASS );
	};

	const removeButton = () => {
		button.removeEventListener( 'click', onClick );
		button.remove();
		button = null;
		contents.classList.remove( CROPPED_CLASS, NO_CROP_CLASS );
	};

	const sync = () => {
		// An expanded diff is taller than its limit by definition, so re-measuring
		// it would always read as cropped. Leave the user's choice alone.
		if ( button && contents.classList.contains( NO_CROP_CLASS ) ) {
			return;
		}

		const isCropped =
			contents.scrollHeight > contents.clientHeight + OVERFLOW_TOLERANCE;

		if ( isCropped && ! button ) {
			addButton();
		} else if ( ! isCropped && button ) {
			removeButton();
		}
	};

	sync();

	// ResizeObserver is available everywhere wp-admin runs, but guard anyway so
	// a missing implementation degrades to the one-off measurement above.
	const observer =
		typeof ResizeObserver === 'undefined'
			? null
			: new ResizeObserver( sync );

	if ( observer ) {
		observer.observe( contents );
	}

	return () => {
		if ( observer ) {
			observer.disconnect();
		}

		if ( button ) {
			removeButton();
		}
	};
}

/**
 * Add expand toggles to any cropped diffs inside a container.
 *
 * @param {Object} ref        React ref holding the element that contains the diffs.
 * @param {*}      resetOnDep Value that, when it changes, rebuilds the toggles.
 */
export function useExpandableDiffs( ref, resetOnDep ) {
	useEffect( () => {
		const root = ref.current;

		if ( ! root ) {
			return undefined;
		}

		const cleanups = [];

		root.querySelectorAll( CONTENTS_SELECTOR ).forEach( ( contents ) => {
			// Some diffs opt out of cropping entirely, so there is nothing to expand.
			if ( contents.classList.contains( NO_CROP_CLASS ) ) {
				return;
			}

			cleanups.push( attachToggle( contents ) );
		} );

		return () => cleanups.forEach( ( cleanup ) => cleanup() );
	}, [ ref, resetOnDep ] );
}
