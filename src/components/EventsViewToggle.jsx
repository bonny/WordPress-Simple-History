import { Icon, Tooltip, VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { menu, postList } from '@wordpress/icons';
import { clsx } from 'clsx';

/**
 * Switch between the detailed and the compact event list.
 *
 * Native radio inputs give arrow-key navigation and screen reader grouping
 * without any focus management of our own; the inputs are visually hidden
 * and their labels render as icon buttons.
 *
 * @param {Object}   props
 * @param {string}   props.view     Current view, "detailed" or "compact".
 * @param {Function} props.onChange Called with the newly chosen view.
 */
export function EventsViewToggle( { view, onChange } ) {
	const options = [
		{
			value: 'detailed',
			label: __( 'Detailed view', 'simple-history' ),
			icon: postList,
		},
		{
			value: 'compact',
			label: __( 'Compact view', 'simple-history' ),
			icon: menu,
		},
	];

	return (
		<fieldset className="sh-EventsViewToggle">
			<VisuallyHidden as="legend">
				{ __( 'Event list view', 'simple-history' ) }
			</VisuallyHidden>

			{ options.map( ( option ) => {
				const isChecked = view === option.value;

				const inputId = `sh-events-view-${ option.value }`;

				return (
					<Tooltip key={ option.value } text={ option.label }>
						<label
							htmlFor={ inputId }
							className={ clsx( 'sh-EventsViewToggle__option', {
								'is-checked': isChecked,
							} ) }
						>
							<input
								id={ inputId }
								type="radio"
								name="sh-events-view"
								value={ option.value }
								checked={ isChecked }
								onChange={ () => onChange( option.value ) }
								aria-label={ option.label }
								className="sh-EventsViewToggle__input"
							/>
							<Icon icon={ option.icon } />
						</label>
					</Tooltip>
				);
			} ) }
		</fieldset>
	);
}
