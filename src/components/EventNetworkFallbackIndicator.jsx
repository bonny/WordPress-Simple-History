import { __ } from '@wordpress/i18n';
import { Tooltip } from '@wordpress/components';
import { EventHeaderItem } from './EventHeaderItem';

/**
 * Discreet per-row marker on events that were actually performed at the
 * network level (super-admin actions on a multisite Network Admin screen)
 * but ended up in the per-site log because no dedicated network log was
 * available.
 *
 * Rendered as muted metadata text matching the style of other header
 * items (date, via, IP). The prominent call-to-action lives in the
 * summary line above the events list — we don't want a CTA on every row.
 *
 * The REST controller gates the `network_fallback` flag on Premium
 * being inactive, so once Premium is installed (and its network module
 * is running) the marker disappears from all previously-flagged events.
 *
 * @param {Object} props
 * @param {Object} props.event
 */
export function EventNetworkFallbackIndicator( props ) {
	const { event } = props;

	if ( ! event.network_fallback ) {
		return null;
	}

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__networkFallback">
			<Tooltip
				text={ __(
					'This action happened at the network level.',
					'simple-history'
				) }
			>
				<span>{ __( 'network-level', 'simple-history' ) }</span>
			</Tooltip>
		</EventHeaderItem>
	);
}
