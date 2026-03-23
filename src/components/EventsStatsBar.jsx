import { createInterpolateElement } from '@wordpress/element';
import { _n, sprintf } from '@wordpress/i18n';
import { Icon, chartBar } from '@wordpress/icons';

/**
 * Stats bar showing events today and last 7 days.
 * Displayed above the search/filter row.
 *
 * @param {Object} props
 * @param {Object} props.stats      Stats object with num_events_today and num_events_last_7_days.
 * @param {string} props.statsPageURL URL to the stats page.
 */
export function EventsStatsBar( { stats, statsPageURL } ) {
	if ( ! stats ) {
		return null;
	}

	const content = (
		<>
			<Icon icon={ chartBar } size={ 16 } />
			<span>
				{ createInterpolateElement(
					sprintf(
						/* translators: 1: number of events today */
						_n(
							'<strong>%s</strong> event today',
							'<strong>%s</strong> events today',
							stats.num_events_today,
							'simple-history'
						),
						stats.num_events_today
					),
					{ strong: <strong /> }
				) }
			</span>
			<span className="sh-EventsStatsBar__separator">&middot;</span>
			<span>
				{ createInterpolateElement(
					sprintf(
						/* translators: 1: number of events last 7 days */
						_n(
							'<strong>%s</strong> last 7 days',
							'<strong>%s</strong> last 7 days',
							stats.num_events_last_7_days,
							'simple-history'
						),
						stats.num_events_last_7_days
					),
					{ strong: <strong /> }
				) }
			</span>
		</>
	);

	if ( statsPageURL ) {
		return (
			<a
				href={ statsPageURL }
				className="sh-EventsStatsBar sh-EventsStatsBar--link"
			>
				{ content }
			</a>
		);
	}

	return <div className="sh-EventsStatsBar">{ content }</div>;
}
