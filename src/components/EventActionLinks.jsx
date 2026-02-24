const ACTION_ICONS = {
	view: 'sh-Icon--visibility',
	edit: 'sh-Icon--edit',
	preview: 'sh-Icon--preview',
	revisions: 'sh-Icon--history',
};

/**
 * Renders structured action links below an event.
 *
 * @param {Object} props
 * @param {Object} props.event Event object with optional action_links array.
 */
export function EventActionLinks( { event } ) {
	const links = event.action_links;

	if ( ! links || links.length === 0 ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__actionLinks">
			{ links.map( ( link ) => (
				<a
					key={ link.action }
					href={ link.url }
					className="SimpleHistoryLogitem__actionLinks__link"
				>
					{ ACTION_ICONS[ link.action ] && (
						<span
							className={ `sh-Icon ${
								ACTION_ICONS[ link.action ]
							}` }
						/>
					) }
					{ link.label }
				</a>
			) ) }
		</div>
	);
}
