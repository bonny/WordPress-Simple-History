import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
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
	const upgradeUrl = window.simpleHistoryReactData?.tableViewUpgradeUrl ?? '';

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
							'See your events in a table you can sort, select rows from, and export to CSV or JSON.',
							'simple-history'
						) }
					</span>

					<a href={ upgradeUrl }>
						{ __( 'Upgrade to Premium', 'simple-history' ) } →
					</a>
				</div>
			</div>

			{ /* Decoration, not data: hidden from screen readers, which get the
			     banner above instead, and unreachable by the mouse. */ }
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
				{ __( '2 selected', 'simple-history' ) } ·{ ' ' }
				{ __( 'Export', 'simple-history' ) } ▾
			</div>
		</div>
	);
}
