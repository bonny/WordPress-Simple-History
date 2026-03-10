import { Button, Icon, __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function FetchEventsNoResultsMessage( props ) {
	const { eventsIsLoading, events, hasActiveFilters, onClearFilters } = props;

	if ( eventsIsLoading ) {
		return null;
	}

	if ( events.length && events.length > 0 ) {
		return null;
	}

	// "Search" icon (plain magnifying glass).
	// https://fonts.google.com/icons?selected=Material+Symbols+Outlined:search
	const icon = (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			height="24px"
			viewBox="0 -960 960 960"
			width="24px"
		>
			<path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z" />
		</svg>
	);

	const containerStyles = {
		margin: '1.5rem 0 2rem 1.5rem',
		display: 'flex',
		flexDirection: 'row',
		alignItems: 'flex-start',
		gap: '0.75rem',
	};

	return (
		<div style={ containerStyles } role="status" aria-live="polite">
			<Icon
				icon={ icon }
				fill="var(--sh-color-gray-2)"
				size={ 32 }
				style={ { flexShrink: 0, marginTop: '2px' } }
			/>

			<div>
				<Text
					as="p"
					style={ {
						fontSize: '1rem',
						fontWeight: '600',
						marginBlock: '0 .25rem',
						color: 'var(--sh-color-gray-2)',
					} }
				>
					{ __( 'No matching events', 'simple-history' ) }
				</Text>

				<Text
					as="p"
					style={ {
						fontSize: '0.9rem',
						fontWeight: '400',
						marginBlock: '.25rem',
						color: 'var(--sh-color-gray-2)',
					} }
				>
					{ __(
						'Adjust the date range or search terms, or clear all filters to see everything.',
						'simple-history'
					) }
				</Text>

				{ hasActiveFilters && onClearFilters && (
					<Button
						variant="secondary"
						onClick={ onClearFilters }
						style={ { marginTop: '0.5rem' } }
					>
						{ __( 'Clear filters', 'simple-history' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
