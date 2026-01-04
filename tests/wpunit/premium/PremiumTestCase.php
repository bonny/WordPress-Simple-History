<?php

namespace Simple_History\Tests\Premium;

/**
 * Base test case for premium plugin tests.
 *
 * Provides helper methods for activating/deactivating the premium plugin
 * and checking premium-specific functionality.
 */
abstract class PremiumTestCase extends \Codeception\TestCase\WPTestCase {
	/** @var string Premium plugin file path. */
	protected const PREMIUM_PLUGIN = 'simple-history-premium/simple-history-premium.php';

	/** @var bool Whether premium was activated by this test. */
	protected bool $premium_activated = false;

	/**
	 * Activate the premium plugin.
	 *
	 * Call this in setUp() or in individual tests that need premium.
	 */
	protected function activate_premium(): void {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$result = activate_plugin( self::PREMIUM_PLUGIN );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Failed to activate premium plugin: ' . $result->get_error_message() );
		}

		$this->premium_activated = true;

		// Trigger the plugins_loaded hook for premium to initialize.
		do_action( 'plugins_loaded' );
	}

	/**
	 * Deactivate the premium plugin.
	 */
	protected function deactivate_premium(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( self::PREMIUM_PLUGIN );
		$this->premium_activated = false;
	}

	/**
	 * Check if premium plugin is active.
	 *
	 * @return bool
	 */
	protected function is_premium_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::PREMIUM_PLUGIN );
	}

	/**
	 * Assert that the premium filter is handling alerts.
	 */
	protected function assertPremiumHandlingAlerts(): void {
		$this->assertTrue(
			apply_filters( 'simple_history/alerts/is_premium_handling', false ),
			'Premium should be handling alerts when active.'
		);
	}

	/**
	 * Assert that core is handling alerts (premium not active).
	 */
	protected function assertCoreHandlingAlerts(): void {
		$this->assertFalse(
			apply_filters( 'simple_history/alerts/is_premium_handling', false ),
			'Core should be handling alerts when premium is not active.'
		);
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Reset options that tests may have modified.
		delete_option( 'simple_history_alert_destinations' );
		delete_option( 'simple_history_alert_rules' );

		// Deactivate premium if we activated it.
		if ( $this->premium_activated ) {
			$this->deactivate_premium();
		}

		parent::tearDown();
	}
}
