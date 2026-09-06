<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Requirements;
use function Simple_History\AddOns\Pro\core_version_meets_minimum;

/**
 * Issue 292, gap 1: the minimum-core-version gate is the check that keeps
 * premium from fataling on an older core. It had no test, which is how a
 * fatal on the minimum supported WordPress once sat unnoticed for 16
 * releases.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit premium/PremiumRequirementsTest
 *
 * @group premium
 */
class PremiumRequirementsTest extends PremiumTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * @dataProvider core_versions
	 */
	public function test_core_version_gate( $core_version, string $minimum, bool $expected ) {
		$this->assertSame( $expected, core_version_meets_minimum( $core_version, $minimum ) );
	}

	public function core_versions(): array {
		return [
			'older patch release'        => [ '5.28.9', '5.29.0', false ],
			'exactly the minimum'        => [ '5.29.0', '5.29.0', true ],
			'newer minor'                => [ '5.30.0', '5.29.0', true ],
			'newer major'                => [ '6.0.0', '5.29.0', true ],
			'beta of a newer version'    => [ '5.30.0-beta1', '5.29.0', true ],
			'beta of the minimum itself' => [ '5.29.0-beta1', '5.29.0', false ],
			'core version constant gone' => [ null, '5.29.0', false ],
			'empty string'               => [ '', '5.29.0', false ],
		];
	}

	public function test_shipped_minimum_core_version_is_met_by_the_core_in_this_checkout() {
		$this->assertTrue(
			core_version_meets_minimum( SIMPLE_HISTORY_VERSION, SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION ),
			sprintf(
				'Premium requires core %s but this checkout is %s. Either bump core or lower SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION.',
				SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION,
				SIMPLE_HISTORY_VERSION
			)
		);
	}

	public function test_requirements_php_check() {
		$this->assertTrue( ( new Requirements() )->php( '7.4' )->met() );
		$this->assertFalse( ( new Requirements() )->php( '99.0' )->met() );
	}

	public function test_requirements_wp_check() {
		$this->assertTrue( ( new Requirements() )->wp( '6.3' )->met() );
		$this->assertFalse( ( new Requirements() )->wp( '99.0' )->met() );
	}

	public function test_requirements_stay_unmet_once_one_check_fails() {
		$this->assertFalse( ( new Requirements() )->php( '99.0' )->wp( '6.3' )->met() );
		$this->assertFalse( ( new Requirements() )->wp( '99.0' )->php( '7.4' )->met() );
	}
}
