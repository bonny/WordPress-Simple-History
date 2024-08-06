import { clsx } from 'clsx';

/**
 * Outputs a span with inline-block display and a divider.
 *
 * @param {Object} props
 * @param {string} props.children
 * @param {string} props.className
 */
export function EventHeaderItem( props ) {
	const { children, className } = props;
	const classNames = clsx( 'SimpleHistoryLogitem__inlineDivided', className );

	return (
		<>
			{ /* Leave space so items can wrap on smaller screens. */ }{ ' ' }
			<span className={ classNames }>{ children }</span>
		</>
	);
}
