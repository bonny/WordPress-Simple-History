<?php

namespace Simple_History\Tests\Premium;

/**
 * Tests comparing core vs premium functionality.
 *
 * These tests verify that:
 * - Core shows appropriate promo/teaser features when premium is not active
 * - Premium properly overrides core functionality when active
 *
 * @group premium
 * @group core-vs-premium
 */
class CoreVsPremiumTest extends PremiumTestCase {
	/**
	 * Test that alerts filter returns false when premium is not active.
	 */
	public function test_alerts_filter_false_without_premium(): void {
		// Make sure premium is NOT active.
		$this->assertFalse( $this->is_premium_active() );

		// Remove any existing filters.
		remove_all_filters( 'simple_history/alerts/is_premium_handling' );

		$is_premium_handling = apply_filters( 'simple_history/alerts/is_premium_handling', false );

		$this->assertFalse( $is_premium_handling, 'Core should report that premium is not handling alerts.' );
	}

	/**
	 * Test that alerts filter returns true when premium is active.
	 */
	public function test_alerts_filter_true_with_premium(): void {
		$this->activate_premium();

		$is_premium_handling = apply_filters( 'simple_history/alerts/is_premium_handling', false );

		$this->assertTrue( $is_premium_handling, 'Premium should report that it is handling alerts.' );
	}

	/**
	 * Test premium classes are not available when premium is not active.
	 */
	public function test_premium_classes_not_loaded_without_premium(): void {
		// Make sure premium is NOT active.
		$this->assertFalse( $this->is_premium_active() );

		// Premium module class should not be instantiated.
		// (The class file might be available but not loaded/used)
		$simple_history = \Simple_History\Simple_History::get_instance();
		$alerts_logger  = $simple_history->get_instantiated_logger_by_slug( 'AlertsLogger' );

		$this->assertNull( $alerts_logger, 'AlertsLogger should not be registered when premium is inactive.' );
	}

	/**
	 * Test premium classes are available when premium is active.
	 */
	public function test_premium_classes_loaded_with_premium(): void {
		$this->activate_premium();

		$this->assertTrue( $this->is_premium_active() );

		// Premium module class should now be available.
		$this->assertTrue(
			class_exists( 'Simple_History\AddOns\Pro\Modules\Alerts_Module' ),
			'Alerts_Module class should exist when premium is active.'
		);
	}

	/**
	 * Test that core Simple History works without premium.
	 */
	public function test_core_logging_works_without_premium(): void {
		// Make sure premium is NOT active.
		$this->assertFalse( $this->is_premium_active() );

		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Log something using core.
		\SimpleLogger()->info( 'Test log message without premium' );

		// Query for the log entry.
		$log_query     = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );

		$this->assertNotEmpty( $query_results['log_rows'], 'Core logging should work without premium.' );
		$this->assertStringContainsString( 'Test log message', $query_results['log_rows'][0]->message );
	}

	/**
	 * Test that core Simple History works with premium.
	 */
	public function test_core_logging_works_with_premium(): void {
		$this->activate_premium();

		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Log something using core.
		\SimpleLogger()->info( 'Test log message with premium' );

		// Query for the log entry.
		$log_query     = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );

		$this->assertNotEmpty( $query_results['log_rows'], 'Core logging should work with premium active.' );
		$this->assertStringContainsString( 'Test log message', $query_results['log_rows'][0]->message );
	}

	/**
	 * Test that premium options don't exist before premium is used.
	 */
	public function test_premium_options_not_set_initially(): void {
		// These options should not exist before premium is activated and configured.
		$destinations = get_option( 'simple_history_alert_destinations' );
		$rules        = get_option( 'simple_history_alert_rules' );

		// Options might be false (not set) or empty array.
		$this->assertTrue(
			$destinations === false || empty( $destinations ),
			'Alert destinations should not be configured initially.'
		);

		$this->assertTrue(
			$rules === false || empty( $rules ),
			'Alert rules should not be configured initially.'
		);
	}
}
