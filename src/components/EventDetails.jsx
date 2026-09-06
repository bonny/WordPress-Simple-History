import { useRef } from '@wordpress/element';
import { useExpandableDiffs } from '../hooks/useExpandableDiffs';

/**
 * Outputs event details.
 *
 * @param {Object} props
 */
export function EventDetails( props ) {
	const { event } = props;
	const { details_html: detailsHtml } = event;
	const detailsRef = useRef( null );

	useExpandableDiffs( detailsRef, detailsHtml );

	return (
		<div
			ref={ detailsRef }
			className="SimpleHistoryLogitem__details"
			dangerouslySetInnerHTML={ { __html: detailsHtml } }
		></div>
	);
}
