import {
	Button,
	ExternalLink,
	Notice,
	__experimentalText as Text,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { getDatabaseErrorMessage, getTrackingUrl } from '../functions';

export function FetchEventsErrorMessage( props ) {
	const { eventsLoadingHasErrors, eventsLoadingErrorDetails } = props;

	if ( ! eventsLoadingHasErrors ) {
		return null;
	}

	// A database error has a cause worth naming and reloading will not fix it,
	// so it gets its own message instead of the generic advice below.
	const databaseErrorMessage = getDatabaseErrorMessage(
		eventsLoadingErrorDetails
	);

	// Build support URL with tracking parameters.
	const baseUrl = getTrackingUrl(
		'https://simple-history.com/support/load-events-error/',
		'support_error_loadevents'
	);

	// Add error details as additional parameter.
	const supportURL = addQueryArgs( baseUrl, {
		error: JSON.stringify( eventsLoadingErrorDetails ),
	} );

	return (
		<div
			style={ {
				margin: '1rem',
			} }
		>
			<Notice status="warning" isDismissible={ false }>
				<VStack spacing={ 2 }>
					{ /*
					 * Children of VStack must stay flat — it clones each child
					 * to apply spacing, and a React fragment here crashes the
					 * whole admin page instead of rendering this notice.
					 */ }
					<Text as="p">
						{ databaseErrorMessage
							? __(
									'There was an error loading the events. The database reported:',
									'simple-history'
							  )
							: __(
									'There was an error loading the events.',
									'simple-history'
							  ) }
					</Text>

					{ databaseErrorMessage ? (
						<pre
							style={ {
								whiteSpace: 'pre-wrap',
								margin: 0,
							} }
						>
							{ databaseErrorMessage }
						</pre>
					) : null }

					<Text as="p">
						{ databaseErrorMessage
							? __(
									'Reloading will not fix this. The database table holding the history needs attention — your web host can help, or send them the error above.',
									'simple-history'
							  )
							: __(
									'This can often be resolved by refreshing your browser. If the problem persists, please try again later.',
									'simple-history'
							  ) }
					</Text>

					{ databaseErrorMessage ? null : (
						<Text as="p">
							<Button
								variant="secondary"
								onClick={ () => window.location.reload() }
							>
								{ __( 'Reload page', 'simple-history' ) }
							</Button>
						</Text>
					) }

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
