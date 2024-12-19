import { Notice, __experimentalText as Text } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export function FetchEventsErrorMessage( props ) {
	const { eventsLoadingHasErrors, eventsLoadingErrorDetails } = props;

	if ( ! eventsLoadingHasErrors ) {
		return null;
	}

	return (
		<div
			style={ {
				margin: '1rem',
			} }
		>
			<Notice status="warning" isDismissible={ false }>
				<Text>
					{ __(
						'There was an error loading the events. Please try again later.',
						'simple-history'
					) }
				</Text>

				<details>
					<summary
						style={ {
							marginTop: '.5rem',
						} }
					>
						{ __( 'View error details', 'simple-history' ) }
					</summary>

					<pre>
						{ JSON.stringify( eventsLoadingErrorDetails, null, 2 ) }
					</pre>
				</details>
			</Notice>
		</div>
	);
}
