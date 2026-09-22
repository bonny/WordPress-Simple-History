import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

// Staged sample rows. Deliberately not the reader's own events: a quiet site
// previews badly, and the point of the preview is to show the table doing the
// things it is good at.
//
// The mix follows the wp-org-screenshots skill: a spread of loggers and levels,
// a failed login to give the Level column something to say, and plausible
// names rather than placeholders. Newest first, because the Date header says
// the table is sorted that way.
//
// Every message and level is copied from what the real loggers write for that
// event, so this is a picture of the log and not an idea of one. Check the
// logger's messages array before changing one.
//
// The top two rows are the same page saved by two people, and both are ticked,
// so the toolbar shows Compare: the question the table answers best is "what
// did the second save change?".
const SAMPLE_ROWS = [
	{
		id: 16991,
		selected: true,
		date: '2026-09-22 11:32:53',
		relative: __( 'a minute ago', 'simple-history' ),
		user: 'anna',
		message: __( 'Updated page "Pricing"', 'simple-history' ),
		level: 'info',
	},
	{
		id: 16987,
		selected: true,
		date: '2026-09-22 09:03:58',
		relative: __( '2 hours ago', 'simple-history' ),
		user: 'jonas',
		message: __( 'Updated page "Pricing"', 'simple-history' ),
		level: 'info',
	},
	{
		id: 16940,
		date: '2026-09-19 13:47:20',
		relative: __( '3 days ago', 'simple-history' ),
		user: 'jonas',
		message: __(
			'Uploaded attachment "hero-autumn.jpg"',
			'simple-history'
		),
		level: 'info',
	},
	{
		id: 16921,
		date: '2026-09-19 02:14:07',
		relative: __( '3 days ago', 'simple-history' ),
		user: '',
		message: __(
			'Failed to login with username "admin" (username does not exist)',
			'simple-history'
		),
		level: 'warning',
	},
	{
		id: 16803,
		date: '2026-09-17 14:02:31',
		relative: __( '5 days ago', 'simple-history' ),
		user: 'sara',
		message: __(
			'Updated setting "Site Title" on the "General" settings page',
			'simple-history'
		),
		level: 'info',
	},
	{
		id: 16790,
		date: '2026-09-17 10:12:44',
		relative: __( '5 days ago', 'simple-history' ),
		user: 'WP-CLI',
		message: __(
			'Updated plugin "WooCommerce" to version 9.4.2 from 9.3.3',
			'simple-history'
		),
		level: 'info',
	},
	{
		id: 16788,
		date: '2026-09-17 10:11:02',
		relative: __( '5 days ago', 'simple-history' ),
		user: 'jonas',
		message: __(
			'Deactivated plugin "Wordfence Security"',
			'simple-history'
		),
		level: 'info',
	},
];

// Initials-avatar colours for the sample users. An empty user is an
// anonymous request, like the failed login.
const AVATAR_COLORS = {
	anna: '#9b51e0',
	jonas: '#2271b1',
	sara: '#b32d2e',
	'WP-CLI': '#50575e',
	'': '#a7aaad',
};

const LEVEL_LABELS = {
	info: __( 'Info', 'simple-history' ),
	warning: __( 'Warning', 'simple-history' ),
};

// The staged activity chart above the sample table: bar height in percent,
// then how much of that height is warnings or worse.
//
// Hand-written rather than generated, and forty bars rather than a dozen. It
// has to look like real hours — two quiet nights, a working-day rise and
// fall, and one spike with a band of red where something went wrong — because
// that shape is the whole reason to want the chart. A random sequence reads
// as noise, and a handful of fat bars reads as a diagram of nothing.
//
// Plain divs, not Chart.js. The preview must stay a picture (see the note on
// the component below), and forty fixed numbers are a picture whether or not
// a charting library draws them.
const SAMPLE_CHART = [
	[ 12, 0 ],
	[ 8, 0 ],
	[ 5, 0 ],
	[ 9, 0 ],
	[ 22, 0 ],
	[ 41, 0 ],
	[ 58, 0 ],
	[ 47, 0 ],
	[ 52, 9 ],
	[ 44, 0 ],
	[ 61, 0 ],
	[ 39, 0 ],
	[ 26, 0 ],
	[ 14, 0 ],
	[ 9, 0 ],
	[ 6, 0 ],
	[ 11, 0 ],
	[ 19, 0 ],
	[ 37, 0 ],
	[ 55, 0 ],
	[ 92, 24 ],
	[ 71, 12 ],
	[ 48, 0 ],
	[ 40, 0 ],
	[ 33, 0 ],
	[ 21, 0 ],
	[ 12, 0 ],
	[ 7, 0 ],
	[ 5, 0 ],
	[ 10, 0 ],
	[ 24, 0 ],
	[ 45, 0 ],
	[ 63, 0 ],
	[ 57, 7 ],
	[ 49, 0 ],
	[ 36, 0 ],
	[ 28, 0 ],
	[ 17, 0 ],
	[ 10, 0 ],
	[ 6, 0 ],
].map( ( [ height, severe ] ) => ( { height, severe } ) );

