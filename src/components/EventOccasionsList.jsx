import { __ } from '@wordpress/i18n';
import { clsx } from 'clsx';
import { Event } from './Event';

export function EventOccasionsList( props ) {
	const {
		occasions,
		isLoadingOccasions,
		subsequent_occasions_count: subsequentOccasionsCount,
		occasionsCountMaxReturn,
	} = props;

	const ulClassNames = clsx( {
		SimpleHistoryLogitems: true,
		SimpleHistoryLogitem__occasionsItems: true,
		haveOccasionsAdded: isLoadingOccasions === false,
	} );

	return (
		<div
			className="SimpleHistoryLogitem__occasionsItemsWrap"
			style={ {
				marginTop: '1rem',
				marginLeft: '-4.5rem',
				marginRight: '-1.5rem',
			} }
		>
			<ul className={ ulClassNames }>
				{ occasions.map( ( event ) => (
					<Event key={ event.id } event={ event } />
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
