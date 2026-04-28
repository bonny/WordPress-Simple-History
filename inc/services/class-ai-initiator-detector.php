<?php

namespace Simple_History\Services;

/**
 * Detects when a request to WordPress is being made via an AI agent
 * (Claude Code, ChatGPT, MCP clients, the Abilities API, etc.) and
 * attaches that as informational context on every event logged during
 * the request.
 *
 * The actual initiator stays the real signed-in user — this is *additional*
 * audit context, not an authentication signal.
 */
class AI_Initiator_Detector extends Service {
	/** Context key set on each event when AI attribution is detected. */
	const CONTEXT_KEY_AGENT        = '_initiator_ai_agent';
	const CONTEXT_KEY_DETECTED_VIA = '_initiator_ai_detected_via';
	const CONTEXT_KEY_APPLICATION  = '_initiator_ai_application';

	/** Canonical `detected_via` values exposed in REST and the React UI. */
	const VIA_ABILITIES_API   = 'abilities-api';
	const VIA_SIGNATURE_AGENT = 'signature-agent';
	const VIA_HEADER          = 'header';
	const VIA_USER_AGENT      = 'user-agent';
	const VIA_WP_CLI_ENV      = 'wp-cli-env';

	/**
	 * Map of regex → friendly agent name. Each pattern targets a known tool
	 * identifier in a user-agent string. Patterns are anchored to product
	 * tokens (with a slash, hyphen-suffix, or an explicit tool keyword) so
	 * incidental occurrences of the bare brand word do not match.
	 *
	 * Verified against the public bot directories of Anthropic, OpenAI,
	 * Perplexity, Meta, Google, ByteDance, Amazon, and the MCP reference
	 * fetch server.
	 *
	 * @var array<string, string>
	 */
	const UA_PATTERNS = [
		// Anthropic / Claude family.
		'/claude-code\//i'          => 'Claude Code',
		'/claude-user\//i'          => 'Claude',
		'/claude-searchbot\//i'     => 'Claude (search)',
		'/claudebot\//i'            => 'ClaudeBot',
		'/\bclaude-web\b/i'         => 'Claude (web)',
		'/\bAnthropic[\/\s-]/i'     => 'Anthropic',

		// OpenAI / ChatGPT family.
		'/chatgpt-user\//i'         => 'ChatGPT',
		'/oai-searchbot\//i'        => 'OpenAI (search)',
		'/\bGPTBot\b/i'             => 'GPTBot',
		'/\bOpenAI[\/\s-]/i'        => 'OpenAI',

		// Perplexity. User-initiated agentic action vs background crawler are
		// labelled differently so an auditor can tell at a glance whether
		// their site was scraped or actively used by a human via Perplexity.
		'/perplexity-user\//i'      => 'Perplexity AI',
		'/perplexitybot\//i'        => 'PerplexityBot (crawler)',

		// Meta AI.
		'/meta-externalagent\//i'   => 'Meta AI',
		'/meta-externalfetcher\//i' => 'Meta AI',

		// Google AI.
		'/google-cloudvertexbot/i'  => 'Google Vertex AI',
		'/\bGoogleOther\b/i'        => 'Google AI',

		// Other vendor crawlers commonly used to feed AI products.
		'/\bBytespider\b/i'         => 'Bytespider',
		'/amazonbot\//i'            => 'Amazonbot',

		// Agentic IDE / harness tooling.
		'/cursor-agent\//i'         => 'Cursor',

		// MCP reference fetch server and clients that follow its convention.
		'/modelcontextprotocol\//i' => 'MCP client',
		'/\bmcp[-\/]/i'             => 'MCP client',
	];

	/**
	 * Map of known Signature-Agent host values to friendly brand names.
	 *
	 * @var array<string, string>
	 */
	const SIGNATURE_AGENT_HOSTS = [
		'chatgpt.com'   => 'ChatGPT',
		'openai.com'    => 'OpenAI',
		'claude.ai'     => 'Claude',
		'anthropic.com' => 'Anthropic',
	];

	/** Whether detection has run for this request. */
	private bool $has_detected = false;