// The week the chart covers. Formatted with the site's locale rather than
// written as English strings, so a translator never has to rewrite a date.
const SAMPLE_CHART_AXIS = [
	'2026-09-15',
	'2026-09-17',
	'2026-09-19',
	'2026-09-22',
].map( ( date ) => dateI18n( 'M j', date ) );

// The sample's numbers, kept together so they add up: info plus warnings is
// the total, and the busiest hour is the tallest bar on the chart.
const SAMPLE_COUNTS = {
	info: 14681,
	warnings: 31,
	severe: 4,
	total: 14716,
	busiestHour: dateI18n( 'M j, H:i', '2026-09-18 14:00' ),
	busiestHourEvents: 1174,
	loggedInPercent: 99,
};

const formatNumber = ( number ) => number.toLocaleString();

// What Premium adds here, as things the reader gets done rather than
// feature names. One more than the premium-upsell-design skill's four,
// because Compare earns its place: nothing in the free plugin does it.
const PREVIEW_FEATURES = [
	sprintf(
		/* translators: %s: an example search, like "days:7". */
		__( 'Search with filters like %s', 'simple-history' ),
		'days:7'
	),
	__( 'Quickly see spikes in activity', 'simple-history' ),
	__( 'Compare two events side by side', 'simple-history' ),
	__( 'Save the views you check often', 'simple-history' ),
	__( 'Export to CSV or JSON', 'simple-history' ),
];

function CheckIcon() {
	return (
		<svg
			className="sh-TablePreview__check"
			width="18"
			height="18"
			viewBox="0 0 20 20"
			aria-hidden="true"
			focusable="false"
		>
			<path d="M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z" />
		</svg>
	);
}

/**
 * Preview of the premium Table view, shown when nothing fills the
 * SimpleHistorySlotTableView Slot.
 *
 * Laid out like a screenshot: the sample table fills the view in full colour
 * and fades out into one premium card at the bottom. The card is what says
 * this is a preview, so the sample no longer has to be dimmed to say it.
 *
 * This component contains no table logic on purpose: no sorting, no selection,
 * no data fetching, no TanStack import. It is a picture of a feature that lives
 * in Simple History Premium, in the same spirit as the alerts settings teaser.
 * Keep it that way — shipping working-but-switched-off code in the free plugin
 * is what the WordPress.org guidelines call trialware.
 *
 * @param {Object}   props
 * @param {Function} props.onBackToList Switches back to the list view.
 */
