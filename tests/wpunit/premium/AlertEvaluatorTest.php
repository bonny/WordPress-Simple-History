<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Alerts\Alert_Evaluator;

/**
 * Tests for the Alert_Evaluator class.
 *
 * Tests JsonLogic evaluation, rule validation, and wildcard expansion.
 *
 * @group premium
 * @group alerts
 * @group evaluator
 */
class AlertEvaluatorTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * Test that JsonLogic library is available.
	 */
	public function test_is_available(): void {
		$this->assertTrue( Alert_Evaluator::is_available() );
	}

	/**
	 * Test empty rule matches all events.
	 */
	public function test_empty_rule_matches_all(): void {
		$event_data = [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'info',
			'context' => [ '_message_key' => 'user_logged_in' ],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( null, $event_data ) );
		$this->assertTrue( Alert_Evaluator::evaluate( [], $event_data ) );
	}

	/**
	 * Test simple equality rule.
	 */
	public function test_simple_equality_rule(): void {
		$rule = [
			'==' => [
				[ 'var' => 'logger' ],
				'SimpleUserLogger',
			],
		];

		$matching_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [],
		];

		$non_matching_event = [
			'logger'  => 'SimplePostLogger',
			'context' => [],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $matching_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $non_matching_event ) );
	}

	/**
	 * Test "in" operator for multiple values.
	 */
	public function test_in_operator(): void {
		$rule = [
			'in' => [
				[ 'var' => 'level' ],
				[ 'warning', 'error', 'critical' ],
			],
		];

		$warning_event = [ 'level' => 'warning', 'context' => [] ];
		$info_event    = [ 'level' => 'info', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $warning_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $info_event ) );
	}

	/**
	 * Test AND combinator.
	 */
	public function test_and_combinator(): void {
		$rule = [
			'and' => [
				[ '==' => [ [ 'var' => 'logger' ], 'SimpleUserLogger' ] ],
				[ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
			],
		];

		$matching_event = [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'warning',
			'context' => [],
		];

		$partial_match = [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'info',
			'context' => [],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $matching_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $partial_match ) );
	}

	/**
	 * Test OR combinator.
	 */
	public function test_or_combinator(): void {
		$rule = [
			'or' => [
				[ '==' => [ [ 'var' => 'logger' ], 'SimpleUserLogger' ] ],
				[ '==' => [ [ 'var' => 'logger' ], 'SimplePostLogger' ] ],
			],
		];

		$user_event = [ 'logger' => 'SimpleUserLogger', 'context' => [] ];
		$post_event = [ 'logger' => 'SimplePostLogger', 'context' => [] ];
		$other_event = [ 'logger' => 'SimpleMediaLogger', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $user_event ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $post_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $other_event ) );
	}

	/**
	 * Test NOT operator.
	 */
	public function test_not_operator(): void {
		$rule = [
			'!' => [
				[ '==' => [ [ 'var' => 'level' ], 'debug' ] ],
			],
		];

		$info_event  = [ 'level' => 'info', 'context' => [] ];
		$debug_event = [ 'level' => 'debug', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $info_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $debug_event ) );
	}

	/**
	 * Test != operator.
	 */
	public function test_not_equals_operator(): void {
		$rule = [
			'!=' => [
				[ 'var' => 'user_role' ],
				'administrator',
			],
		];

		$admin_event = [
			'context' => [ '_user_role' => 'administrator' ],
		];

		$editor_event = [
			'context' => [ '_user_role' => 'editor' ],
		];

		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $admin_event ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $editor_event ) );
	}

	/**
	 * Test context values are flattened.
	 */
	public function test_context_flattening(): void {
		$rule = [
			'==' => [
				[ 'var' => 'user_id' ],
				123,
			],
		];

		$event = [
			'context' => [
				'_user_id' => 123,
			],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $event ) );
	}

	/**
	 * Test message_type field is constructed from logger:message_key.
	 */
	public function test_message_type_construction(): void {
		$rule = [
			'==' => [
				[ 'var' => 'message_type' ],
				'SimpleUserLogger:user_logged_in',
			],
		];

		$matching_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_in' ],
		];

		$non_matching_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_out' ],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $matching_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $non_matching_event ) );
	}

	/**
	 * Test message_type wildcard matching (Logger:*).
	 */
	public function test_message_type_wildcard(): void {
		$rule = [
			'==' => [
				[ 'var' => 'message_type' ],
				'SimpleUserLogger:*',
			],
		];

		$login_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_in' ],
		];

		$logout_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_out' ],
		];

		$post_event = [
			'logger'  => 'SimplePostLogger',
			'context' => [ '_message_key' => 'post_created' ],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $login_event ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $logout_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $post_event ) );
	}

	/**
	 * Test message_type wildcard with "in" operator.
	 */
	public function test_message_type_wildcard_in_operator(): void {
		$rule = [
			'in' => [
				[ 'var' => 'message_type' ],
				[ 'SimpleUserLogger:*', 'SimplePostLogger:post_deleted' ],
			],
		];

		$user_login_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_in' ],
		];

		$post_deleted_event = [
			'logger'  => 'SimplePostLogger',
			'context' => [ '_message_key' => 'post_deleted' ],
		];

		$post_created_event = [
			'logger'  => 'SimplePostLogger',
			'context' => [ '_message_key' => 'post_created' ],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $user_login_event ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $post_deleted_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $post_created_event ) );
	}

	/**
	 * Test comma-separated message_type values expansion.
	 */
	public function test_message_type_comma_separated(): void {
		$rule = [
			'==' => [
				[ 'var' => 'message_type' ],
				'SimpleUserLogger:user_login_failed,user_unknown_login_failed',
			],
		];

		$login_failed_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_login_failed' ],
		];

		$unknown_failed_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_unknown_login_failed' ],
		];

		$success_event = [
			'logger'  => 'SimpleUserLogger',
			'context' => [ '_message_key' => 'user_logged_in' ],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $login_failed_event ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $unknown_failed_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $success_event ) );
	}

	/**
	 * Test user_role lookup fallback for older events.
	 */
	public function test_user_role_fallback_lookup(): void {
		// Create a test user with editor role.
		$user_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		$rule = [
			'==' => [
				[ 'var' => 'user_role' ],
				'editor',
			],
		];

		// Event without _user_role but with _user_id (simulating old event format).
		$event = [
			'context' => [
				'_user_id' => $user_id,
				// No _user_role - should be looked up.
			],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $event ) );
	}

	/**
	 * Test validate_rule with valid rule.
	 */
	public function test_validate_rule_valid(): void {
		$rule = [
			'==' => [
				[ 'var' => 'logger' ],
				'SimpleUserLogger',
			],
		];

		$result = Alert_Evaluator::validate_rule( $rule );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test validate_rule with empty rule (valid - matches all).
	 */
	public function test_validate_rule_empty(): void {
		$result = Alert_Evaluator::validate_rule( null );

		$this->assertTrue( $result['valid'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test validate_rule with invalid type.
	 */
	public function test_validate_rule_invalid_type(): void {
		$result = Alert_Evaluator::validate_rule( 'not an array' );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test get_rule_description for AND combinator.
	 */
	public function test_get_rule_description_and(): void {
		$rule = [
			'and' => [
				[ '==' => [ [ 'var' => 'logger' ], 'A' ] ],
				[ '==' => [ [ 'var' => 'level' ], 'B' ] ],
			],
		];

		$description = Alert_Evaluator::get_rule_description( $rule );

		$this->assertStringContainsString( '2', $description );
		$this->assertStringContainsString( 'all must match', $description );
	}

	/**
	 * Test get_rule_description for OR combinator.
	 */
	public function test_get_rule_description_or(): void {
		$rule = [
			'or' => [
				[ '==' => [ [ 'var' => 'logger' ], 'A' ] ],
				[ '==' => [ [ 'var' => 'level' ], 'B' ] ],
				[ '==' => [ [ 'var' => 'level' ], 'C' ] ],
			],
		];

		$description = Alert_Evaluator::get_rule_description( $rule );

		$this->assertStringContainsString( '3', $description );
		$this->assertStringContainsString( 'any must match', $description );
	}

	/**
	 * Test get_rule_description for empty rule.
	 */
	public function test_get_rule_description_empty(): void {
		$description = Alert_Evaluator::get_rule_description( null );

		$this->assertStringContainsString( 'All events', $description );
	}

	/**
	 * Test evaluate_alert convenience method.
	 */
	public function test_evaluate_alert(): void {
		$alert_config = [
			'rule' => [
				'==' => [
					[ 'var' => 'level' ],
					'error',
				],
			],
		];

		$error_event = [ 'level' => 'error', 'context' => [] ];
		$info_event  = [ 'level' => 'info', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate_alert( $alert_config, $error_event ) );
		$this->assertFalse( Alert_Evaluator::evaluate_alert( $alert_config, $info_event ) );
	}

	/**
	 * Test evaluate_alert with jsonlogic_rule key.
	 */
	public function test_evaluate_alert_jsonlogic_rule_key(): void {
		$alert_config = [
			'jsonlogic_rule' => [
				'==' => [
					[ 'var' => 'level' ],
					'warning',
				],
			],
		];

		$warning_event = [ 'level' => 'warning', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate_alert( $alert_config, $warning_event ) );
	}

	/**
	 * Test complex nested rule.
	 */
	public function test_complex_nested_rule(): void {
		// Rule: (logger is SimpleUserLogger AND level is warning) OR level is critical.
		$rule = [
			'or' => [
				[
					'and' => [
						[ '==' => [ [ 'var' => 'logger' ], 'SimpleUserLogger' ] ],
						[ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
					],
				],
				[ '==' => [ [ 'var' => 'level' ], 'critical' ] ],
			],
		];

		$user_warning = [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'warning',
			'context' => [],
		];

		$post_critical = [
			'logger'  => 'SimplePostLogger',
			'level'   => 'critical',
			'context' => [],
		];

		$user_info = [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'info',
			'context' => [],
		];

		$post_warning = [
			'logger'  => 'SimplePostLogger',
			'level'   => 'warning',
			'context' => [],
		];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $user_warning ) );
		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $post_critical ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $user_info ) );
		$this->assertFalse( Alert_Evaluator::evaluate( $rule, $post_warning ) );
	}

	/**
	 * Test that rule with object is converted to array.
	 */
	public function test_rule_object_conversion(): void {
		$rule = (object) [
			'==' => [
				(object) [ 'var' => 'level' ],
				'error',
			],
		];

		$event = [ 'level' => 'error', 'context' => [] ];

		$this->assertTrue( Alert_Evaluator::evaluate( $rule, $event ) );
	}
}
