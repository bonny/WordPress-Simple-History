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
 * @param {string|null} props.networkHistoryAdminURL Network history page URL (null when the feature isn't available on this install — fall back to the upgrade URL).
 * @param {string|null} props.networkUpgradeURL      Tracked upgrade URL, built in PHP so the campaign parameters live with the rest of them.
 */
export function EventsNetworkFallbackSummary( props ) {
	const { events, networkHistoryAdminURL, networkUpgradeURL } = props;

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
	// the feature isn't registered on this install. Both URLs come from the
	// search options response — the marketing one carries the tracking
	// parameters Helpers::get_tracking_url() adds, which is why it isn't
	// written out here.
	const linkHref = networkHistoryAdminURL || networkUpgradeURL;

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

	// No URL at all (an install whose search options response predates
	// these fields): say what happened, without a dead link.
	if ( ! linkHref ) {
		return (
			<p className="sh-NetworkFallbackSummary">
				<span className="sh-NetworkFallbackSummary-label">
					{ label }
				</span>
			</p>
		);
	}

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
