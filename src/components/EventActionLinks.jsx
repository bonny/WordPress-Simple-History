const ACTION_ICONS = {
	view: 'sh-Icon--visibility',
	edit: 'sh-Icon--edit',
	preview: 'sh-Icon--preview',
	revisions: 'sh-Icon--history',
	details: 'sh-Icon--details',
};

const EXTERNAL_LINK_ICON = 'sh-Icon--external-link';

/**
 * Decide whether a URL points off-site.
 *
 * Relative URLs, fragment-only URLs, and URLs on the same host as the page
 * are considered internal. Anything else is external. Failures to parse the
 * URL (e.g. mailto:, weird inputs) fall through as internal so we don't
 * surprise users with an external icon where none is warranted.
 */
function isExternalUrl( url ) {
	if ( typeof url !== 'string' || url === '' ) {
		return false;
	}

	try {
		const parsed = new URL( url, window.location.href );
		return parsed.host !== window.location.host;
	} catch ( e ) {
		return false;
	}
}

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
			{ links.map( ( link ) => {
				const external = isExternalUrl( link.url );
				const iconClass = external
					? EXTERNAL_LINK_ICON
					: ACTION_ICONS[ link.action ];
				const extraAttrs = external
					? {
							target: '_blank',
							rel: 'noopener noreferrer',
					  }
					: {};

				return (
					<a
						key={ link.url }
						href={ link.url }
						title={ link.description || undefined }
						className="SimpleHistoryLogitem__actionLinks__link"
						{ ...extraAttrs }
					>
						{ iconClass && (
							<span className={ `sh-Icon ${ iconClass }` } />
						) }
						{ link.label }
					</a>
				);
			} ) }
		</div>
	);
}
