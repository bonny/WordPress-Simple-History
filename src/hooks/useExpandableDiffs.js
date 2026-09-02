import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Diff containers are cropped to a fixed height by CSS. Anything taller is
 * silently cut off and can only be read by scrolling inside a small box, with
 * nothing to indicate there is more below — on a long diff that can hide the
 * overwhelming majority of a recorded change while the row still looks complete.
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

/**
 * Set statically in PHP by diffs that opt out of cropping altogether, such as
 * the before/after thumbnail comparison. Nothing to expand there.
 */
const NO_CROP_CLASS = 'SimpleHistory__diff__contents--noContentsCrop';

const CROPPED_CLASS = 'SimpleHistory__diff__contents--isCropped';

/**
 * Deliberately not NO_CROP_CLASS: expanding lifts the crop to a tall-but-bounded
 * height rather than removing it, so a very long diff does not push the rest of
 * the page — and this button — far out of reach.
 */
const EXPANDED_CLASS = 'SimpleHistory__diff__contents--isExpanded';

const TOGGLE_CLASS = 'SimpleHistory__diff__expandToggle';

/**
 * A couple of pixels of slack so sub-pixel rounding does not make a diff that
 * fits exactly look croppable.
 */
const OVERFLOW_TOLERANCE = 4;

/** Used to give each diff a stable id for aria-controls. */
let idCounter = 0;

/**
 * Set the toggle's label and state to match whether its diff is expanded.
 *
 * "Expand" rather than "Show full diff", because expanding raises the height
 * limit but does not remove it — a very long diff is still scrollable once
 * open, so promising the full diff would be a promise the control does not
 * keep. Naming the object ("diff") also keeps each button distinguishable in a
 * screen reader's button list, where a page of events would otherwise present a
 * stack of identical "Show more" controls.
 *
 * No count of hidden lines is offered. It cannot be measured honestly: the diff
 * is a table whose every row holds both the before and after column, and either
 * cell may wrap to several visual lines, so neither table rows nor pixel
 * heights correspond to lines as a reader perceives them. A number that looks
 * precise and is not would be worse than no number.
 *
 * @param {HTMLElement} button
 * @param {boolean}     isExpanded
 */
function setToggleState( button, isExpanded ) {
	button.setAttribute( 'aria-expanded', isExpanded ? 'true' : 'false' );
	button.textContent = isExpanded
		? __( 'Collapse diff', 'simple-history' )
		: __( 'Expand diff', 'simple-history' );
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
		const isExpanded = contents.classList.toggle( EXPANDED_CLASS );
		setToggleState( button, isExpanded );

		// Collapsing removes a lot of height at once, so without this the
		// button and everything below it jump up the screen by however tall
		// the diff was. Disorienting in general, worse when zoomed in.
		if ( ! isExpanded ) {
			contents.scrollIntoView( { block: 'nearest' } );
		}
	};

	const addButton = () => {
		if ( ! contents.id ) {
			idCounter += 1;
			contents.id = `sh-diff-contents-${ idCounter }`;
		}

		button = document.createElement( 'button' );
		button.type = 'button';
		button.className = TOGGLE_CLASS;
		button.setAttribute( 'aria-controls', contents.id );
		setToggleState( button, false );
		button.addEventListener( 'click', onClick );
		contents.after( button );
		contents.classList.add( CROPPED_CLASS );
	};

	const removeButton = () => {
		// Removal is triggered by a resize, not by the user, so it can land
		// while the button has keyboard focus — widening a window or collapsing
		// a sidebar is enough. Ripping a focused element out drops focus to
		// <body> and sends the user back to the top of the page, so hand focus
		// to the diff itself, which is already focusable.
		if ( button.contains( contents.ownerDocument.activeElement ) ) {
			contents.focus();
		}

		button.removeEventListener( 'click', onClick );
		button.remove();
		button = null;
		contents.classList.remove( CROPPED_CLASS, EXPANDED_CLASS );
	};

	const sync = () => {
		// An expanded diff is taller than its collapsed limit by definition, so
		// re-measuring it would always read as cropped. Leave the user's choice
		// alone.
		if ( button && contents.classList.contains( EXPANDED_CLASS ) ) {
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
			if ( contents.classList.contains( NO_CROP_CLASS ) ) {
				return;
			}

			cleanups.push( attachToggle( contents ) );
		} );

		return () => cleanups.forEach( ( cleanup ) => cleanup() );
	}, [ ref, resetOnDep ] );
}
