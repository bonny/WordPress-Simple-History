import { Notice, __experimentalText as Text } from '@wordpress/components';
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

	return (
		<div
			style={ {
				margin: '1rem',
			} }
		>
			<Notice status="warning" isDismissible={ false }>
				<Text as="p">
					{ __(
						'Your search did not match any history events.',
						'simple-history'
					) }
				</Text>
			</Notice>
		</div>
	);
}
