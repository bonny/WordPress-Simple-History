import { __ } from '@wordpress/i18n';

/**
 * Preview of the premium Table view, shown when nothing fills the
 * SimpleHistorySlotTableView Slot.
 *
 * This component deliberately contains no table logic: no sorting, no
 * selection, no data fetching. It is a picture of a feature that lives in
 * Simple History Premium, in the same spirit as the alerts settings teaser.
 * Keep it that way — shipping working-but-switched-off code in the free
 * plugin is what the WordPress.org guidelines call trialware.
 */
export function TablePreview() {
	return (
		<div className="sh-TablePreview">
			<p>
				{ __(
					'Table view is part of Simple History Premium.',
					'simple-history'
				) }
			</p>
		</div>
	);
}
