<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Formatters\Json_Lines_Formatter;
use Simple_History\AddOns\Pro\Formatters\Logfmt_Formatter;
use Simple_History\AddOns\Pro\Formatters\Rfc5424_Formatter;

/**
 * Tests for premium formatters (JSON Lines, Logfmt, RFC5424).
 *
 * @group premium
 * @group formatters
 */
class FormattersTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * Get sample event data for testing formatters.
	 *
	 * @return array Event data array.
	 */
	private function get_sample_event_data(): array {
		return [
			'level'     => 'info',
			'logger'    => 'SimpleUserLogger',
			'initiator' => 'wp_user',
			'date'      => '2025-01-04 12:00:00',
			'context'   => [
				'_message_key' => 'user_logged_in',
				'_user_id'     => '42',
				'_user_login'  => 'admin',
			],
		];
	}

	// =========================================================================
	// JSON Lines Formatter Tests
	// =========================================================================

	/**
	 * Test JSON Lines formatter exists and can be instantiated.
	 */
	public function test_json_lines_formatter_exists(): void {
		$this->assertTrue(
			class_exists( Json_Lines_Formatter::class ),
			'Json_Lines_Formatter class should exist.'
		);
	}

	/**
	 * Test JSON Lines formatter slug.
	 */
	public function test_json_lines_formatter_slug(): void {
		$formatter = new Json_Lines_Formatter();
		$this->assertEquals( 'json_lines', $formatter->get_slug() );
	}

	/**
	 * Test JSON Lines formatter name.
	 */
	public function test_json_lines_formatter_name(): void {
		$formatter = new Json_Lines_Formatter();
		$this->assertNotEmpty( $formatter->get_name() );
	}

	/**
	 * Test JSON Lines formatter description.
	 */
	public function test_json_lines_formatter_description(): void {
		$formatter = new Json_Lines_Formatter();
		$this->assertNotEmpty( $formatter->get_description() );
	}

	/**
	 * Test JSON Lines formatter output is valid JSON.
	 */
	public function test_json_lines_format_outputs_valid_json(): void {
		$formatter = new Json_Lines_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Should end with newline.
		$this->assertStringEndsWith( "\n", $output );

		// Should be valid JSON (trim newline first).
		$decoded = json_decode( trim( $output ), true );
		$this->assertNotNull( $decoded, 'Output should be valid JSON.' );
	}

	/**
	 * Test JSON Lines formatter includes required fields.
	 */
	public function test_json_lines_format_includes_required_fields(): void {
		$formatter = new Json_Lines_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output  = $formatter->format( $event, $message );
		$decoded = json_decode( trim( $output ), true );

		$this->assertArrayHasKey( 'timestamp', $decoded );
		$this->assertArrayHasKey( 'level', $decoded );
		$this->assertArrayHasKey( 'message', $decoded );
		$this->assertArrayHasKey( 'logger', $decoded );
		$this->assertArrayHasKey( 'initiator', $decoded );
	}

	/**
	 * Test JSON Lines formatter level is lowercase.
	 */
	public function test_json_lines_format_level_is_lowercase(): void {
		$formatter = new Json_Lines_Formatter();
		$event     = $this->get_sample_event_data();
		$event['level'] = 'WARNING';
		$message   = 'Test warning';

		$output  = $formatter->format( $event, $message );
		$decoded = json_decode( trim( $output ), true );

		$this->assertEquals( 'warning', $decoded['level'] );
	}

	// =========================================================================
	// Logfmt Formatter Tests
	// =========================================================================

	/**
	 * Test Logfmt formatter exists and can be instantiated.
	 */
	public function test_logfmt_formatter_exists(): void {
		$this->assertTrue(
			class_exists( Logfmt_Formatter::class ),
			'Logfmt_Formatter class should exist.'
		);
	}

	/**
	 * Test Logfmt formatter slug.
	 */
	public function test_logfmt_formatter_slug(): void {
		$formatter = new Logfmt_Formatter();
		$this->assertEquals( 'logfmt', $formatter->get_slug() );
	}

	/**
	 * Test Logfmt formatter name.
	 */
	public function test_logfmt_formatter_name(): void {
		$formatter = new Logfmt_Formatter();
		$this->assertNotEmpty( $formatter->get_name() );
	}

	/**
	 * Test Logfmt formatter description.
	 */
	public function test_logfmt_formatter_description(): void {
		$formatter = new Logfmt_Formatter();
		$this->assertNotEmpty( $formatter->get_description() );
	}

	/**
	 * Test Logfmt formatter output format.
	 */
	public function test_logfmt_format_output(): void {
		$formatter = new Logfmt_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Should end with newline.
		$this->assertStringEndsWith( "\n", $output );

		// Should contain key=value pairs.
		$this->assertStringContainsString( 'level=info', $output );
		$this->assertStringContainsString( 'logger=SimpleUserLogger', $output );
		$this->assertStringContainsString( 'initiator=wp_user', $output );
	}

	/**
	 * Test Logfmt formatter quotes values with spaces.
	 */
	public function test_logfmt_format_quotes_values_with_spaces(): void {
		$formatter = new Logfmt_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in successfully';

		$output = $formatter->format( $event, $message );

		// Message with spaces should be quoted.
		$this->assertStringContainsString( 'msg="User logged in successfully"', $output );
	}

	// =========================================================================
	// RFC5424 Formatter Tests
	// =========================================================================

	/**
	 * Test RFC5424 formatter exists and can be instantiated.
	 */
	public function test_rfc5424_formatter_exists(): void {
		$this->assertTrue(
			class_exists( Rfc5424_Formatter::class ),
			'Rfc5424_Formatter class should exist.'
		);
	}

	/**
	 * Test RFC5424 formatter slug.
	 */
	public function test_rfc5424_formatter_slug(): void {
		$formatter = new Rfc5424_Formatter();
		$this->assertEquals( 'rfc5424', $formatter->get_slug() );
	}

	/**
	 * Test RFC5424 formatter name.
	 */
	public function test_rfc5424_formatter_name(): void {
		$formatter = new Rfc5424_Formatter();
		$this->assertNotEmpty( $formatter->get_name() );
	}

	/**
	 * Test RFC5424 formatter description.
	 */
	public function test_rfc5424_formatter_description(): void {
		$formatter = new Rfc5424_Formatter();
		$this->assertNotEmpty( $formatter->get_description() );
	}

	/**
	 * Test RFC5424 formatter output starts with PRI.
	 */
	public function test_rfc5424_format_starts_with_pri(): void {
		$formatter = new Rfc5424_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Should start with <PRI> (e.g., <14>).
		$this->assertMatchesRegularExpression( '/^<\d+>/', $output );
	}

	/**
	 * Test RFC5424 formatter output contains version.
	 */
	public function test_rfc5424_format_contains_version(): void {
		$formatter = new Rfc5424_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Should contain version 1 after PRI.
		$this->assertMatchesRegularExpression( '/^<\d+>1 /', $output );
	}

	/**
	 * Test RFC5424 formatter includes structured data.
	 */
	public function test_rfc5424_format_includes_structured_data(): void {
		$formatter = new Rfc5424_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Should contain structured data with simplehistory SD-ID.
		$this->assertStringContainsString( '[simplehistory@', $output );
		$this->assertStringContainsString( 'level="info"', $output );
		$this->assertStringContainsString( 'logger="SimpleUserLogger"', $output );
	}

	/**
	 * Test RFC5424 formatter output ends with newline.
	 */
	public function test_rfc5424_format_ends_with_newline(): void {
		$formatter = new Rfc5424_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		$this->assertStringEndsWith( "\n", $output );
	}

	/**
	 * Test RFC5424 formatter includes message at end.
	 */
	public function test_rfc5424_format_includes_message(): void {
		$formatter = new Rfc5424_Formatter();
		$event     = $this->get_sample_event_data();
		$message   = 'User logged in';

		$output = $formatter->format( $event, $message );

		// Message should be at the end (before newline).
		$this->assertStringEndsWith( "User logged in\n", $output );
	}
}
