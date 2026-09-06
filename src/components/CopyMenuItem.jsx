import { MenuItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { check } from '@wordpress/icons';

// WordPress' success/alert green, matching admin notice-success.
const COPY_SUCCESS_COLOR = '#00a32a';

/**
 * Dropdown menu item that copies a payload to the clipboard and shows a 2-second
 * "copied" state (green checkmark + tinted label) for explicit success feedback.
 *
 * Consolidates what used to be four near-identical menu-item components.
 *
 * @param {Object}   props
 * @param {Object}   props.icon        Idle-state icon (swapped to `check` on success).
 * @param {string}   props.label       Idle-state label.
 * @param {string}   props.labelCopied Label shown for 2s after a successful copy.
 * @param {string}   props.payload     The text written to the clipboard on click.
 * @return {Object} React element
 */
export function CopyMenuItem( { icon, label, labelCopied, payload } ) {
	const [ isCopied, setIsCopied ] = useState( false );

	const ref = useCopyToClipboard( payload, () => {
		setIsCopied( true );
		setTimeout( () => setIsCopied( false ), 2000 );
	} );

	return (
		<MenuItem icon={ isCopied ? check : icon } ref={ ref }>
			<span
				style={ isCopied ? { color: COPY_SUCCESS_COLOR } : undefined }
			>
				{ isCopied ? labelCopied : label }
			</span>
		</MenuItem>
	);
}
