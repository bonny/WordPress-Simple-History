<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Export_Module;
use Simple_History\AddOns\Pro\Modules\Table_Saved_Views_Module;

/**
 * The role filter (issue 390) reaches Premium's export and saved views.
 *
 * Both keep an allowlist of query keys, so a filter missing from it is
 * silently dropped: an export of "editors only" would contain every event.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit premium/RoleFilterPremiumTest
 *
 * @group premium
 */
class RoleFilterPremiumTest extends PremiumTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	public function test_export_keeps_the_role_filters() {
		$query = Export_Module::sanitize_query_args(
			[
				'user_roles'         => [ 'editor' ],
				'exclude_user_roles' => [ 'subscriber' ],
				'not_a_filter'       => 'dropped',
			]
		);

		$this->assertSame( [ 'editor' ], $query['user_roles'] ?? null );
		$this->assertSame( [ 'subscriber' ], $query['exclude_user_roles'] ?? null );
		$this->assertArrayNotHasKey( 'not_a_filter', $query );
	}

	public function test_saved_view_keeps_the_role_filters() {
		$method = new ReflectionMethod( Table_Saved_Views_Module::class, 'sanitize_query' );
		$method->setAccessible( true );

		$query = $method->invoke(
			null,
			[
				'roles'         => 'administrator,editor',
				'exclude-roles' => 'subscriber',
			]
		);

		$this->assertSame( 'administrator,editor', $query['roles'] ?? null );
		$this->assertSame( 'subscriber', $query['exclude-roles'] ?? null );
	}
}
