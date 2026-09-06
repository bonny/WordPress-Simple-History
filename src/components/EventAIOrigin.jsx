import { Tooltip } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';
import { EventHeaderItem } from './EventHeaderItem';
import { getTrackingUrl } from '../functions';

/**
 * Sparkle — the de-facto AI/agent icon adopted across the industry
 * (Google Photos, Notion AI, Coda AI, Miro AI, and others) by 2026.
 * `@wordpress/icons` does not ship one, so we render the path inline.
 * Mirrors Material Design's `auto_awesome`: one main star with deep
 * concave waists plus a small companion sparkle, which reads as
 * "sparkly" even at 12px. Uses `currentColor` for light/dark mode.
 */
const sparkleIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		aria-hidden="true"
		focusable="false"
	>
		<path
			fill="currentColor"
			d="M19 9l1.25-2.75L23 5l-2.75-1.25L19 1l-1.25 2.75L15 5l2.75 1.25L19 9zm-7.5.5L9 4 6.5 9.5 1 12l5.5 2.5L9 20l2.5-5.5L17 12z"
		/>
	</svg>
);

const sparkleIconStyle = {
	verticalAlign: 'text-bottom',
	marginInlineEnd: '4px',
};

/**
 * Map of `detected_via` values to a sentence fragment that completes the
 * tooltip "Detected from the …" phrasing in plain language.
 *
 * @param {string} detectedVia
 */
function getDetectedViaLabel( detectedVia ) {
	switch ( detectedVia ) {
		case 'abilities-api':
			return __(
				'a WordPress API designed for AI integrations',
				'simple-history'
			);
		case 'signature-agent':
			return __(
				'a verified signature the AI agent attached to its request',
				'simple-history'
			);
		case 'header':
			return __(
				'a special request marker used by AI coding tools',
				'simple-history'
			);
		case 'user-agent':
			return __(
				'the name the AI tool reported in its request',
				'simple-history'
			);
		case 'wp-cli-env':
			return __(
				'an environment marker the AI tool left when running command-line tasks',
				'simple-history'
			);
		default:
			return __( 'signals in the request', 'simple-history' );
	}
}

/**
 * Renders an inline marker on the event header row when the event was
 * triggered by a request that looked like it came from an AI tool
 * (Claude Code, ChatGPT, Cursor, MCP clients, the Abilities API, etc.).
 *
 * The actual initiator stays the real signed-in user — this is additional
 * audit context, not an authentication signal.
 *
 * @param {Object} props
 * @param {Object} props.event
 */
export function EventAIOrigin( props ) {
	const { event } = props;
	const aiOrigin = event?.ai_origin;

	if ( ! aiOrigin || ! aiOrigin.agent_name ) {
		return null;
	}

	const accessibleLabel = sprintf(
		/* translators: %s: AI agent name (e.g. "Claude Code"). */
		__( 'AI agent: %s', 'simple-history' ),
		aiOrigin.agent_name
	);

	const helpUrl = getTrackingUrl(
		'https://simple-history.com/docs/ai-agent-detection/',
		'ai-agent-detection',
		'plugin',
		'tooltip'
	);

	const tooltip = (
		<>
			<p>
				{ __(
					'This event looks like it was made by an AI tool acting on behalf of a logged-in user.',
					'simple-history'
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: explanation of how the AI agent was detected. */
					__( 'Detected from %s.', 'simple-history' ),
					getDetectedViaLabel( aiOrigin.detected_via )
				) }
			</p>
			<p>
				<a href={ helpUrl } target="_blank" rel="noopener noreferrer">
					{ __( 'Learn how AI detection works', 'simple-history' ) }
				</a>
			</p>
		</>
	);

	return (
		<EventHeaderItem className="SimpleHistoryLogitem__aiOrigin">
			<Tooltip text={ tooltip }>
				<span role="img" aria-label={ accessibleLabel } tabIndex={ 0 }>
					<Icon
						icon={ sparkleIcon }
						size={ 12 }
						style={ sparkleIconStyle }
					/>
					{ aiOrigin.agent_name }
				</span>
			</Tooltip>
		</EventHeaderItem>
	);
}
