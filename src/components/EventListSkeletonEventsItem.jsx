// Fixed width presets cycled by row index. Widths used to be random per render,
// which made the skeleton visibly reshuffle whenever the parent re-rendered
// while events were still loading.
const WIDTH_PRESETS = [
	{ header: 44, text: 62, details: 55 },
	{ header: 48, text: 70, details: 52 },
	{ header: 41, text: 58, details: 58 },
	{ header: 46, text: 74, details: 50 },
];

/**
 * @param {Object} props
 * @param {number} props.index Row index, used to pick a width preset.
 * @return {JSX.Element} The skeleton row.
 */
export function EventListSkeletonEventsItem( props ) {
	const { index } = props;

	const preset = WIDTH_PRESETS[ index % WIDTH_PRESETS.length ];

	const headerStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: preset.header + '%',
		height: '1rem',
	};

	const textStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: preset.text + '%',
		height: '1.25rem',
	};

	const detailsStyles = {
		backgroundColor: 'var(--sh-color-gray-4)',
		width: preset.details + '%',
		height: '3rem',
	};

	return (
		<li className="SimpleHistoryLogitem SimpleHistoryLogitem--variant-normal SimpleHistoryLogitem--loglevel-debug SimpleHistoryLogitem--logger-WPHTTPRequestsLogger SimpleHistoryLogitem--initiator-wp_user">
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