export function TablePreview( { onBackToList } ) {
	const upgradeUrl = window.simpleHistoryReactData?.tableViewUpgradeUrl;

	return (
		<div className="sh-TablePreview">
			{ /* Everything in here is decoration, not data: hidden from
			     screen readers, which get the card below instead, and out of
			     reach of the mouse. */ }
			<div className="sh-TablePreview__sample" aria-hidden="true">
				<span className="sh-TablePreview__ribbon">
					{ __( 'Example · sample data', 'simple-history' ) }
				</span>

				<div className="sh-TablePreview__query">
					<span className="sh-TablePreview__queryText">days:7</span>
					<span className="sh-TablePreview__queryApply">
						{ __( 'Apply', 'simple-history' ) }
					</span>
				</div>

				<div className="sh-TablePreview__chart">
					{ SAMPLE_CHART.map( ( bar, index ) => (
						<span
							// Position is the identity here: these are fixed
							// slots on a chart, not a list that reorders.
							// eslint-disable-next-line react/no-array-index-key
							key={ index }
							className="sh-TablePreview__chartBar"
							style={ { height: `${ bar.height }%` } }
						>
							{ bar.severe > 0 && (
								<span
									className="sh-TablePreview__chartBar-severe"
									style={ {
										height: `${ Math.round(
											( bar.severe / bar.height ) * 100
										) }%`,
									} }
								/>
							) }
						</span>
					) ) }
				</div>

				<div className="sh-TablePreview__axis">
					{ SAMPLE_CHART_AXIS.map( ( label ) => (
						<span key={ label }>{ label }</span>
					) ) }
				</div>

				<div className="sh-TablePreview__legend">
					<span>
						<span className="sh-TablePreview__swatch" />
						{ sprintf(
							/* translators: %s: number of events */
							__( 'Info or lower %s', 'simple-history' ),
							formatNumber( SAMPLE_COUNTS.info )
						) }
					</span>
					<span>
						<span className="sh-TablePreview__swatch is-severe" />
						{ sprintf(
							/* translators: %s: number of events */
							__( 'Warning or higher %s', 'simple-history' ),
							formatNumber(
								SAMPLE_COUNTS.warnings + SAMPLE_COUNTS.severe
							)
						) }
					</span>
					<span>
						{ sprintf(
							/* translators: 1: date and hour, 2: number of events */
							__(
								'Busiest hour: %1$s, %2$s events',
								'simple-history'
							),
							SAMPLE_COUNTS.busiestHour,
							formatNumber( SAMPLE_COUNTS.busiestHourEvents )
						) }
					</span>
				</div>

				<div className="sh-TablePreview__summary">
					<span className="sh-TablePreview__count">
						{ sprintf(
							/* translators: %s: number of events */
							__( '%s events', 'simple-history' ),
							formatNumber( SAMPLE_COUNTS.total )
						) }
					</span>
					<span className="sh-TablePreview__warnings">
						{ sprintf(
							/* translators: 1: number of warnings, 2: number of more severe events */
							__(
								'%1$s warnings, %2$s severe',
								'simple-history'
							),
							formatNumber( SAMPLE_COUNTS.warnings ),
							formatNumber( SAMPLE_COUNTS.severe )
						) }
					</span>
					<span>
						·{ ' ' }
						{ sprintf(
							/* translators: %s: share of events made by logged-in users, like "99%". */
							__( '%s logged-in users', 'simple-history' ),
							`${ SAMPLE_COUNTS.loggedInPercent }%`
						) }
					</span>
					<span className="sh-TablePreview__menus">
						<span className="sh-TablePreview__selected">
							{ sprintf(
								/* translators: %s: number of selected events */
								__( '%s selected', 'simple-history' ),
								formatNumber( 2 )
							) }
						</span>
						<span className="sh-TablePreview__button">
							{ __( 'Compare', 'simple-history' ) }
						</span>
						<span className="sh-TablePreview__button">
							{ __( 'Export selected', 'simple-history' ) }
						</span>
						<span>{ __( 'Views', 'simple-history' ) } ▾</span>
						<span>{ __( 'Columns', 'simple-history' ) } ▾</span>
					</span>
				</div>

				<table className="sh-TablePreview__table">
					<thead>
						<tr>
							<th />
							<th>
								<input type="checkbox" disabled />
							</th>
							<th>{ __( 'ID', 'simple-history' ) }</th>
							<th>{ __( 'Date', 'simple-history' ) } ▾</th>
							<th>{ __( 'Relative date', 'simple-history' ) }</th>
							<th>{ __( 'User', 'simple-history' ) }</th>
							<th>{ __( 'Message', 'simple-history' ) }</th>
							<th>{ __( 'Level', 'simple-history' ) }</th>
							<th>{ __( 'Actions', 'simple-history' ) }</th>
						</tr>
					</thead>

					<tbody>
						{ SAMPLE_ROWS.map( ( row ) => (
							<tr
								key={ row.id }
								className={
									row.selected ? 'is-selected' : undefined
								}
							>
								<td className="is-muted">›</td>
								<td>
									<input
										type="checkbox"
										disabled
										defaultChecked={ row.selected }
									/>
								</td>
								<td className="is-muted">{ row.id }</td>
								<td>{ row.date }</td>
								<td className="is-muted">{ row.relative }</td>
								<td>
									<span className="sh-TablePreview__user">
										<span
											className="sh-TablePreview__avatar"
											style={ {
												background:
													AVATAR_COLORS[ row.user ],
											} }
										>
											{ row.user
												? row.user
														.charAt( 0 )
														.toUpperCase()
												: '?' }
										</span>
										{ row.user || '—' }
									</span>
								</td>
								<td className="sh-TablePreview__message">
									{ row.message }
								</td>
								<td>
									<span
										className={ `sh-TablePreview__level is-${ row.level }` }
									>
										{ LEVEL_LABELS[ row.level ] }
									</span>
								</td>
								<td className="is-muted">•••</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="sh-TablePreview__fade" aria-hidden="true" />

			<div className="sh-TablePreview__card">
				<p className="sh-TablePreview__title">
					{ __(
						'Sort, filter, compare and export your log',
						'simple-history'
					) }
					<span className="sh-Badge sh-Badge--premium">
						{ __( 'Premium', 'simple-history' ) }
					</span>
				</p>

				<p className="sh-TablePreview__text">
					{ __(
						'With Premium, your own log opens like this, with every column sortable.',
						'simple-history'
					) }
				</p>

				<ul className="sh-TablePreview__features">
					{ PREVIEW_FEATURES.map( ( feature ) => (
						<li key={ feature }>
							<CheckIcon />
							{ feature }
						</li>
					) ) }
				</ul>

				<div className="sh-TablePreview__actions">
					{ upgradeUrl ? (
						<a
							className="sh-TablePreview__cta"
							href={ upgradeUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __(
								'Get the table view with Premium',
								'simple-history'
							) }{ ' ' }
							→
						</a>
					) : null }

					{ onBackToList ? (
						<button
							type="button"
							className="button-link sh-TablePreview__back"
							onClick={ onBackToList }
						>
							{ __( 'Back to list view', 'simple-history' ) }
						</button>
					) : null }
				</div>

				{ /* The table is one part of Premium. Named quietly here so
				     the reader weighing it up knows what else comes with it. */ }
				<p className="sh-TablePreview__also">
					{ __(
						'Also in Premium: keep your log for a year or longer, alerts by email and Slack, and log forwarding.',
						'simple-history'
					) }
				</p>
			</div>
		</div>
	);
}
