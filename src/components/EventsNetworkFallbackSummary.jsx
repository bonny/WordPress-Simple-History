import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Single-line summary shown above the events list when one or more
 * events on the current page originated at the network level.
 *
 * Per-row surfaces carry only a subtle "network-level" label
 * (see EventNetworkFallbackIndicator). This component is where the
 * explanation and the link live — once, at the top of the view.
 *
 * Count is page-scoped (the loaded events prop), so the copy says
 * "on this page" to avoid implying it's a total across all events.
 *
 * Renders nothing when no events on the page carry the flag.
 *
 * @param {Object}      props
 * @param {Array}       props.events                 The events currently in the view.
 * @param {string|null} props.networkHistoryAdminURL Network history page URL (null when the feature isn't available on this install — fall back to the marketing page).
 */
export function EventsNetworkFallbackSummary( props ) {
	const { events, networkHistoryAdminURL } = props;

	if ( ! Array.isArray( events ) || events.length === 0 ) {
		return null;
	}

	const count = events.filter(
		( event ) => event && event.network_fallback === true
	).length;

	if ( count === 0 ) {
		return null;
	}

	// Prefer linking to the in-admin network history page (the teaser) so
	// users stay inside WP-admin; fall back to the marketing page only when
	// the feature isn't registered on this install.
	const linkHref =
		networkHistoryAdminURL ||
		'https://simple-history.com/premium/?utm_source=wpadmin&utm_medium=event-log-summary&utm_campaign=network-fallback';

	// External link opens in a new tab; internal link navigates in place.
	const linkTargetProps = networkHistoryAdminURL
		? {}
		: { target: '_blank', rel: 'noopener noreferrer' };

	const label = sprintf(
		/* translators: %d: count of network-level events on the current page. */
		_n(
			'%d event on this page happened at the network level.',
			'%d events on this page happened at the network level.',
			count,
			'simple-history'
		),
		count
	);

	return (
		<p className="sh-NetworkFallbackSummary">
			<span className="sh-NetworkFallbackSummary-label">{ label }</span>{ ' ' }
			<a
				className="sh-NetworkFallbackSummary-link"
				href={ linkHref }
				{ ...linkTargetProps }
			>
				{ __(
					'Premium adds a dedicated network log →',
					'simple-history'
				) }
			</a>
		</p>
	);
}
