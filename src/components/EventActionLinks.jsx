import { Fragment } from '@wordpress/element';

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
 *
 * @param {string} url
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
 * @param {Object} props.event    Event object with optional action_links array.
 * @param {Object} props.trailing Optional node placed after the links, on the
 *                                same line. The compact row puts its details
 *                                disclosure here instead of on a line of its own.
 */
export function EventActionLinks( { event, trailing } ) {
	const links = event.action_links;
	const hasLinks = links && links.length > 0;

	if ( ! hasLinks && ! trailing ) {
		return null;
	}

	const items = ( links || [] ).map( ( link ) => {
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

		return {
			key: link.url,
			node: (
				<a
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
			),
		};
	} );

	if ( trailing ) {
		items.push( { key: 'trailing', node: trailing } );
	}

	return (
		<div className="SimpleHistoryLogitem__actionLinks">
			{ items.map( ( item, index ) => (
				<Fragment key={ item.key }>
					{ /* A real element, not a ::before on the link: generated
					     content inside the link lands in its accessible name,
					     and screen readers then announce "dot, All plugins".
					     Only the views that drop the icons show it, see the
					     stylesheet. */ }
					{ index > 0 && (
						<span
							aria-hidden="true"
							className="SimpleHistoryLogitem__actionLinks__separator"
						>
							·
						</span>
					) }

					{ item.node }
				</Fragment>
			) ) }
		</div>
	);
}
