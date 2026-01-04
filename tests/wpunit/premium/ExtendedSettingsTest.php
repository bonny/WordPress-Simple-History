<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Extended_Settings;

/**
 * Tests for the Extended_Settings class.
 *
 * @group premium
 * @group extended-settings
 */
class ExtendedSettingsTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * Test Extended_Settings class exists.
	 */
	public function test_extended_settings_class_exists(): void {
		$this->assertTrue(
			class_exists( Extended_Settings::class ),
			'Extended_Settings class should exist.'
		);
	}

	/**
	 * Test Extended_Settings is a singleton.
	 */
	public function test_extended_settings_is_singleton(): void {
		$instance1 = Extended_Settings::get_instance();
		$instance2 = Extended_Settings::get_instance();

		$this->assertSame( $instance1, $instance2, 'Extended_Settings should be a singleton.' );
	}

	/**
	 * Test get_modules returns array.
	 */
	public function test_get_modules_returns_array(): void {
		$instance = Extended_Settings::get_instance();
		$modules  = $instance->get_modules();

		$this->assertIsArray( $modules );
		$this->assertNotEmpty( $modules );
	}

	/**
	 * Test get_modules includes Alerts_Module.
	 */
	public function test_get_modules_includes_alerts_module(): void {
		$instance = Extended_Settings::get_instance();
		$modules  = $instance->get_modules();

		$this->assertContains(
			'Simple_History\AddOns\Pro\Modules\Alerts_Module',
			$modules,
			'Modules should include Alerts_Module.'
		);
	}

	/**
	 * Test get_modules includes Stats_Module.
	 */
	public function test_get_modules_includes_stats_module(): void {
		$instance = Extended_Settings::get_instance();
		$modules  = $instance->get_modules();

		$this->assertContains(
			'Simple_History\AddOns\Pro\Modules\Stats_Module',
			$modules,
			'Modules should include Stats_Module.'
		);
	}

	/**
	 * Test get_modules includes Export_Module.
	 */
	public function test_get_modules_includes_export_module(): void {
		$instance = Extended_Settings::get_instance();
		$modules  = $instance->get_modules();

		$this->assertContains(
			'Simple_History\AddOns\Pro\Modules\Export_Module',
			$modules,
			'Modules should include Export_Module.'
		);
	}

	/**
	 * Test get_instantiated_modules returns array.
	 */
	public function test_get_instantiated_modules_returns_array(): void {
		$instance = Extended_Settings::get_instance();
		$modules  = $instance->get_instantiated_modules();

		$this->assertIsArray( $modules );
	}

	/**
	 * Test get_instantiated_module with valid class.
	 */
	public function test_get_instantiated_module_with_valid_class(): void {
		$instance = Extended_Settings::get_instance();

		$alerts_module = $instance->get_instantiated_module(
			'Simple_History\AddOns\Pro\Modules\Alerts_Module'
		);

		$this->assertNotNull( $alerts_module, 'Should find Alerts_Module instance.' );
	}

	/**
	 * Test get_instantiated_module with invalid class returns null.
	 */
	public function test_get_instantiated_module_with_invalid_class_returns_null(): void {
		$instance = Extended_Settings::get_instance();

		$fake_module = $instance->get_instantiated_module(
			'Simple_History\AddOns\Pro\Modules\FakeNonExistentModule'
		);

		$this->assertNull( $fake_module, 'Should return null for non-existent module.' );
	}

	/**
	 * Test is_on_settings_page is static method.
	 */
	public function test_is_on_settings_page_is_callable(): void {
		$this->assertTrue(
			method_exists( Extended_Settings::class, 'is_on_settings_page' ),
			'is_on_settings_page method should exist.'
		);
	}
}
