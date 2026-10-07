import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { EventActionsButton } from './EventActionsButton';
import { EventActionLinks } from './EventActionLinks';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventReactions } from './EventReactions';
import { EventText } from './EventText';

/**
 * The compact event row (issue 240).
 *
 * There is no left column: the avatar rides in the header line, so the
 * message, the action links and the avatar all share the row's left edge.
 *
 * The details table is hidden behind a disclosure rather than dropped: the
 * details come down with the event either way, so opening one costs a render
 * and nothing more. Rows without details never show the toggle.
 *
 * Everything inside the row is the same component the detailed row uses; only
 * the arrangement differs.
 *
 * @param {Object}  props
 * @param {Object}  props.event
 * @param {Object}  props.reactionState           From useEventReactions, shared with the actions menu.
 * @param {boolean} props.isSurroundingEventsMode
 */
export function EventRowCompact( {
	event,
	reactionState,
	isSurroundingEventsMode,
} ) {
	const [ isShowingDetails, setIsShowingDetails ] = useState( false );

	const hasDetails = Boolean(
		event.details_html && event.details_html.trim() !== ''
	);

	// "Show details", not "Details": the actions cluster on the same row
	// already has an "Event details" button and a "View event details" menu
	// item, and both open the modal. The verb says this one happens in place.
	const detailsToggleLabel = isShowingDetails
		? __( 'Hide details', 'simple-history' )
		: __( 'Show details', 'simple-history' );

	// The button sits in the action links row and the details it opens render
	// below it, so the state lives here rather than in a component of its own.
	const detailsToggle = hasDetails ? (
		<button
			type="button"
			className="SimpleHistory__disclosureToggle SimpleHistoryLogitem__detailsToggle"
			aria-expanded={ isShowingDetails }
			onClick={ () => setIsShowingDetails( ! isShowingDetails ) }
		>
			{ detailsToggleLabel }
		</button>
	) : null;

	return (
		<div className="SimpleHistoryLogitem__secondcol">
			<EventHeader
				event={ event }
				eventVariant="compact"
				isSurroundingEventsMode={ isSurroundingEventsMode }
				leading={
					<span className="SimpleHistoryLogitem__headerAvatar">
						<EventInitiatorImage event={ event } />
					</span>
				}
			/>

			<EventText event={ event } eventVariant="compact" />

			<EventActionLinks event={ event } trailing={ detailsToggle } />

			{ isShowingDetails && (
				<EventDetails event={ event } eventVariant="compact" />
			) }

			<EventReactions { ...reactionState } />

			<EventOccasions event={ event } eventVariant="compact" />

			<EventActionsButton
				event={ event }
				eventVariant="compact"
				reactionState={ reactionState }
			/>
		</div>
	);
}
