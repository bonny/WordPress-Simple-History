<?php

use Simple_History\Services\CLI_Origin_Detector;

/**
 * Test the WP-CLI origin context: parsing, validation and who can see it.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit CLIOriginDetectorTest
 */
class CLIOriginDetectorTest extends \Codeception\TestCase\WPTestCase {
	public function test_full_ssh_sudo_context() {
		$context = CLI_Origin_Detector::build_context(
			[
				'SSH_CONNECTION' => '203.0.113.5 52144 198.51.100.10 22',
				'SUDO_USER'      => 'par',
			],
			'www-data',
			[ 'plugin', 'deactivate' ]
		);

		$this->assertSame(
			[
				'_cli_command'       => 'plugin deactivate',
				'_cli_process_user'  => 'www-data',
				'_cli_sudo_user'     => 'par',
				'_cli_ssh_client_ip' => '203.0.113.x',
			],
			$context
		);
	}

	public function test_no_ssh_variables_adds_only_command_and_user() {
		$context = CLI_Origin_Detector::build_context(
			[
				'SSH_CONNECTION' => false,
				'SUDO_USER'      => false,
			],
			'deploy',
			[ 'cron', 'event', 'run' ]
		);

		$this->assertSame(
			[
				'_cli_command'      => 'cron event run',
				'_cli_process_user' => 'deploy',
			],
			$context
		);
	}

	public function test_full_ip_is_kept_when_masking_is_off() {
		add_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );

		$context = CLI_Origin_Detector::build_context( [ 'SSH_CONNECTION' => '203.0.113.5 52144 198.51.100.10 22' ], null, [] );

