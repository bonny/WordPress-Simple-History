<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Alerts_Module;
use Simple_History\AddOns\Pro\WP_REST_Destinations_Controller;

/**
 * Tests for WP_REST_Destinations_Controller.
 *
 * @group premium
 * @group alerts
 * @group rest-api
 */
class WPRESTDestinationsControllerTest extends PremiumTestCase {
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
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		// Set up REST server - must be done after activating premium.
		global $wp_rest_server;
		$wp_rest_server = null;
		$this->server   = rest_get_server();

		// Register the destinations controller routes directly.
		$controller = new WP_REST_Destinations_Controller();
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

		// Clear any existing destinations.
		delete_option( Alerts_Module::OPTION_DESTINATIONS );
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

		$this->assertArrayHasKey( '/simple-history/v1/alerts/destinations', $routes );
		$this->assertArrayHasKey( '/simple-history/v1/alerts/destinations/(?P<id>[a-zA-Z0-9_-]+)', $routes );
		$this->assertArrayHasKey( '/simple-history/v1/alerts/destinations/(?P<id>[a-zA-Z0-9_-]+)/test', $routes );
	}

	// =========================================================================
	// Permission Tests
	// =========================================================================

	/**
	 * Test that unauthenticated requests are rejected.
	 */
	public function test_get_items_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test that subscribers cannot access destinations.
	 */
	public function test_get_items_requires_manage_options(): void {
		wp_set_current_user( $this->subscriber_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test that admins can access destinations.
	 */
	public function test_get_items_allowed_for_admin(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	// =========================================================================
	// GET /destinations Tests
	// =========================================================================

	/**
	 * Test getting empty destinations list.
	 */
	public function test_get_items_returns_empty_array(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test getting destinations list with items.
	 */
	public function test_get_items_returns_destinations(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination directly.
		$destinations = [
			'dest_test_123' => [
				'type'   => 'email',
				'name'   => 'Test Email',
				'config' => [
					'recipients' => 'test@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertEquals( 'dest_test_123', $data[0]['id'] );
		$this->assertEquals( 'email', $data[0]['type'] );
		$this->assertEquals( 'Test Email', $data[0]['name'] );
	}

	// =========================================================================
	// POST /destinations Tests (Create)
	// =========================================================================

	/**
	 * Test creating an email destination.
	 */
	public function test_create_email_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'email',
				'name'   => 'Admin Alerts',
				'config' => [
					'recipients' => 'admin@example.com',
				],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertStringStartsWith( 'dest_', $data['id'] );
		$this->assertEquals( 'email', $data['type'] );
		$this->assertEquals( 'Admin Alerts', $data['name'] );
	}

	/**
	 * Test creating a Slack destination.
	 */
	public function test_create_slack_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'slack',
				'name'   => '#alerts',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXX',
				],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'slack', $data['type'] );
		$this->assertEquals( '#alerts', $data['name'] );
	}

	/**
	 * Test creating a Discord destination.
	 */
	public function test_create_discord_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'discord',
				'name'   => 'Discord Alerts',
				'config' => [
					'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
				],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'discord', $data['type'] );
	}

	/**
	 * Test creating a Telegram destination.
	 */
	public function test_create_telegram_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'telegram',
				'name'   => 'Telegram Alerts',
				'config' => [
					'bot_token' => '123456:ABC-DEF',
					'chat_id'   => '-1001234567890',
				],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'telegram', $data['type'] );
	}

	/**
	 * Test creating destination with invalid type fails.
	 */
	public function test_create_destination_invalid_type(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'invalid_type',
				'name'   => 'Test',
				'config' => [],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating email destination without recipients fails.
	 */
	public function test_create_email_without_recipients_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'email',
				'name'   => 'Test',
				'config' => [
					'recipients' => '',
				],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating email destination with invalid email fails.
	 */
	public function test_create_email_with_invalid_email_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'email',
				'name'   => 'Test',
				'config' => [
					'recipients' => 'not-an-email',
				],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating Slack destination without webhook URL fails.
	 */
	public function test_create_slack_without_webhook_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'slack',
				'name'   => 'Test',
				'config' => [
					'webhook_url' => '',
				],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test creating destination with empty name fails.
	 */
	public function test_create_destination_empty_name_fails(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations' );
		$request->set_body_params(
			[
				'type'   => 'email',
				'name'   => '   ', // Whitespace only.
				'config' => [
					'recipients' => 'test@example.com',
				],
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	// =========================================================================
	// GET /destinations/{id} Tests
	// =========================================================================

	/**
	 * Test getting a single destination.
	 */
	public function test_get_item_returns_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination.
		$destinations = [
			'dest_single_test' => [
				'type'   => 'email',
				'name'   => 'Single Test',
				'config' => [
					'recipients' => 'single@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations/dest_single_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'dest_single_test', $data['id'] );
		$this->assertEquals( 'Single Test', $data['name'] );
	}

	/**
	 * Test getting non-existent destination returns 404.
	 */
	public function test_get_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations/nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// =========================================================================
	// PUT /destinations/{id} Tests (Update)
	// =========================================================================

	/**
	 * Test updating a destination name.
	 */
	public function test_update_destination_name(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination.
		$destinations = [
			'dest_update_test' => [
				'type'   => 'email',
				'name'   => 'Original Name',
				'config' => [
					'recipients' => 'original@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/destinations/dest_update_test' );
		$request->set_body_params(
			[
				'name' => 'Updated Name',
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'Updated Name', $data['name'] );
	}

	/**
	 * Test updating a destination config.
	 */
	public function test_update_destination_config(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination.
		$destinations = [
			'dest_config_test' => [
				'type'   => 'email',
				'name'   => 'Config Test',
				'config' => [
					'recipients' => 'old@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/destinations/dest_config_test' );
		$request->set_body_params(
			[
				'config' => [
					'recipients' => 'new@example.com',
				],
			]
		);

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( 'new@example.com', $data['config']['recipients'] );
	}

	/**
	 * Test updating non-existent destination returns 404.
	 */
	public function test_update_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request = new WP_REST_Request( 'PUT', '/simple-history/v1/alerts/destinations/nonexistent' );
		$request->set_body_params(
			[
				'name' => 'Updated',
			]
		);

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// =========================================================================
	// DELETE /destinations/{id} Tests
	// =========================================================================

	/**
	 * Test deleting a destination.
	 */
	public function test_delete_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination.
		$destinations = [
			'dest_delete_test' => [
				'type'   => 'email',
				'name'   => 'To Be Deleted',
				'config' => [
					'recipients' => 'delete@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'DELETE', '/simple-history/v1/alerts/destinations/dest_delete_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['deleted'] );
		$this->assertArrayHasKey( 'previous', $data );

		// Verify it's actually deleted.
		$remaining = Alerts_Module::get_destinations();
		$this->assertArrayNotHasKey( 'dest_delete_test', $remaining );
	}

	/**
	 * Test deleting non-existent destination returns 404.
	 */
	public function test_delete_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'DELETE', '/simple-history/v1/alerts/destinations/nonexistent' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	// =========================================================================
	// POST /destinations/{id}/test Tests
	// =========================================================================

	/**
	 * Test testing a destination that doesn't exist returns 404.
	 */
	public function test_test_item_not_found(): void {
		wp_set_current_user( $this->admin_user_id );

		$request  = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations/nonexistent/test' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test testing an email destination (will fail without mail setup, but should return proper response).
	 */
	public function test_test_email_destination(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination.
		$destinations = [
			'dest_test_email' => [
				'type'   => 'email',
				'name'   => 'Test Email Dest',
				'config' => [
					'recipients' => 'test@example.com',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'POST', '/simple-history/v1/alerts/destinations/dest_test_email/test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	// =========================================================================
	// Response Format Tests
	// =========================================================================

	/**
	 * Test that sensitive data is masked in responses.
	 */
	public function test_webhook_url_is_masked_in_response(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a Slack destination.
		$destinations = [
			'dest_mask_test' => [
				'type'   => 'slack',
				'name'   => 'Masked Test',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations/dest_mask_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'webhook_url_masked', $data['config'] );
		$this->assertArrayHasKey( 'has_webhook_url', $data['config'] );
		$this->assertTrue( $data['config']['has_webhook_url'] );
	}

	/**
	 * Test that tracking data is included in responses.
	 */
	public function test_tracking_data_included_in_response(): void {
		wp_set_current_user( $this->admin_user_id );

		// Create a destination with tracking data.
		$destinations = [
			'dest_tracking_test' => [
				'type'     => 'email',
				'name'     => 'Tracking Test',
				'config'   => [
					'recipients' => 'track@example.com',
				],
				'tracking' => [
					'last_success'  => time() - 3600,
					'success_count' => 5,
					'error_count'   => 1,
				],
			],
		];
		Alerts_Module::save_destinations( $destinations );

		$request  = new WP_REST_Request( 'GET', '/simple-history/v1/alerts/destinations/dest_tracking_test' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'tracking', $data );
		$this->assertArrayHasKey( 'success_count', $data['tracking'] );
		$this->assertEquals( 5, $data['tracking']['success_count'] );
	}
}
