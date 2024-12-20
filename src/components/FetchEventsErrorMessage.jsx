import {
	ExternalLink,
	Notice,
	__experimentalText as Text,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

export function FetchEventsErrorMessage( props ) {
	const { eventsLoadingHasErrors, eventsLoadingErrorDetails } = props;

	if ( ! eventsLoadingHasErrors ) {
		return null;
	}

	const supportURL = addQueryArgs(
		'https://simple-history.com/support/load-events-error/',
		{
			utm_source: 'wpadmin',
			utm_content: 'fech-events-error-message',
			error: JSON.stringify( eventsLoadingErrorDetails ),
		}
	);

	return (
		<div
			style={ {
				margin: '1rem',
			} }
		>
			<Notice status="warning" isDismissible={ false }>
				<Text as="p">
					{ __(
						'There was an error loading the events. Please try again later.',
						'simple-history'
					) }
				</Text>

				<details open>
					<summary
						style={ {
							marginTop: '.5rem',
						} }
					>
						{ __( 'View error details', 'simple-history' ) }
					</summary>

					<Text
						as="pre"
						style={ {
							padding: '1rem',
						} }
					>
						{ JSON.stringify( eventsLoadingErrorDetails, null, 2 ) }
					</Text>

					<Text as="p">
						<ExternalLink href={ supportURL }>
							Search for error and solutions online.
						</ExternalLink>
					</Text>

					<Text variant="muted" as="p">
						Error above will be sent to simple-history.com. Make
						sure it don&apos;t contain any personal or sensitive
						information.
					</Text>
				</details>
			</Notice>
		</div>
	);
}
