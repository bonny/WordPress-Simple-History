<?php

namespace Helper;

/**
 * Base test case for premium plugin tests.
 *
 * Provides helper methods for activating/deactivating the premium plugin
 * and checking premium-specific functionality.
 */
abstract class PremiumTestCase extends \Codeception\TestCase\WPTestCase {
	/** @var string Premium plugin file path. */
	protected const PREMIUM_PLUGIN = 'simple-history-premium/simple-history-premium.php';

	/** @var string Minimum WordPress version required for premium (matches simple-history-premium.php). */
	protected const MIN_WP_VERSION = '6.3';

	/** @var bool Whether premium was activated by this test. */
	protected bool $premium_activated = false;

	/**
	 * Set up test - skip if WordPress version is too old for premium.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_version;
		if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			$this->markTestSkipped(
				sprintf(
					'Premium plugin requires WordPress %s+, current version is %s',
					self::MIN_WP_VERSION,
					$wp_version
				)
			);
		}
	}

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

		// Ensure the premium plugin is properly initialized.
		// The plugins_loaded hook registers the initialization, but we may need
		// to manually trigger it if Simple History was already loaded.
		$this->ensure_premium_initialized();

		// Since after_setup_theme has already run, we need to manually instantiate
		// any loggers that were registered by the premium plugin.
		$this->instantiate_premium_loggers();
	}

	/**
	 * Ensure the premium plugin is properly initialized.
	 *
	 * This handles the case where plugins_loaded has already fired
	 * before the premium plugin was activated.
	 */
	private function ensure_premium_initialized(): void {
		// Check if Extended_Settings is already instantiated.
		if ( class_exists( '\Simple_History\AddOns\Pro\Extended_Settings' ) ) {
			$simple_history = \Simple_History\Simple_History::get_instance();

			// Try to get the existing instance, or create a new one.
			try {
				$reflection = new \ReflectionClass( '\Simple_History\AddOns\Pro\Extended_Settings' );
				$instance_property = $reflection->getProperty( 'instance' );
				$instance_property->setAccessible( true );
				$instance = $instance_property->getValue();

				if ( $instance === null ) {
					// Initialize Extended_Settings which loads all modules.
					\Simple_History\AddOns\Pro\Extended_Settings::get_instance( $simple_history );
				}
			} catch ( \ReflectionException $e ) {
				// Fallback: just try to get the instance.
				\Simple_History\AddOns\Pro\Extended_Settings::get_instance( $simple_history );
			}
		}
	}

	/**
	 * Manually instantiate premium loggers that were registered after
	 * the normal logger loading phase (after_setup_theme) has completed.
	 */
	private function instantiate_premium_loggers(): void {
		$simple_history = \Simple_History\Simple_History::get_instance();
		$external_loggers = $simple_history->get_external_loggers();
		$instantiated_loggers = $simple_history->get_instantiated_loggers();

		foreach ( $external_loggers as $logger_class ) {
			if ( ! class_exists( $logger_class ) ) {
				continue;
			}

			// Instantiate the logger.
			$logger_instance = new $logger_class( $simple_history );
			$slug = $logger_instance->get_slug();

			// Skip if already instantiated.
			if ( isset( $instantiated_loggers[ $slug ] ) ) {
				continue;
			}

			// Call loaded() to register hooks.
			if ( $logger_instance->is_enabled() ) {
				$logger_instance->loaded();
			}

			$instantiated_loggers[ $slug ] = [
				'name'     => $logger_instance->get_info_value_by_key( 'name' ),
				'instance' => $logger_instance,
			];
		}

		$simple_history->set_instantiated_loggers( $instantiated_loggers );
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
