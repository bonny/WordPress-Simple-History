import { Tooltip } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon } from '@wordpress/icons';
import { EventHeaderItem } from './EventHeaderItem';

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
				'request being made to the WordPress Abilities API',
				'simple-history'
			);
		case 'signature-agent':
			return __(
				'cryptographically signed Signature-Agent request header',
				'simple-history'
			);
		case 'header':
			return __( 'MCP client request header', 'simple-history' );
		case 'user-agent':
			return __(
				'user-agent string the AI tool sent with the request',
				'simple-history'
			);
		case 'wp-cli-env':
			return __(
				'WP-CLI environment variable set by the AI tool',
				'simple-history'
			);
		default:
			return __( 'request signals', 'simple-history' );
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

	const tooltip = (
		<>
			<p>
				{ __(
					'This event appears to have been triggered by an AI tool or agent.',
					'simple-history'
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: explanation of how the AI agent was detected. */
					__( 'Detected from the %s.', 'simple-history' ),
					getDetectedViaLabel( aiOrigin.detected_via )
				) }
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
