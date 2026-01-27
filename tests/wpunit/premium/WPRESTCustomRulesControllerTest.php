<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Alerts_Module;
use Simple_History\AddOns\Pro\WP_REST_Custom_Rules_Controller;

/**
 * Tests for WP_REST_Custom_Rules_Controller.
 *
 * @group premium
 * @group alerts
 * @group rest-api
 * @group custom-rules
 */
class WPRESTCustomRulesControllerTest extends PremiumTestCase {
	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	/**
	 * Admin user ID for authenticated requests.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	/**
	 * Subscriber user ID for unauthorized requests.
	 *
	 * @var int
	 */
	protected $subscriber_user_id;

	/**
	 * Test destination ID.
	 *
	 * @var string
	 */
	protected $test_destination_id;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		// Set up REST server.
		global $wp_rest_server;
		$wp_rest_server = null;
		$this->server   = rest_get_server();

		// Register the custom rules controller routes.
		$controller = new WP_REST_Custom_Rules_Controller();
		$controller->register_routes();

		// Create admin user.
		$this->admin_user_id = wp_insert_user(
			[
				'user_login' => 'testadmin_' . wp_generate_uuid4(),
				'user_pass'  => 'password',
				'user_email' => 'admin_' . wp_generate_uuid4() . '@example.com',
				'role'       => 'administrator',
			]
		);

		// Create subscriber user.
		$this->subscriber_user_id = wp_insert_user(
			[
				'user_login' => 'testsub_' . wp_generate_uuid4(),
				'user_pass'  => 'password',
				'user_email' => 'sub_' . wp_generate_uuid4() . '@example.com',
				'role'       => 'subscriber',
			]
		);

		// Clear any existing custom rules.
		delete_option( Alerts_Module::OPTION_CUSTOM_RULES );

		// Create a test destination for rule creation tests.
		$this->test_destination_id = 'dest_test_' . wp_generate_uuid4();
		$destinations              = [
			$this->test_destination_id => [
				'type'   => 'email',
				'name'   => 'Test Destination',
				'config' => [
					'recipients' => 'test@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		// Clean up users.
		if ( $this->admin_user_id ) {
			wp_delete_user( $this->admin_user_id );
		}
		if ( $this->subscriber_user_id ) {
			wp_delete_user( $this->subscriber_user_id );
		}

		// Clean up options.
		delete_option( Alerts_Module::OPTION_CUSTOM_RULES );
		delete_option( Alerts_Module::OPTION_DESTINATIONS );

		parent::tearDown();
	}

	// =========================================================================
	// Route Registration Tests
	// =========================================================================

	/**
	 * Test that routes are registered.
	 */
	public function test_routes_are_registered(): void {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/simple-history/v1/alerts/rules', $routes );
		$this->assertArrayHasKey( '/simple-history/v1/alerts/rules/(?P<id>[a-zA-Z0-9_-]+)', $routes );
	}

	// =========================================================================
	// Permission Tests
	// =========================================================================

	/**
	 * Test that unauthenticated requests are rejected.
	 */
	public function test_get_items_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test that subscribers cannot access rules.
	 */
	public function test_get_items_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test that admins can access rules.
	 */
	public function test_get_items_allowed_for_admin(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test that subscribers cannot create rules.
	 */
	public function test_create_item_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Test Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	// =========================================================================
	// GET /alerts/rules Tests
	// =========================================================================

	/**
	 * Test getting empty rules list.
	 */
	public function test_get_items_returns_empty_array(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test getting rules list with items.
	 */
	public function test_get_items_returns_rules(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule directly.
		$rules = [
			'rule_test_123' => [
				'name'         => 'Test Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertEquals( 'rule_test_123', $data[0]['id'] );
		$this->assertEquals( 'Test Rule', $data[0]['name'] );
		$this->assertTrue( $data[0]['enabled'] );
	}

	// =========================================================================
	// POST /alerts/rules Tests (Create)
	// =========================================================================

	/**
	 * Test creating a custom rule.
	 */
	public function test_create_rule(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Error Events',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertStringStartsWith( 'rule_', $data['id'] );
		$this->assertEquals( 'Error Events', $data['name'] );
		$this->assertTrue( $data['enabled'] );
		$this->assertContains( $this->test_destination_id, $data['destinations'] );
	}

	/**
	 * Test creating rule with AND conditions.
	 */
	public function test_create_rule_with_and_conditions(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'User Errors',
				'conditions'   => [
					'and' => [
						[ '==' => [ [ 'var' => 'logger' ], 'SimpleUserLogger' ] ],
						[ '==' => [ [ 'var' => 'level' ], 'error' ] ],
					],
				],
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'conditions', $data );
		$this->assertArrayHasKey( 'and', $data['conditions'] );
	}

	/**
	 * Test creating rule defaults to enabled.
	 */
	public function test_create_rule_defaults_enabled(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Default Enabled Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
				'destinations' => [ $this->test_destination_id ],
				// Not passing 'enabled' - should default to true.
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['enabled'] );
	}

	/**
	 * Test creating rule with empty name fails.
	 */
	public function test_create_rule_empty_name_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => '   ', // Whitespace only.
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating rule with empty conditions fails.
	 */
	public function test_create_rule_empty_conditions_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Empty Conditions Rule',
				'conditions'   => [ 'and' => [] ], // Empty AND array.
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_empty_conditions', $data['code'] );
	}

	/**
	 * Test creating rule with null conditions fails.
	 */
	public function test_create_rule_null_conditions_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Null Conditions Rule',
				'conditions'   => null,
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating rule with invalid destination fails.
	 */
	public function test_create_rule_invalid_destination_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Invalid Dest Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ 'nonexistent_destination' ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_invalid_destinations', $data['code'] );
	}

	/**
	 * Test creating rule with empty destinations fails.
	 */
	public function test_create_rule_empty_destinations_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'No Dest Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_empty_destinations', $data['code'] );
	}