		remove_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );

		$this->assertSame( [ '_cli_ssh_client_ip' => '203.0.113.5' ], $context );
	}

	public function test_ipv6_ssh_client_is_masked() {
		$context = CLI_Origin_Detector::build_context( [ 'SSH_CONNECTION' => '2001:db8:1:2:3:4:5:6 52144 2001:db8::1 22' ], null, [] );

		$this->assertSame( [ '_cli_ssh_client_ip' => '2001:db8:1:2::' ], $context );
	}

	/**
	 * @dataProvider invalid_ssh_connection_provider
	 *
	 * @param mixed $ssh_connection Value of SSH_CONNECTION.
	 */
	public function test_invalid_ssh_connection_is_dropped( $ssh_connection ) {
		$this->assertNull( CLI_Origin_Detector::parse_ssh_client_ip( $ssh_connection ) );
	}

	public function invalid_ssh_connection_provider() {
		return [
			'unset'               => [ false ],
			'empty'               => [ '' ],
			'ssh_client format'   => [ '203.0.113.5 52144 22' ],
			'not an ip'           => [ 'evil.example 1 2.2.2.2 22' ],
			'html'                => [ '<script>alert(1)</script> 1 2.2.2.2 22' ],
			'newline injection'   => [ "203.0.113.5\n<14>fake 1 2 3" ],
			'extra fields'        => [ '203.0.113.5 1 2.2.2.2 22 extra' ],
		];
	}

	/**
	 * @dataProvider invalid_username_provider
	 *
	 * @param mixed $username Username.
	 */
	public function test_invalid_username_is_dropped( $username ) {
		$this->assertNull( CLI_Origin_Detector::sanitize_username( $username ) );
	}

	public function invalid_username_provider() {
		return [
			'unset'     => [ false ],
			'null'      => [ null ],
			'empty'     => [ '' ],
			'html'      => [ '<b>root</b>' ],
			'space'     => [ 'par thernstrom' ],
			'newline'   => [ "par\nroot" ],
			'too long'  => [ str_repeat( 'a', 33 ) ],
		];
	}

	public function test_valid_usernames_are_kept() {
		$this->assertSame( 'www-data', CLI_Origin_Detector::sanitize_username( 'www-data' ) );
		$this->assertSame( 'first.last_1', CLI_Origin_Detector::sanitize_username( 'first.last_1' ) );
	}

	public function test_command_path_with_odd_names_is_dropped() {
		$this->assertNull( CLI_Origin_Detector::sanitize_command_path( [] ) );
		$this->assertNull( CLI_Origin_Detector::sanitize_command_path( [ 'option', 'update secret' ] ) );
		$this->assertNull( CLI_Origin_Detector::sanitize_command_path( [ 'eval', '<?php' ] ) );
		$this->assertSame( 'db query', CLI_Origin_Detector::sanitize_command_path( [ 'db', 'query' ] ) );
		$this->assertSame( 'simple-history list', CLI_Origin_Detector::sanitize_command_path( [ 'simple-history', 'list' ] ) );
	}

	/**
	 * Log an event with WP-CLI origin context and fetch it through the REST API.
	 *
	 * @return array REST response data for the event.
	 */
	private function get_rest_event_with_cli_origin() {
		SimpleLogger()->info(
			'Test event from WP-CLI',
			[
				'_cli_command'       => 'plugin deactivate',
				'_cli_process_user'  => 'www-data',
				'_cli_sudo_user'     => 'par',
				'_cli_ssh_client_ip' => '203.0.113.x',
			]
		);

		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( '_fields', 'id,cli_origin,context' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		return $response->get_data()[0];
	}

	public function test_admin_sees_full_cli_origin() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$event = $this->get_rest_event_with_cli_origin();

		$this->assertSame(
			[
				'command'       => 'plugin deactivate',
				'process_user'  => 'www-data',
				'sudo_user'     => 'par',
				'ssh_client_ip' => '203.0.113.x',
			],
			$event['cli_origin']
		);
		$this->assertSame( 'par', $event['context']['_cli_sudo_user'] );
	}

	public function test_editor_sees_command_but_no_users_or_ip() {
		// The event is logged by an admin, then read by an editor.
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info(
			'Test event from WP-CLI',
			[
				'_cli_command'       => 'plugin deactivate',
				'_cli_process_user'  => 'www-data',
				'_cli_sudo_user'     => 'par',
				'_cli_ssh_client_ip' => '203.0.113.x',
			]
		);

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( '_fields', 'id,cli_origin,context' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$event = $response->get_data()[0];

		$this->assertSame( [ 'command' => 'plugin deactivate' ], $event['cli_origin'] );
		$this->assertArrayHasKey( '_cli_command', $event['context'] );
		$this->assertArrayNotHasKey( '_cli_process_user', $event['context'] );
		$this->assertArrayNotHasKey( '_cli_sudo_user', $event['context'] );
		$this->assertArrayNotHasKey( '_cli_ssh_client_ip', $event['context'] );
	}

	public function test_event_without_cli_origin_has_null() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'Test event from the web' );

		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( '_fields', 'id,cli_origin' );

		$event = rest_do_request( $request )->get_data()[0];

		$this->assertNull( $event['cli_origin'] );
	}

	/**
	 * Count events found through the REST API with the given query params.
	 *
	 * @param array $params Query params.
	 * @return int
	 */
	private function count_rest_events( $params ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( '_fields', 'id' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		return count( $response->get_data() );
	}

	public function test_editor_cannot_search_or_filter_on_hidden_cli_keys() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info(
			'Test event from WP-CLI',
			[
				'_cli_command'       => 'plugin deactivate',
				'_cli_process_user'  => 'secretuser',
				'_cli_ssh_client_ip' => '198.18.7.x',
			]
		);

		$filter_by_user = [ 'context_filters' => [ '_cli_process_user' => 'secretuser' ] ];
		$search_ip      = [ 'metadata_search' => '198.18.7' ];
		$filter_command = [ 'context_filters' => [ '_cli_command' => 'plugin deactivate' ] ];

		// Admins can find the event by its server user and SSH address.
		$this->assertSame( 1, $this->count_rest_events( $filter_by_user ) );
		$this->assertSame( 1, $this->count_rest_events( $search_ip ) );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		// For an editor the hidden keys must not narrow the result, or each
		// query would confirm or rule out a guessed username or address.
		$all_events = $this->count_rest_events( [] );

		$this->assertGreaterThan( 1, $all_events );
		$this->assertSame( $all_events, $this->count_rest_events( $filter_by_user ) );
		$this->assertSame( 0, $this->count_rest_events( $search_ip ) );

		// The command is not hidden, so filtering on it still works.
		$this->assertSame( 1, $this->count_rest_events( $filter_command ) );
	}
}
