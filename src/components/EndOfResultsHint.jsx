import { ExternalLink } from '@wordpress/components';
import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getTrackingUrl } from '../functions';
import { useEventsSettings } from './EventsSettingsContext';

/**
 * Generic "you've reached the end" nudge shown at the bottom of the result
 * list on the last page. Adds a premium-retention upsell for non-premium
 * users — the last page is the natural moment to surface "looking for
 * older events? Premium stores them for longer."
 *
 * Deliberately dumb by design: makes no claim about whether older matching
 * events actually exist outside the current window. The user knows they're
 * looking; the hint just reminds them of the available options.
 *
 * Always renders on the last page with results — even when the active date
 * is the server's "default" (e.g. "Last 30 days"). A default date is still
 * a date filter the user can widen.
 *
 * The "Adjust the filters above" line is gated on `canAdjustFilters`:
 * showing it when the date is already "All dates" and no other filters
 * are active would point the user at filters they haven't set.
 *
 * @param {Object}  props
 * @param {boolean} props.canAdjustFilters Whether at least one non-date filter
 *                                         is active AND the date is not "All dates".
 * @return {JSX.Element} Hint element.
 */
export function EndOfResultsHint( { canAdjustFilters } ) {
	const { hasPremiumAddOn } = useEventsSettings();

	return (
		<div className="sh-EndOfResultsHint">
			<p className="sh-EndOfResultsHint__line">
				{ canAdjustFilters
					? __(
							"You've reached the end of the matching events. Adjust the filters above if you didn't find what you were looking for.",
							'simple-history'
					  )
					: __(
							"You've reached the end of the matching events.",
							'simple-history'
					  ) }
			</p>

			{ ! hasPremiumAddOn && (
				<p className="sh-EndOfResultsHint__line">
					{ createInterpolateElement(
						__(
							'Looking for older events? <a>Simple History Premium</a> stores events for longer.',
							'simple-history'
						),
						{
							a: (
								<ExternalLink
									href={ getTrackingUrl(
										'https://simple-history.com/add-ons/premium/',
										'premium_events_endofresults'
									) }
								/>
							),
						}
					) }
				</p>
			) }
		</div>
	);
}