	/**
	 * Test max rules limit is enforced.
	 */
	public function test_create_rule_max_limit_enforced(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create rules up to the limit.
		$rules = [];
		for ( $i = 0; $i < WP_REST_Custom_Rules_Controller::MAX_RULES; $i++ ) {
			$rules[ 'rule_' . $i ] = [
				'name'         => 'Rule ' . $i,
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'info' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			];
		}
		Alerts_Module::save_custom_rules( $rules );

		// Try to create one more.
		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'One Too Many',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_max_rules_reached', $data['code'] );
	}

	// =========================================================================
	// GET /alerts/rules/{id} Tests
	// =========================================================================

	/**
	 * Test getting a single rule.
	 */
	public function test_get_item_returns_rule(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_single_test' => [
				'name'         => 'Single Test Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => false,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules/rule_single_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'rule_single_test', $data['id'] );
		$this->assertEquals( 'Single Test Rule', $data['name'] );
		$this->assertFalse( $data['enabled'] );
	}

	/**
	 * Test getting non-existent rule returns 404.
	 */
	public function test_get_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules/nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// =========================================================================
	// PUT /alerts/rules/{id} Tests (Update)
	// =========================================================================

	/**
	 * Test updating a rule name.
	 */
	public function test_update_rule_name(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_update_test' => [
				'name'         => 'Original Name',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/rule_update_test' );
		$request->set_body_params(
			[
				'name' => 'Updated Name',
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Updated Name', $data['name'] );
		// Other fields should remain unchanged.
		$this->assertTrue( $data['enabled'] );
	}

	/**
	 * Test updating rule conditions.
	 */
	public function test_update_rule_conditions(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_cond_test' => [
				'name'         => 'Conditions Test',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$new_conditions = [ '==' => [ [ 'var' => 'level' ], 'warning' ] ];

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/rule_cond_test' );
		$request->set_body_params(
			[
				'conditions' => $new_conditions,
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $new_conditions, $data['conditions'] );
	}

	/**
	 * Test updating rule enabled status.
	 */
	public function test_update_rule_enabled(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create an enabled rule.
		$rules = [
			'rule_enabled_test' => [
				'name'         => 'Enabled Test',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/rule_enabled_test' );
		$request->set_body_params(
			[
				'enabled' => false,
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $data['enabled'] );
	}

	/**
	 * Test updating rule destinations.
	 */
	public function test_update_rule_destinations(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create another destination.
		$second_dest_id = 'dest_second_' . wp_generate_uuid4();
		$destinations   = Alerts_Module::get_destinations();
		$destinations[ $second_dest_id ] = [
			'type'   => 'email',
			'name'   => 'Second Destination',
			'config' => [
				'recipients' => 'second@example.com',
			],
		];
		Alerts_Module::save_destinations( $destinations );

		// Create a rule with original destination.
		$rules = [
			'rule_dest_test' => [
				'name'         => 'Destination Test',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		// Update to use both destinations.
		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/rule_dest_test' );
		$request->set_body_params(
			[
				'destinations' => [ $this->test_destination_id, $second_dest_id ],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 2, $data['destinations'] );
		$this->assertContains( $this->test_destination_id, $data['destinations'] );
		$this->assertContains( $second_dest_id, $data['destinations'] );
	}

	/**
	 * Test updating non-existent rule returns 404.
	 */
	public function test_update_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/nonexistent' );
		$request->set_body_params(
			[
				'name' => 'Updated',
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test updating rule with invalid conditions fails.
	 */
	public function test_update_rule_invalid_conditions_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_invalid_cond' => [
				'name'         => 'Invalid Cond Test',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/rules/rule_invalid_cond' );
		$request->set_body_params(
			[
				'conditions' => [ 'and' => [] ], // Empty conditions.
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	// =========================================================================
	// DELETE /alerts/rules/{id} Tests
	// =========================================================================

	/**
	 * Test deleting a rule.
	 */
	public function test_delete_rule(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_delete_test' => [
				'name'         => 'To Be Deleted',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request  = new WP_REST_Request( 'DELETE', '/simple-history/v1/alerts/rules/rule_delete_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['deleted'] );
		$this->assertArrayHasKey( 'previous', $data );
		$this->assertEquals( 'To Be Deleted', $data['previous']['name'] );

		// Verify it's actually deleted.
		$remaining = Alerts_Module::get_custom_rules();
		$this->assertArrayNotHasKey( 'rule_delete_test', $remaining );
	}

	/**
	 * Test deleting non-existent rule returns 404.
	 */
	public function test_delete_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'DELETE', '/simple-history/v1/alerts/rules/nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// =========================================================================
	// Response Format Tests
	// =========================================================================

	/**
	 * Test response includes all expected fields.
	 */
	public function test_response_includes_all_fields(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a rule.
		$rules = [
			'rule_fields_test' => [
				'name'         => 'Fields Test',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules/rule_fields_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'conditions', $data );
		$this->assertArrayHasKey( 'destinations', $data );
		$this->assertArrayHasKey( 'enabled', $data );
	}

	/**
	 * Test list response is an array of rules.
	 */
	public function test_list_response_is_array(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create multiple rules.
		$rules = [
			'rule_1' => [
				'name'         => 'Rule 1',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => true,
			],
			'rule_2' => [
				'name'         => 'Rule 2',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'warning' ] ],
				'destinations' => [ $this->test_destination_id ],
				'enabled'      => false,
			],
		];
		Alerts_Module::save_custom_rules( $rules );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/rules' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertCount( 2, $data );

		// Each item should have an id.
		foreach ( $data as $item ) {
			$this->assertArrayHasKey( 'id', $item );
		}
	}

	// =========================================================================
	// Validation Edge Cases
	// =========================================================================

	/**
	 * Test that name is sanitized.
	 */
	public function test_name_is_sanitized(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => '<script>alert("xss")</script>My Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id ],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		// Script tags should be stripped.
		$this->assertStringNotContainsString( '<script>', $data['name'] );
		$this->assertStringContainsString( 'My Rule', $data['name'] );
	}

	/**
	 * Test multiple destinations are validated.
	 */
	public function test_multiple_destinations_validated(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/rules' );
		$request->set_body_params(
			[
				'name'         => 'Multi Dest Rule',
				'conditions'   => [ '==' => [ [ 'var' => 'level' ], 'error' ] ],
				'destinations' => [ $this->test_destination_id, 'invalid_dest' ],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_invalid_destinations', $data['code'] );
		$this->assertStringContainsString( 'invalid_dest', $data['message'] );
	}
}
