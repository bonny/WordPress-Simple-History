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

	// The -4.5rem/-1.5rem pull-out matches the normal row's geometry:
	// .SimpleHistoryLogitem__secondcol margin-left (50px) plus the li's own
	// padding (var(--sh-spacing-medium)). Compact rows use a narrower
	// secondcol margin-left (40px, see css/styles.css), so reusing the normal
	// offset pulled the nested occasion rows too far left and clipped their
	// avatars — cancel the compact geometry instead.
	let wrapStyle;

	if ( isDashboard ) {
		wrapStyle = { marginTop: '0.5rem' };
	} else if ( isCompact ) {
		wrapStyle = {
			marginTop: '1rem',
			marginLeft: 'calc(-40px - var(--sh-spacing-medium))',
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
