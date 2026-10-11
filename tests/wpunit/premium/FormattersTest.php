<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Formatters\Json_Lines_Formatter;
use Simple_History\AddOns\Pro\Formatters\Logfmt_Formatter;
use Simple_History\AddOns\Pro\Formatters\Rfc5424_Formatter;
use Simple_History\AddOns\Pro\Formatters\Splunk_Formatter;
use Simple_History\AddOns\Pro\Formatters\Datadog_Formatter;
use Simple_History\AddOns\Pro\Channels\Webhook_Channel;
use Simple_History\AddOns\Pro\Channels\Local_Syslog_Channel;

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

	/**
	 * CR and LF in a value must not start a new, forged logfmt line.
	 */
	public function test_logfmt_escapes_line_breaks(): void {
		$formatter = new Logfmt_Formatter();
		$output    = $formatter->format(
			[
				'level'     => 'info',
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'web_user',
				'context'   => [ '_user_login' => "admin\r\nlevel=critical msg=\"forged\"" ],
			],
			"Failed login\nlevel=critical msg=\"forged\""
		);

		$this->assertSame( 1, substr_count( $output, "\n" ), 'Only the trailing newline is allowed.' );
		$this->assertStringNotContainsString( "\r", $output );
		$this->assertStringContainsString( 'Failed login\nlevel=critical', $output );
	}

	// =========================================================================
	// Event details (issue 365)
	// =========================================================================

	/**
	 * Sample event with the details core's channels manager adds.
	 *
	 * @return array Event data array.
	 */
	private function get_sample_event_data_with_details(): array {
		$event = $this->get_sample_event_data();

		$event['context']['_message_key'] = 'user_updated_profile';
		$event['details_text']            = "Role added: administrator\nRole removed: author";
		$event['details']                 = [
			'user_added_roles'   => [
				'label' => 'Role added',
				'new'   => 'administrator',
			],
			'user_removed_roles' => [
				'label' => 'Role removed',
				'new'   => 'author',
			],
		];

		return $event;
	}

	public function test_json_lines_includes_details(): void {
		$output  = ( new Json_Lines_Formatter() )->format( $this->get_sample_event_data_with_details(), 'Edited the profile for user "john"' );
		$decoded = json_decode( trim( $output ), true );

		$this->assertSame( 'administrator', $decoded['details']['user_added_roles']['new'] );
		$this->assertSame( 'Role added: administrator; Role removed: author', $decoded['details_text'] );
	}

	public function test_json_lines_without_details_has_no_details_keys(): void {
		$output  = ( new Json_Lines_Formatter() )->format( $this->get_sample_event_data(), 'User logged in' );
		$decoded = json_decode( trim( $output ), true );

		$this->assertArrayNotHasKey( 'details', $decoded );
		$this->assertArrayNotHasKey( 'details_text', $decoded );
	}

	public function test_logfmt_includes_details(): void {
		$output = ( new Logfmt_Formatter() )->format( $this->get_sample_event_data_with_details(), 'Edited profile' );

		$this->assertStringContainsString( 'details="Role added: administrator; Role removed: author"', $output );
	}

	public function test_rfc5424_includes_details_param(): void {
		$output = ( new Rfc5424_Formatter() )->format( $this->get_sample_event_data_with_details(), 'Edited profile' );

		$this->assertStringContainsString( 'details="Role added: administrator; Role removed: author"', $output );
	}

	public function test_splunk_and_datadog_include_details(): void {
		$event = $this->get_sample_event_data_with_details();

		$splunk  = ( new Splunk_Formatter() )->format_as_array( $event, 'Edited profile' );
		$datadog = ( new Datadog_Formatter() )->format_as_array( $event, 'Edited profile' );

		$this->assertSame( 'author', $splunk['event']['details']['user_removed_roles']['new'] );
		$this->assertSame( 'Role added: administrator; Role removed: author', $splunk['event']['details_text'] );
		$this->assertSame( 'author', $datadog['details']['user_removed_roles']['new'] );
	}

	/**
	 * Details are user-controlled text, so they must not break a line-based format.
	 */
	public function test_details_line_breaks_cannot_forge_lines(): void {
		$event                 = $this->get_sample_event_data_with_details();
		$event['details_text'] = "Title: a\r[2026-01-01T00:00:00Z] CRITICAL forged";

		foreach ( [ new Logfmt_Formatter(), new Rfc5424_Formatter(), new Json_Lines_Formatter() ] as $formatter ) {
			$output = $formatter->format( $event, 'Updated post' );

			$this->assertSame( 1, substr_count( $output, "\n" ), get_class( $formatter ) . ': only the trailing newline is allowed.' );
			$this->assertStringNotContainsString( "\r", $output, get_class( $formatter ) );
		}
	}

	public function test_local_syslog_message_includes_details_and_escapes_line_breaks(): void {
		$event                 = $this->get_sample_event_data_with_details();
		$event['details_text'] = "Role added: administrator\nRole removed: author\r\nforged";

		$method = new \ReflectionMethod( Local_Syslog_Channel::class, 'format_message' );
		$method->setAccessible( true );
		$output = $method->invoke( new Local_Syslog_Channel(), $event, "Edited profile\nforged line" );

		$this->assertStringContainsString( 'Edited profile\nforged line | Role added: administrator; Role removed: author; forged', $output );
		$this->assertStringNotContainsString( "\n", $output );
		$this->assertStringNotContainsString( "\r", $output );
	}

	public function test_webhook_details_placeholders(): void {
		$method = new \ReflectionMethod( Webhook_Channel::class, 'process_template' );
		$method->setAccessible( true );

		$template = '{"message": "{message}", "details": "{details}", "changes": {details_json}}';
		$output   = $method->invoke( new Webhook_Channel(), $template, $this->get_sample_event_data_with_details(), 'Edited "john"' );
		$decoded  = json_decode( $output, true );

		$this->assertIsArray( $decoded, 'Template output must be valid JSON: ' . $output );
		$this->assertSame( 'Role added: administrator; Role removed: author', $decoded['details'] );
		$this->assertSame( 'administrator', $decoded['changes']['user_added_roles']['new'] );

		// No details: still valid JSON.
		$output  = $method->invoke( new Webhook_Channel(), $template, $this->get_sample_event_data(), 'Logged in' );
		$decoded = json_decode( $output, true );

		$this->assertSame( '', $decoded['details'] );
		$this->assertSame( [], $decoded['changes'] );
	}

	/**
	 * A placeholder inside an event value must not be replaced in turn.
	 */
	public function test_webhook_placeholder_in_value_is_not_replaced(): void {
		$method = new \ReflectionMethod( Webhook_Channel::class, 'process_template' );
		$method->setAccessible( true );

		$template = '{"message": "{message}", "changes": {details_json}}';
		$output   = $method->invoke( new Webhook_Channel(), $template, $this->get_sample_event_data_with_details(), 'Post "{details_json}" updated' );

		$this->assertIsArray( json_decode( $output, true ), 'Template output must be valid JSON: ' . $output );
		$this->assertStringContainsString( 'Post \"{details_json}\" updated', $output );
	}
}