	/**
	 * Cached detection result for the current request.
	 *
	 * @var array{agent_name: string, detected_via: string, application: string}|null
	 */
	private ?array $detected = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_filter( 'simple_history/log_insert_context', [ $this, 'maybe_attach_context' ], 10, 1 );
	}

	/**
	 * Append AI-origin context keys to an event's context array
	 * when the current request looks like it came from an AI agent.
	 *
	 * @param array<string, mixed> $context Context array as built by the logger.
	 * @return array<string, mixed>
	 */
	public function maybe_attach_context( $context ) {
		$origin = $this->detect();

		if ( null === $origin ) {
			return $context;
		}

		$context[ self::CONTEXT_KEY_AGENT ]        = $origin['agent_name'];
		$context[ self::CONTEXT_KEY_DETECTED_VIA ] = $origin['detected_via'];

		if ( '' !== $origin['application'] ) {
			$context[ self::CONTEXT_KEY_APPLICATION ] = $origin['application'];
		}

		return $context;
	}

	/**
	 * Detect whether the current request originates from an AI agent.
	 *
	 * Result is cached for the lifetime of the request — detection runs once,
	 * even though context attachment fires for every event.
	 *
	 * @return array{agent_name: string, detected_via: string, application: string}|null
	 */
	public function detect() {
		if ( $this->has_detected ) {
			return $this->detected;
		}

		$result = $this->run_detection();

		/**
		 * Filter the detected AI initiator origin.
		 *
		 * Allows sites to register their own detection or override the result.
		 *
		 * @param array{agent_name: string, detected_via: string, application: string}|null $result Detection result.
		 */
		$this->detected     = apply_filters( 'simple_history/ai_initiator_origin', $result );
		$this->has_detected = true;

		return $this->detected;
	}

	/**
	 * Run detection signals in order of reliability.
	 *
	 * @return array{agent_name: string, detected_via: string, application: string}|null
	 */
	private function run_detection() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ), 0, 256 ) : '';
		$ua_app     = substr( $user_agent, 0, 64 );

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		if ( false !== strpos( $request_uri, '/wp-json/wp-abilities/' ) || false !== strpos( $request_uri, 'rest_route=/wp-abilities/' ) ) {
			return [
				'agent_name'   => $this->match_ua_pattern( $user_agent ) ?? __( 'Abilities API client', 'simple-history' ),
				'detected_via' => self::VIA_ABILITIES_API,
				'application'  => $ua_app,
			];
		}

		// RFC 9421 Signature-Agent header — emerging standard for signed
		// AI-agent requests, used by ChatGPT Agent and the Cloudflare Web
		// Bot Auth ecosystem.
		$signature_agent = $this->get_header( 'Signature-Agent' );

		if ( null !== $signature_agent ) {
			return [
				'agent_name'   => $this->normalize_signature_agent( $signature_agent ),
				'detected_via' => self::VIA_SIGNATURE_AGENT,
				'application'  => '',
			];
		}

		$header_value = $this->get_header( 'X-MCP-Client' ) ?? $this->get_header( 'X-Source-Application' );

		if ( null !== $header_value ) {
			return [
				'agent_name'   => substr( $header_value, 0, 64 ),
				'detected_via' => self::VIA_HEADER,
				'application'  => $ua_app,
			];
		}

		$ua_match = $this->match_ua_pattern( $user_agent );

		if ( null !== $ua_match ) {
			return [
				'agent_name'   => $ua_match,
				'detected_via' => self::VIA_USER_AGENT,
				'application'  => $ua_app,
			];
		}

		return $this->detect_from_wp_cli_env();
	}

	/**
	 * Detect AI tooling from WP-CLI environment variables.
	 *
	 * @return array{agent_name: string, detected_via: string, application: string}|null
	 */
	private function detect_from_wp_cli_env() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return null;
		}

		if ( ! empty( getenv( 'CLAUDECODE' ) ) ) {
			return [
				'agent_name'   => 'Claude Code',
				'detected_via' => self::VIA_WP_CLI_ENV,
				'application'  => 'wp-cli',
			];
		}

		$cli_agent = getenv( 'WP_CLI_AI_AGENT' );

		if ( ! empty( $cli_agent ) ) {
			return [
				'agent_name'   => substr( (string) $cli_agent, 0, 64 ),
				'detected_via' => self::VIA_WP_CLI_ENV,
				'application'  => 'wp-cli',
			];
		}

		return null;
	}

	/**
	 * Read a request header in a way that works across PHP-FPM / mod_php.
	 *
	 * @param string $name Header name (case-insensitive).
	 * @return string|null
	 */
	private function get_header( $name ) {
		$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) );

		if ( ! isset( $_SERVER[ $server_key ] ) ) {
			return null;
		}

		$value = trim( (string) wp_unslash( $_SERVER[ $server_key ] ) );

		return '' === $value ? null : $value;
	}

	/**
	 * Normalize a Signature-Agent header value into a friendly agent name.
	 *
	 * The header carries a structured-fields string — typically a quoted URL
	 * like `"https://chatgpt.com"`. Strip quotes/whitespace, parse the URL,
	 * and map known hosts to brand names. Falls back to the bare host (or
	 * the original value) so unknown agents still surface usefully.
	 *
	 * @param string $value Raw header value.
	 * @return string
	 */
	private function normalize_signature_agent( $value ) {
		$cleaned = trim( $value, " \t\n\r\0\x0B\"" );
		$host    = wp_parse_url( $cleaned, PHP_URL_HOST );

		if ( is_string( $host ) && '' !== $host ) {
			$host = strtolower( ltrim( $host, '.' ) );

			if ( str_starts_with( $host, 'www.' ) ) {
				$host = substr( $host, 4 );
			}

			if ( isset( self::SIGNATURE_AGENT_HOSTS[ $host ] ) ) {
				return self::SIGNATURE_AGENT_HOSTS[ $host ];
			}

			return $host;
		}

		return substr( $cleaned, 0, 64 );
	}

	/**
	 * Match a user-agent string against the known tool patterns.
	 *
	 * @param string $user_agent
	 * @return string|null Friendly agent name, or null on no match.
	 */
	private function match_ua_pattern( $user_agent ) {
		if ( '' === $user_agent ) {
			return null;
		}

		foreach ( self::UA_PATTERNS as $pattern => $name ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return $name;
			}
		}

		return null;
	}
}
