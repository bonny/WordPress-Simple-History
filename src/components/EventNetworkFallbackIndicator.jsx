import { __ } from '@wordpress/i18n';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Small explanatory note shown on events that were actually performed at
 * the network level (super admin actions on a multisite Network Admin
 * screen) but ended up in the per-site log because no dedicated network
 * log was available at log time.
 *
 * Helps users understand why a network-level action appears here, and
 * points them to where it would otherwise live. The REST controller
 * gates the `network_fallback` flag on Premium being inactive, so once
 * Premium is installed (and its network module is running) the note
 * disappears from all previously-flagged events — the dedicated log
 * takes over and the explanation is no longer useful.
 *
 * @param {Object} props
 * @param {Object} props.event
 */
export function EventNetworkFallbackIndicator( props ) {
	const { event } = props;

	if ( ! event.network_fallback ) {
		return null;
	}

	const upgradeUrl =
		'https://simple-history.com/premium/?utm_source=wpadmin&utm_medium=event-badge&utm_campaign=network-fallback';

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__networkFallback">
			<a
				href={ upgradeUrl }
				target="_blank"
				rel="noopener noreferrer"
				title={ __(
					'This action happened at the network level. Simple History Premium has a dedicated network log where events like this would normally live.',
					'simple-history'
				) }
			>
				{ __( 'Network-level action →', 'simple-history' ) }
			</a>
		</EventHeaderItem>
	);
}
