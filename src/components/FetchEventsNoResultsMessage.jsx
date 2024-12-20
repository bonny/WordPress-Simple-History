import { Icon, __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function FetchEventsNoResultsMessage( props ) {
	const { eventsIsLoading, events } = props;

	// Bail if loading.
	if ( eventsIsLoading ) {
		return null;
	}

	// Bail if there are events.
	if ( events.length && events.length > 0 ) {
		return null;
	}

	// Icon = "Search Off".
	// https://fonts.google.com/icons?selected=Material+Symbols+Outlined:search_off:FILL@0;wght@400;GRAD@0;opsz@24&icon.query=search&icon.size=24&icon.color=%23000000&icon.platform=web
	const icon = (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			height="24px"
			viewBox="0 -960 960 960"
			width="24px"
			fill="#000000"
		>
			<path d="M280-80q-83 0-141.5-58.5T80-280q0-83 58.5-141.5T280-480q83 0 141.5 58.5T480-280q0 83-58.5 141.5T280-80Zm544-40L568-376q-12-13-25.5-26.5T516-428q38-24 61-64t23-88q0-75-52.5-127.5T420-760q-75 0-127.5 52.5T240-580q0 6 .5 11.5T242-557q-18 2-39.5 8T164-535q-2-11-3-22t-1-23q0-109 75.5-184.5T420-840q109 0 184.5 75.5T680-580q0 43-13.5 81.5T629-428l251 252-56 56Zm-615-61 71-71 70 71 29-28-71-71 71-71-28-28-71 71-71-71-28 28 71 71-71 71 28 28Z" />
		</svg>
	);

	const containerStyles = {
		marginBlock: '2rem',
		display: 'flex',
		flexDirection: 'column',
		alignItems: 'center',
	};

	const pStyles = {
		fontSize: '1rem',
		fontWeight: '500',
		marginBlock: '.25rem',
		color: 'var(--sh-color-gray-2)',
	};

	return (
		<div style={ containerStyles }>
			<Icon
				icon={ icon }
				fill="var(--sh-color-gray-2)"
				size={ 50 }
				style={ {
					marginBottom: '2rem',
				} }
			/>

			<Text as="p" style={ pStyles }>
				{ __(
					'Your search did not match any history events.',
					'simple-history'
				) }
			</Text>

			<Text as="p" style={ pStyles }>
				{ __(
					'Try different search options or clear the search.',
					'simple-history'
				) }
			</Text>
		</div>
	);
}
