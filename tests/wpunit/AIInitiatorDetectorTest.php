<?php

use Simple_History\Simple_History;
use Simple_History\Services\AI_Initiator_Detector;

/**
 * Tests for the AI_Initiator_Detector service.
 */
class AIInitiatorDetectorTest extends \Codeception\TestCase\WPTestCase {
	/** @var array<string, string> */
	private $original_server = [];

	public function setUp(): void {
		parent::setUp();
		$this->original_server = $_SERVER;
		unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_URI'] );
	}

	public function tearDown(): void {
		$_SERVER = $this->original_server;
		parent::tearDown();
	}

	private function detector(): AI_Initiator_Detector {
		return new AI_Initiator_Detector( Simple_History::get_instance() );
	}

	public function test_no_signals_returns_null() {
		$this->assertNull( $this->detector()->detect() );
	}

	public function test_abilities_api_route_matches() {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp-abilities/v1/some-ability';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'abilities-api', $result['detected_via'] );
	}

	public function test_signature_agent_header_normalized_to_brand_name() {
		$_SERVER['HTTP_SIGNATURE_AGENT'] = '"https://chatgpt.com"';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'signature-agent', $result['detected_via'] );
		$this->assertSame( 'ChatGPT', $result['agent_name'] );
	}

	public function test_signature_agent_unknown_host_falls_back_to_host() {
		$_SERVER['HTTP_SIGNATURE_AGENT'] = '"https://example.ai"';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'example.ai', $result['agent_name'] );
	}

	public function test_user_agent_claude_code_matches() {
		$_SERVER['HTTP_USER_AGENT'] = 'claude-code/1.2.3 (cli)';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'user-agent', $result['detected_via'] );
		$this->assertSame( 'Claude Code', $result['agent_name'] );
	}

	public function test_user_agent_perplexitybot_matches() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'PerplexityBot (crawler)', $result['agent_name'] );
	}

	/**
	 * Regression test: a human whose name appears in the user-agent
	 * (e.g. as a custom MacBook label) must not be flagged as AI.
	 */
	public function test_bare_brand_word_in_ua_does_not_match() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Claude is my real name)';

		$this->assertNull( $this->detector()->detect() );
	}

	public function test_x_mcp_client_header_matches() {
		$_SERVER['HTTP_X_MCP_CLIENT'] = 'my-mcp-server/0.1';

		$result = $this->detector()->detect();

		$this->assertIsArray( $result );
		$this->assertSame( 'header', $result['detected_via'] );
		$this->assertSame( 'my-mcp-server/0.1', $result['agent_name'] );
	}

	public function test_filter_can_override_detection() {
		$_SERVER['HTTP_USER_AGENT'] = 'claude-code/1.2.3';

		$override = function () {
			return [
				'agent_name'   => 'Custom',
				'detected_via' => 'custom',
				'application'  => 'test',
			];
		};

		add_filter( 'simple_history/ai_initiator_origin', $override );

		$result = $this->detector()->detect();

		remove_filter( 'simple_history/ai_initiator_origin', $override );

		$this->assertSame( 'Custom', $result['agent_name'] );
		$this->assertSame( 'custom', $result['detected_via'] );
	}
}
