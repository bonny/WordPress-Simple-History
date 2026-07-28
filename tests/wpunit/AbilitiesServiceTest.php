<?php

use Simple_History\Simple_History;
use Simple_History\Services\Abilities_Service;

/**
 * Registering Simple History abilities with the WordPress Abilities API.
 *
 * The Abilities API is WordPress 6.9+. Simple History supports 6.3+, so the
 * service has to no-op cleanly on older versions rather than fatal. The test
 * suite defaults to WP 6.8, so the registration tests skip unless the suite is
 * run with WORDPRESS_VERSION=6.9.
 *
 * @coversDefaultClass Simple_History\Services\Abilities_Service
 */
class AbilitiesServiceTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Skip a test when the Abilities API is not present.
	 */
	private function require_abilities_api() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API requires WordPress 6.9+. Run with WORDPRESS_VERSION=6.9.' );
		}
	}

	/**
	 * The hook is only added when the API exists, and is always added when it does.
	 *
	 * This assertion is meaningful on both WP 6.8 and 6.9, which is why it does
	 * not skip.
	 *
	 * @covers ::loaded
	 */
	public function test_hook_is_registered_only_when_abilities_api_exists() {
		remove_all_actions( 'wp_abilities_api_init' );

		$service = new Abilities_Service( Simple_History::get_instance() );
		$service->loaded();

		if ( function_exists( 'wp_register_ability' ) ) {
			$this->assertNotFalse(
				has_action( 'wp_abilities_api_init', [ $service, 'register_abilities' ] ),
				'Abilities should be registered on WordPress 6.9+.'
			);
		} else {
			$this->assertFalse(
				has_action( 'wp_abilities_api_init' ),
				'Nothing should hook the abilities init on WordPress below 6.9.'
			);
		}
	}

	/**
	 * The service must be wired into the plugin, not merely exist.
	 *
	 * @covers ::loaded
	 */
	public function test_service_is_registered_with_the_plugin() {
		$found = false;

		foreach ( Simple_History::get_instance()->get_instantiated_services() as $service ) {
			if ( $service instanceof Abilities_Service ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Abilities_Service should be in the plugin service list.' );
	}

	/**
	 * An audit log an agent can erase is worse than no audit log, so Simple
	 * History registers read abilities only — no create, update, delete or purge.
	 *
	 * @covers ::register_abilities
	 */
	public function test_registers_no_write_or_destructive_abilities() {
		$this->require_abilities_api();

		$service = new Abilities_Service( Simple_History::get_instance() );
		$service->register_abilities();

		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();

			if ( strpos( $name, 'simple-history/' ) !== 0 ) {
				continue;
			}

			foreach ( [ 'create', 'update', 'delete', 'purge', 'set', 'remove' ] as $verb ) {
				$this->assertStringNotContainsString(
					$verb,
					$name,
					'Simple History registers read abilities only.'
				);
			}
		}
	}
}
