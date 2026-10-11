<?php

use Simple_History\Services\Abilities_Service;
use Simple_History\Services\Stealth_Mode;

/**
 * Stealth Mode must hide the log from the REST API and Abilities too, not
 * only from the admin screens.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit StealthModeRestTest
 */
class StealthModeRestTest extends \Codeception\TestCase\WPTestCase {
	public function setUp(): void {
		parent::setUp();

		// Fire rest_api_init so the Simple History routes are registered.
		rest_get_server();
	}

	private function allow_emails( array $emails ) {
		add_filter(
			'simple_history/stealth_mode_allowed_emails',
			function () use ( $emails ) {
				return $emails;
			}
		);
	}

	private function login_admin( $email ) {
		wp_set_current_user(
			$this->factory->user->create(
				[
					'role'       => 'administrator',
					'user_email' => $email,
				]
			)
		);
	}

	private function get_events_status() {
		return rest_do_request( new WP_REST_Request( 'GET', '/simple-history/v1/events' ) )->get_status();
	}

	public function test_events_readable_when_stealth_mode_is_off() {
		$this->login_admin( 'admin@example.org' );

		$this->assertSame( 200, $this->get_events_status() );
	}

	public function test_events_hidden_from_user_outside_allowlist() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$this->assertSame( 404, $this->get_events_status() );
	}

	public function test_events_readable_by_user_on_allowlist() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.com' );

		$this->assertSame( 200, $this->get_events_status() );
	}

	public function test_full_stealth_mode_hides_events_from_admins() {
		add_filter( 'simple_history/full_stealth_mode_enabled', '__return_true' );
		$this->login_admin( 'admin@example.com' );

		$this->assertSame( 404, $this->get_events_status() );
	}

	public function test_other_namespaces_are_not_affected() {
		add_filter( 'simple_history/full_stealth_mode_enabled', '__return_true' );
		$this->login_admin( 'admin@example.com' );

		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users/me' ) )->get_status() );
	}

	public function test_rest_index_hides_namespace_from_user_outside_allowlist() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/' ) )->get_data();

		$this->assertNotContains( Stealth_Mode::REST_NAMESPACE, $data['namespaces'] );
		$this->assertEmpty( preg_grep( '#^/simple-history/v1#', array_keys( $data['routes'] ) ) );
	}

	public function test_abilities_permission_denied_outside_allowlist() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$abilities = Simple_History\Simple_History::get_instance()->get_service( Abilities_Service::class );

		if ( ! $abilities instanceof Abilities_Service ) {
			$abilities = new Abilities_Service( Simple_History\Simple_History::get_instance() );
		}

		$this->assertFalse( $abilities->check_events_permission() );
		$this->assertFalse( $abilities->check_stats_permission() );
	}

	public function test_uppercase_route_is_blocked_too() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/SIMPLE-HISTORY/v1/events' ) )->get_status() );
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/Simple-History/V1/events' ) )->get_status() );
	}

	public function test_options_request_is_blocked() {
		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		// Must look exactly like an OPTIONS request to a route that does not
		// exist. WordPress 6.8 answers that with a 404, 7.1 with an empty 200.
		$hidden  = rest_do_request( new WP_REST_Request( 'OPTIONS', '/simple-history/v1/events' ) );
		$unknown = rest_do_request( new WP_REST_Request( 'OPTIONS', '/no-such-plugin/v1/things' ) );

		$this->assertSame( $unknown->get_status(), $hidden->get_status() );
		$this->assertSame( $unknown->get_data(), $hidden->get_data() );
	}

	public function test_view_history_helper_respects_stealth_mode() {
		$this->login_admin( 'admin@example.org' );
		$this->assertTrue( Simple_History\Helpers::current_user_can_view_history() );

		$this->allow_emails( [ '@example.com' ] );
		$this->assertFalse( Simple_History\Helpers::current_user_can_view_history() );

		$this->login_admin( 'admin@example.com' );
		$this->assertTrue( Simple_History\Helpers::current_user_can_view_history() );
	}

	public function test_abilities_lists_hide_simple_history_outside_allowlist() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API needs WordPress 6.9 or later.' );
		}

		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$abilities = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' ) )->get_data();
		$this->assertEmpty( array_filter( wp_list_pluck( $abilities, 'name' ), fn( $name ) => strpos( $name, 'simple-history/' ) === 0 ) );

		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/simple-history/get-recent-events' ) )->get_status() );
		$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/categories/simple-history' ) )->get_status() );
	}

	public function test_other_plugins_routes_in_namespace_are_kept() {
		// Registered after rest_api_init, which core flags. The route still works.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		register_rest_route(
			Stealth_Mode::REST_NAMESPACE,
			'/plugins',
			[
				'methods'             => 'GET',
				'callback'            => function () {
					return [ 'ok' => true ];
				},
				'permission_callback' => '__return_true',
			]
		);

		add_filter( 'simple_history/full_stealth_mode_enabled', '__return_true' );
		wp_set_current_user( 0 );

		$this->assertSame( 200, rest_do_request( new WP_REST_Request( 'GET', '/simple-history/v1/plugins' ) )->get_status() );
		$this->assertSame( 404, $this->get_events_status() );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/' ) )->get_data();
		$this->assertContains( Stealth_Mode::REST_NAMESPACE, $data['namespaces'] );
	}

	public function test_allowlist_ignores_email_case() {
		$this->allow_emails( [ '@Example.com', 'Jane@Organization.org' ] );

		$this->assertTrue( Stealth_Mode::is_user_email_allowed_in_stealth_mode( 'Admin@EXAMPLE.com' ) );
		$this->assertTrue( Stealth_Mode::is_user_email_allowed_in_stealth_mode( 'jane@organization.org' ) );
		$this->assertFalse( Stealth_Mode::is_user_email_allowed_in_stealth_mode( 'admin@example.org' ) );
	}

	public function test_abilities_list_total_header_matches_body() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API needs WordPress 6.9 or later.' );
		}

		$this->allow_emails( [ '@example.com' ] );
		$this->login_admin( 'admin@example.org' );

		$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' );
		$request->set_param( 'category', 'simple-history' );
		$response = rest_do_request( $request );

		$this->assertSame( [], $response->get_data() );
		$this->assertSame( '0', (string) $response->get_headers()['X-WP-Total'] );
	}
}
