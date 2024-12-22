import { randomIntFromInterval } from '../functions';

export function EventListSkeletonEventsItem( props ) {
	const { index } = props;

	const headerStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: randomIntFromInterval( 40, 50 ) + '%',
		height: '1rem',
	};

	const textStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: randomIntFromInterval( 55, 75 ) + '%',
		height: '1.25rem',
	};

	const detailsStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: randomIntFromInterval( 50, 60 ) + '%',
		height: '3rem',
	};

	return (
		<li
			key={ index }
			className="SimpleHistoryLogitem SimpleHistoryLogitem--variant-normal SimpleHistoryLogitem--loglevel-debug SimpleHistoryLogitem--logger-WPHTTPRequestsLogger SimpleHistoryLogitem--initiator-wp_user"
		>
			<div
				className="SimpleHistoryLogitem__firstcol"
				style={ {
					width: 32,
					height: 32,
					borderRadius: '50%',
					backgroundColor: 'var(--sh-color-gray-4)',
				} }
			></div>

			<div className="SimpleHistoryLogitem__secondcol">
				<div
					className="SimpleHistoryLogitem__header"
					style={ headerStyles }
				></div>
				<div
					className="SimpleHistoryLogitem__text"
					style={ textStyles }
				></div>
				<div
					className="SimpleHistoryLogitem__details"
					style={ detailsStyles }
				></div>
			</div>
		</li>
	);
}
