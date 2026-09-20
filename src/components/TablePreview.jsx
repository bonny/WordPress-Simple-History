import { Icon } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { table } from '@wordpress/icons';

// Staged sample rows. Deliberately not the reader's own events: a quiet site
// previews badly, and the point of the preview is to show the table doing the
// things it is good at — sorted, with rows picked out for export.
//
// The mix follows the wp-org-screenshots skill: a spread of loggers and levels,
// one warning to give the Level column something to say, and plausible names
// rather than placeholders. Two rows are pre-selected so the bulk bar below has
// a reason to read "2 selected".
const SAMPLE_ROWS = [
	{
		id: 1,
		selected: true,
		date: '12:04:11',
		user: 'anna',
		message: __( 'Updated post "Pricing"', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 2,
		selected: true,
		date: '12:03:58',
		user: 'anna',
		message: __( 'Updated the option "blogdescription"', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 3,
		selected: false,
		date: '11:59:02',
		user: '—',
		message: __(
			'Failed to login with username "admin"',
			'simple-history'
		),
		level: __( 'Warning', 'simple-history' ),
	},
	{
		id: 4,
		selected: false,
		date: '11:47:20',
		user: 'jonas',
		message: __(
			'Changed role for user "sara" to Editor',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 5,
		selected: false,
		date: '11:12:44',
		user: 'WP-CLI',
		message: __(
			'Updated plugin "WooCommerce" to 9.4.2',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 6,
		selected: false,
		date: '10:58:06',
		user: 'jonas',
		message: __(
			'Uploaded attachment "hero-autumn.jpg"',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 7,
		selected: false,
		date: '09:30:15',
		user: 'WordPress',
		message: __( 'Updated WordPress to 6.9.1', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
];

// Derived from SAMPLE_ROWS rather than hardcoded, so the bulk bar's count
// can't drift from which rows actually carry selected: true.
const SELECTED_ROWS_COUNT = SAMPLE_ROWS.filter(
	( row ) => row.selected
).length;

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

// What Premium adds here, in the order the page would show it. Named rather
// than only pictured: the chart and the query box above the table say what
// they are to someone who already knows what they are looking at, and this
// list is for everyone else.
const PREVIEW_FEATURES = [
	__( 'Query language', 'simple-history' ),
	__( 'Activity chart', 'simple-history' ),
	__( 'Saved views', 'simple-history' ),
	__( 'Pick your columns', 'simple-history' ),
	__( 'Group and count', 'simple-history' ),
	__( 'CSV and JSON export', 'simple-history' ),
];

/**
 * Preview of the premium Table view, shown when nothing fills the
 * SimpleHistorySlotTableView Slot.
 *
 * This component contains no table logic on purpose: no sorting, no selection,
 * no data fetching, no TanStack import. It is a picture of a feature that lives
 * in Simple History Premium, in the same spirit as the alerts settings teaser.
 * Keep it that way — shipping working-but-switched-off code in the free plugin
 * is what the WordPress.org guidelines call trialware.
 */
export function TablePreview() {
	const upgradeUrl = window.simpleHistoryReactData?.tableViewUpgradeUrl;

	return (
		<div className="sh-TablePreview">
			<div className="sh-TablePreview__banner">
				<Icon icon={ table } size={ 24 } aria-hidden="true" />

				<div className="sh-TablePreview__banner-content">
					<span className="sh-TablePreview__banner-title">
						{ __(
							'Sort, select and export your log',
							'simple-history'
						) }
						<span className="sh-Badge sh-Badge--premium">
							{ __( 'Premium', 'simple-history' ) }
						</span>
					</span>

					<span>
						{ __(
							'See your events in a table you can sort, filter with a query language, chart by hour, and export to CSV or JSON.',
							'simple-history'
						) }
					</span>

					<ul className="sh-TablePreview__features">
						{ PREVIEW_FEATURES.map( ( feature ) => (
							<li key={ feature }>{ feature }</li>
						) ) }
					</ul>

					{ upgradeUrl ? (
						<a
							href={ upgradeUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Upgrade to Premium', 'simple-history' ) } →
						</a>
					) : null }
				</div>
			</div>

			{ /* Said in words as well as drawn, because the picture below is
			     a good enough likeness of the real table that readers try to
			     use it — click a column header, tick a row — and get nothing
			     back. Outside the muted block so it is not dimmed with it,
			     and not aria-hidden: the sample is hidden from screen
			     readers, so this sentence is all they get about it. */ }
			<p className="sh-TablePreview__sampleNote">
				{ __(
					'Example of the table view, with made-up data. Nothing below is clickable.',
					'simple-history'
				) }
			</p>

			{ /* Everything below is decoration, not data: hidden from screen
			     readers, which get the banner above instead, and unreachable
			     by the mouse. Dimmed as one block, the same way the alerts
			     settings teaser dims its preview, so the two read as the same
			     idea rather than two different kinds of not-quite-real. */ }
			<div className="sh-TablePreview__sample">
				<div className="sh-TablePreview__query" aria-hidden="true">
					<span className="sh-TablePreview__queryText">
						level:error days:7 deploy
					</span>
					<span className="sh-TablePreview__queryApply">
						{ __( 'Apply', 'simple-history' ) }
					</span>
				</div>

				<div className="sh-TablePreview__chart" aria-hidden="true">
					{ SAMPLE_CHART.map( ( bar, index ) => (
						<span
							// Position is the identity here: these are fourteen
							// fixed slots on a chart, not a list that reorders.
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

				<table className="sh-TablePreview__table" aria-hidden="true">
					<thead>
						<tr>
							<th>
								<input type="checkbox" disabled />
							</th>
							<th className="is-sorted">
								{ __( 'Date', 'simple-history' ) } ↓
							</th>
							<th>{ __( 'User', 'simple-history' ) }</th>
							<th>{ __( 'Message', 'simple-history' ) }</th>
							<th>{ __( 'Level', 'simple-history' ) }</th>
						</tr>
					</thead>

					<tbody>
						{ SAMPLE_ROWS.map( ( row ) => (
							<tr key={ row.id }>
								<td>
									<input
										type="checkbox"
										disabled
										defaultChecked={ row.selected }
									/>
								</td>
								<td>{ row.date }</td>
								<td>{ row.user }</td>
								<td>{ row.message }</td>
								<td>{ row.level }</td>
							</tr>
						) ) }
					</tbody>
				</table>

				<div className="sh-TablePreview__bulkBar" aria-hidden="true">
					{ sprintf(
						/* translators: %s: number of selected rows */
						_n(
							'%s selected',
							'%s selected',
							SELECTED_ROWS_COUNT,
							'simple-history'
						),
						SELECTED_ROWS_COUNT
					) }{ ' ' }
					· { __( 'Export', 'simple-history' ) } ▾
				</div>
			</div>
		</div>
	);
}
