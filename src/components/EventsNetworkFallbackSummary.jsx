import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Single-line summary shown above the events list when one or more
 * events in the current view originated at the network level.
 *
 * Replaces the per-row CTA. Per-row surfaces carry only a subtle
 * "network-level" label (see EventNetworkFallbackIndicator); the
 * explanation and the upgrade link live here, once, at the top of
 * the view.
 *
 * Renders nothing when no events carry the flag.
 *
 * @param {Object} props
 * @param {Array}  props.events The events currently in the view.
 */
export function EventsNetworkFallbackSummary( props ) {
	const { events } = props;

	if ( ! Array.isArray( events ) || events.length === 0 ) {
		return null;
	}

	const count = events.filter(
		( event ) => event && event.network_fallback === true
	).length;

	if ( count === 0 ) {
		return null;
	}

	const upgradeUrl =
		'https://simple-history.com/premium/?utm_source=wpadmin&utm_medium=event-log-summary&utm_campaign=network-fallback';

	const label = sprintf(
		/* translators: %d: count of network-level events in the current view. */
		_n(
			'%d of these events happened at the network level.',
			'%d of these events happened at the network level.',
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
				href={ upgradeUrl }
				target="_blank"
				rel="noopener noreferrer"
			>
				{ __(
					'Premium adds a dedicated network log →',
					'simple-history'
				) }
			</a>
		</p>
	);
}
