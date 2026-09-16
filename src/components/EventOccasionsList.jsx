import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { Event } from './Event';

export function EventOccasionsList( props ) {
	const {
		occasions,
		parentEvent,
		eventVariant,
		isLoadingOccasions,
		subsequent_occasions_count: subsequentOccasionsCount,
		occasionsCountMaxReturn,
	} = props;

	const isDashboard = eventVariant === 'dashboard';
	const isCompact = eventVariant === 'compact';

	const ulClassNames = clsx( {
		SimpleHistoryLogitems: true,
		SimpleHistoryLogitem__occasionsItems: true,
		haveOccasionsAdded: isLoadingOccasions === false,
	} );

	const wrapClassNames = clsx( 'SimpleHistoryLogitem__occasionsItemsWrap', {
		'is-dashboard': isDashboard,
	} );

	// The -4.5rem/-1.5rem pull-out is tuned to the normal row's geometry —
	// roughly .SimpleHistoryLogitem__secondcol's margin-left (50px) plus the
	// li's own padding (var(--sh-spacing-medium)), though not an exact sum
	// of the two. Compact rows have no secondcol margin at all (the avatar
	// sits in the header line), so reusing the normal offset pulled the
	// nested occasion rows off the left edge — cancel the li's padding only.
	let wrapStyle;

	if ( isDashboard ) {
		wrapStyle = { marginTop: '0.5rem' };
	} else if ( isCompact ) {
		wrapStyle = {
			marginTop: 'var(--sh-spacing-small)',
			marginLeft: 'calc(var(--sh-spacing-medium) * -1)',
			marginRight: '-1.5rem',
		};
	} else {
		wrapStyle = {
			marginTop: '1rem',
			marginLeft: '-4.5rem',
			marginRight: '-1.5rem',
		};
	}

	return (
		<div className={ wrapClassNames } style={ wrapStyle }>
			<ul className={ ulClassNames }>
				{ occasions.map( ( event, index ) => (
					<Event
						key={ event.id }
						event={ event }
						variant={ eventVariant }
						loopIndex={ index }
						prevEvent={
							index === 0 ? parentEvent : occasions[ index - 1 ]
						}
					/>
				) ) }

				{ /* // If occasionsCount is more than occasionsCountMaxReturn then show a message */ }
				{ subsequentOccasionsCount > occasionsCountMaxReturn ? (
					<li className="SimpleHistoryLogitem SimpleHistoryLogitem--occasion SimpleHistoryLogitem--occasion-tooMany">
						<div className="SimpleHistoryLogitem__firstcol"></div>
						<div className="SimpleHistoryLogitem__secondcol">
							<div className="SimpleHistoryLogitem__text">
								{ __(
									'Sorry, but there are too many similar events to show.',
									'simple-history'
								) }
							</div>
						</div>
					</li>
				) : null }
			</ul>
		</div>
	);
}
