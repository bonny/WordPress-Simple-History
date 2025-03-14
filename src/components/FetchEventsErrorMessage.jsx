import {
	Button,
	ExternalLink,
	Notice,
	__experimentalText as Text,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

export function FetchEventsErrorMessage( props ) {
	const { eventsLoadingHasErrors, eventsLoadingErrorDetails } = props;

	if ( ! eventsLoadingHasErrors ) {
		return null;
	}

	const supportURL = addQueryArgs(
		'https://simple-history.com/support/load-events-error/',
		{
			utm_source: 'wordpress_admin',
			utm_medium: 'Simple_History',
			utm_campaign: 'premium_upsell',
			utm_content: 'fetch-events-error',
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
				<VStack spacing={ 2 }>
					<Text as="p">
						{ __(
							'There was an error loading the events.',
							'simple-history'
						) }
					</Text>

					<Text as="p">
						{ __(
							'This can often be resolved by refreshing your browser. If the problem persists, please try again later.',
							'simple-history'
						) }
					</Text>

					<Text as="p">
						<Button
							variant="secondary"
							onClick={ () => window.location.reload() }
						>
							{ __( 'Reload page', 'simple-history' ) }
						</Button>
					</Text>

					<details>
						<summary>
							{ __( 'View error details', 'simple-history' ) }
						</summary>

						<Text
							as="pre"
							style={ {
								padding: '1rem',
							} }
						>
							{ JSON.stringify(
								eventsLoadingErrorDetails,
								null,
								2
							) }
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
				</VStack>
			</Notice>
		</div>
	);
}
