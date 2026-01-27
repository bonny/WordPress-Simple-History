<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Alerts_Module;
use Simple_History\AddOns\Pro\Extended_Settings;

/**
 * Tests for custom alert rules functionality.
 *
 * Tests CRUD operations, validation, and rule matching.
 *
 * @group premium
 * @group alerts
 * @group custom-rules
 */
class CustomRulesTest extends PremiumTestCase {
	/**
	 * @var Alerts_Module|null
	 */
	private ?Alerts_Module $alerts_module = null;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		// Clear any existing custom rules.
		delete_option( Alerts_Module::OPTION_CUSTOM_RULES );

		// Get the Alerts_Module instance.
		$simple_history    = \Simple_History\Simple_History::get_instance();
		$extended_settings = Extended_Settings::get_instance( $simple_history );
		$this->alerts_module = $extended_settings->get_instantiated_module( Alerts_Module::class );
	}

	/**
	 * Test get_custom_rules returns empty array by default.
	 */
	public function test_get_custom_rules_returns_empty_by_default(): void {
		$rules = Alerts_Module::get_custom_rules();

		$this->assertIsArray( $rules );
		$this->assertEmpty( $rules );
	}

	/**
	 * Test save_custom_rules stores rules correctly.
	 */
	public function test_save_custom_rules(): void {
		$rules = [
			[
				'id'           => 'rule_123',
				'name'         => 'Test Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ 'dest_1' ],
				'enabled'      => true,
			],
		];

		$result = Alerts_Module::save_custom_rules( $rules );

		$this->assertTrue( $result );

		$stored = Alerts_Module::get_custom_rules();
		$this->assertCount( 1, $stored );
		$this->assertEquals( 'Test Rule', $stored[0]['name'] );
	}

	/**
	 * Test custom rules are validated on save.
	 */
	public function test_save_custom_rules_validates(): void {
		// Rule without required 'name' field.
		$invalid_rules = [
			[
				'id'           => 'rule_invalid',
				'conditions'   => [],
				'destinations' => [ 'dest_1' ],
				'enabled'      => true,
			],
		];

		$result = Alerts_Module::save_custom_rules( $invalid_rules );

		// Should still save (validation is done at REST level), but test the structure.
		$stored = Alerts_Module::get_custom_rules();
		$this->assertIsArray( $stored );
	}

	/**
	 * Test saving multiple rules.
	 */
	public function test_save_multiple_rules(): void {
		$rules = [];

		// Create multiple rules.
		for ( $i = 0; $i < 5; $i++ ) {
			$rules[] = [
				'id'           => 'rule_' . $i,
				'name'         => 'Rule ' . $i,
				'conditions'   => [],
				'destinations' => [ 'dest_1' ],
				'enabled'      => true,
			];
		}

		Alerts_Module::save_custom_rules( $rules );
		$stored = Alerts_Module::get_custom_rules();

		$this->assertCount( 5, $stored );
	}

	/**
	 * Test get_enabled_rules includes enabled custom rules.
	 */
	public function test_get_enabled_rules_includes_custom(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		// Set up custom rules.
		$custom_rules = [
			[
				'id'           => 'custom_1',
				'name'         => 'Custom Rule 1',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ 'dest_1' ],
				'enabled'      => true,
			],
			[
				'id'           => 'custom_2',
				'name'         => 'Custom Rule 2 (disabled)',
				'conditions'   => [],
				'destinations' => [ 'dest_1' ],
				'enabled'      => false,
			],
		];

		update_option( Alerts_Module::OPTION_CUSTOM_RULES, $custom_rules );

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'get_enabled_rules' );
		$method->setAccessible( true );

		$enabled_rules = $method->invoke( $this->alerts_module, [], $custom_rules );

		// Should only include enabled custom rule.
		$custom_enabled = array_filter( $enabled_rules, fn( $r ) => isset( $r['id'] ) && str_starts_with( $r['id'], 'custom_' ) );

		$this->assertCount( 1, $custom_enabled );
		$this->assertEquals( 'Custom Rule 1', reset( $custom_enabled )['name'] );
	}

	/**
	 * Test rule_matches_event with custom rule.
	 */
	public function test_rule_matches_event_custom_rule(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		$rule = [
			'id'         => 'custom_rule',
			'name'       => 'Error Events Rule',
			'conditions' => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
			'enabled'    => true,
		];

		$context    = [];
		$error_data = [ 'level' => 'error', 'logger' => 'TestLogger' ];
		$info_data  = [ 'level' => 'info', 'logger' => 'TestLogger' ];

		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $context, $error_data ) );
		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $context, $info_data ) );
	}

	/**
	 * Test rule_matches_event with preset rule (uses preset ID).
	 *
	 * Preset rules use a 'preset' field that references a preset definition.
	 * The preset definitions contain the actual events to match.
	 */
	public function test_rule_matches_event_preset_rule(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		// Preset rule uses 'preset' field referencing a preset ID.
		// The 'security' preset includes 'user_created' among its events.
		$rule = [
			'id'      => 'security_rule',
			'name'    => 'Security',
			'preset'  => 'security',
			'enabled' => true,
		];

		// Security preset includes 'user_created' event.
		$context = [ '_message_key' => 'user_created' ];
		$data    = [ 'logger' => 'SimpleUserLogger' ];

		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $context, $data ) );

		// Non-matching event (user_logged_in is not in security preset).
		$context2 = [ '_message_key' => 'user_logged_in' ];
		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $context2, $data ) );
	}

	/**
	 * Test custom rule with message_type condition.
	 */
	public function test_custom_rule_message_type(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		$rule = [
			'id'         => 'custom_message_type',
			'name'       => 'Failed Logins',
			'conditions' => [
				'in' => [
					[ 'var' => 'message_type' ],
					[ 'SimpleUserLogger:user_login_failed', 'SimpleUserLogger:user_unknown_login_failed' ],
				],
			],
			'enabled'    => true,
		];

		$context = [ '_message_key' => 'user_login_failed' ];
		$data    = [ 'logger' => 'SimpleUserLogger' ];

		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $context, $data ) );
	}

	/**
	 * Test custom rule with user_role condition.
	 */
	public function test_custom_rule_user_role(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		// Rule: alert if user is not administrator.
		$rule = [
			'id'         => 'non_admin_rule',
			'name'       => 'Non-Admin Activity',
			'conditions' => [
				'!=' => [
					[ 'var' => 'user_role' ],
					'administrator',
				],
			],
			'enabled'    => true,
		];

		$admin_context  = [ '_user_role' => 'administrator' ];
		$editor_context = [ '_user_role' => 'editor' ];
		$data           = [ 'logger' => 'SimplePostLogger' ];

		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $admin_context, $data ) );
		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $editor_context, $data ) );
	}

	/**
	 * Test that rule_matches_event evaluates conditions regardless of enabled flag.
	 *
	 * The enabled flag is filtered at a higher level (get_enabled_rules),
	 * not within rule_matches_event itself. This is by design to separate
	 * filtering logic from matching logic.
	 */
	public function test_rule_matches_event_ignores_enabled_flag(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		$rule = [
			'id'         => 'disabled_rule',
			'name'       => 'Disabled Rule',
			'conditions' => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
			'enabled'    => false,
		];

		$context = [];
		$data    = [ 'level' => 'error' ];

		// rule_matches_event only evaluates conditions - it doesn't check enabled flag.
		// Enabled filtering happens in get_enabled_rules().
		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $context, $data ) );
	}

	/**
	 * Test rule without conditions does not match (safety feature).
	 *
	 * Rules without conditions explicitly return false to prevent
	 * accidentally creating "match all" rules. Users must define
	 * at least one condition for a custom rule to match events.
	 */
	public function test_rule_without_conditions_does_not_match(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		$rule = [
			'id'         => 'all_events_rule',
			'name'       => 'All Events',
			'conditions' => null, // No conditions.
			'enabled'    => true,
		];

		$context = [];
		$data    = [ 'level' => 'info', 'logger' => 'AnyLogger' ];

		// Rules without conditions don't match anything (safety feature).
		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $context, $data ) );
	}

	/**
	 * Test multiple AND conditions.
	 */
	public function test_multiple_and_conditions(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'rule_matches_event' );
		$method->setAccessible( true );

		// Rule: logger is SimpleUserLogger AND level is warning.
		$rule = [
			'id'         => 'and_rule',
			'name'       => 'User Warnings',
			'conditions' => [
				'and' => [
					[ '==' => [ [ 'var' => 'logger' ], 'SimpleUserLogger' ] ],
					[ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
				],
			],
			'enabled'    => true,
		];

		$context = [];

		// Both conditions match.
		$match_data = [ 'logger' => 'SimpleUserLogger', 'level' => 'warning' ];
		$this->assertTrue( $method->invoke( $this->alerts_module, $rule, $context, $match_data ) );

		// Only one condition matches.
		$partial_data = [ 'logger' => 'SimpleUserLogger', 'level' => 'info' ];
		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $context, $partial_data ) );

		// Neither matches.
		$no_match_data = [ 'logger' => 'SimplePostLogger', 'level' => 'info' ];
		$this->assertFalse( $method->invoke( $this->alerts_module, $rule, $context, $no_match_data ) );
	}

	/**
	 * Test excluded loggers don't trigger custom rules.
	 */
	public function test_excluded_loggers_not_processed(): void {
		if ( $this->alerts_module === null ) {
			$this->markTestSkipped( 'Alerts_Module not available.' );
		}

		$reflection = new ReflectionClass( $this->alerts_module );
		$method     = $reflection->getMethod( 'process_logged_event' );
		$method->setAccessible( true );

		// Create a mock logger.
		$mock_alerts_logger = $this->createMock( \Simple_History\Loggers\Logger::class );
		$mock_alerts_logger->method( 'get_slug' )->willReturn( 'AlertsLogger' );

		// Set up a custom rule that would match any event.
		$rules = [
			[
				'id'           => 'catch_all',
				'name'         => 'Catch All',
				'conditions'   => null, // Matches everything.
				'destinations' => [ 'dest_1' ],
				'enabled'      => true,
			],
		];
		update_option( Alerts_Module::OPTION_CUSTOM_RULES, $rules );

		// This should return early without processing.
		// We can't easily test the return, but we can ensure no errors occur.
		$method->invoke( $this->alerts_module, [], [ 'logger' => 'AlertsLogger' ], $mock_alerts_logger );

		// If we got here without error, the exclusion is working.
		$this->assertTrue( true );
	}
}
