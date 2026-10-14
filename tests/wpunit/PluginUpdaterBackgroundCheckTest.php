<?php

use Simple_History\AddOn_Plugin;
use Simple_History\Plugin_Updater;

/**
 * The updater asks the server only while WordPress saves its own update
 * check, never while update data is read on an ordinary admin page.
 */
class PluginUpdaterBackgroundCheckTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG    = 'sh-test-addon';
	private const ID      = 'sh-test-addon/index.php';
	private const OPTION  = 'simple_history_plusplugin_message_' . self::SLUG;
	private const API_URL = 'https://example.test/wp-json/lsq/v1';

	/** @var array<int,string> URLs of the requests seen. */
	private $requests = [];

	/** @var string Body of the next fake reply. */
	private $reply_body = '{"success":true,"update":{"version":"2.0.0","download_link":"https://example.test/addon.zip"}}';

	/** @var int Status code of the next fake reply. */
	private $reply_code = 200;

	/** @var Plugin_Updater|null */
	private $updater = null;

	public function setUp(): void {
		parent::setUp();

		add_filter( 'pre_http_request', [ $this, 'fake_http' ], 10, 3 );

		update_option(
			self::OPTION,
			[
				'key'           => 'abc-123',
				'key_activated' => true,
			]
		);

		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		delete_option( Plugin_Updater::get_answer_option_for_slug( self::SLUG ) );

		$this->updater = new Plugin_Updater( self::ID, self::SLUG, '1.0.0', self::API_URL );
	}

	public function tearDown(): void {
		remove_filter( 'site_transient_update_plugins', [ $this->updater, 'site_transient_update_plugins_update' ] );
		remove_filter( 'pre_set_site_transient_update_plugins', [ $this->updater, 'on_pre_set_update_plugins' ] );
		remove_filter( 'plugins_api', [ $this->updater, 'on_plugins_api_handle_plugin_info' ], 20 );
		remove_action( 'load-update-core.php', [ $this->updater, 'on_force_check' ], 9 );
		remove_filter( 'pre_http_request', [ $this, 'fake_http' ] );

		unset( $_GET['force-check'] );

		delete_option( self::OPTION );
		delete_option( 'simple_history_plusplugin_new_features_' . self::SLUG );
		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		delete_option( Plugin_Updater::get_answer_option_for_slug( self::SLUG ) );

		parent::tearDown();
	}

	public function fake_http( $preempt, $args, $url ) {
		if ( strpos( $url, self::API_URL ) === false ) {
			return $preempt;
		}

		$this->requests[] = $url;

		return [
			'headers'  => [],
			'body'     => $this->reply_body,
			'response' => [
				'code'    => $this->reply_code,
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function update_data(): object {
		return (object) [
			'checked'   => [ self::ID => '1.0.0' ],
			'response'  => [],
			'no_update' => [],
		];
	}

	public function test_reading_update_data_makes_no_request_once_an_answer_is_stored() {
		update_option( Plugin_Updater::get_answer_option_for_slug( self::SLUG ), '{"success":true}', false );

		$data = $this->updater->site_transient_update_plugins_update( $this->update_data() );

		$this->assertSame( [], $this->requests );
		$this->assertArrayNotHasKey( self::ID, $data->response );
	}

	public function test_reading_update_data_with_nothing_stored_asks_once() {
		// Right after updating from a version that stored no answer.
		$first  = $this->updater->site_transient_update_plugins_update( $this->update_data() );
		$second = $this->updater->site_transient_update_plugins_update( $this->update_data() );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( '2.0.0', $first->response[ self::ID ]->new_version );
		$this->assertSame( '2.0.0', $second->response[ self::ID ]->new_version );
	}

	public function test_reading_update_data_with_nothing_stored_and_a_failing_server_asks_once() {
		$this->reply_code = 500;
		$this->reply_body = 'Server error';

		$this->updater->site_transient_update_plugins_update( $this->update_data() );
		$this->updater->site_transient_update_plugins_update( $this->update_data() );

		$this->assertCount( 1, $this->requests );
	}

	public function test_saving_update_data_asks_once_and_the_answer_is_kept() {
		// WordPress saves the update data twice per check.
		$this->updater->on_pre_set_update_plugins( $this->update_data() );
		$data = $this->updater->on_pre_set_update_plugins( $this->update_data() );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( '2.0.0', $data->response[ self::ID ]->new_version );

		// Hours later the cache is gone, but reading still offers the update without a request.
		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		$read = $this->updater->site_transient_update_plugins_update( $this->update_data() );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( '2.0.0', $read->response[ self::ID ]->new_version );
	}

	public function test_kept_answer_holds_only_the_update_and_no_license_details() {
		$this->reply_body = '{"success":true,"update":{"version":"2.0.0","download_link":"https://example.test/addon.zip","changelog":"Long text"},"license":{"status":"active","customer_email":"someone@example.test"}}';

		$this->updater->request();

		$this->assertSame(
			'{"success":true,"update":{"version":"2.0.0","download_link":"https:\\/\\/example.test\\/addon.zip"}}',
			get_option( Plugin_Updater::get_answer_option_for_slug( self::SLUG ) )
		);
	}

	public function test_request_limits_the_response_size() {
		$args_seen = null;
		$capture   = function ( $preempt, $args ) use ( &$args_seen ) {
			$args_seen = $args;

			return $preempt;
		};

		add_filter( 'pre_http_request', $capture, 5, 2 );
		$this->updater->request();
		remove_filter( 'pre_http_request', $capture, 5 );

		$this->assertSame( 256 * 1024, $args_seen['limit_response_size'] );
	}

	public function test_saving_without_checked_plugins_makes_no_request() {
		$data          = $this->update_data();
		$data->checked = [];

		$this->updater->on_pre_set_update_plugins( $data );

		$this->assertSame( [], $this->requests );
	}

	public function test_request_sends_the_installed_version() {
		$this->updater->request();

		$this->assertStringContainsString( 'version=1.0.0', $this->requests[0] );
	}

	public function test_new_features_are_stored_from_the_answer_and_cleared_when_missing() {
		$addon = new AddOn_Plugin( self::ID, self::SLUG, '1.0.0', 'Test add-on', 1 );

		$this->reply_code = 401;
		$this->reply_body = '{"success":false,"error_code":"invalid_license_key","whats_new":[{"version":"1.2.0","name":"Table view","date":"2026-09-30"},{"version":"bad","name":"Skipped"}]}';
		$this->updater->request();

		$this->assertSame( [ 'Table view' ], array_column( $addon->get_new_features( '1.0.0' ), 'name' ) );

		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		$this->reply_code = 200;
		$this->reply_body = '{"success":true,"update":{"version":"1.2.0","download_link":"https://example.test/addon.zip"}}';
		$this->updater->request();

		$this->assertSame( [], $addon->get_new_features( '1.0.0' ) );
	}

	public function test_check_again_on_the_updates_screen_asks_past_the_cache() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->updater->request();
		$this->assertCount( 1, $this->requests );

		$this->reply_body = '{"success":true,"update":{"version":"3.0.0","download_link":"https://example.test/addon.zip"}}';

		// Opening the screen without "Check again" uses the cached answer.
		$this->updater->on_force_check();
		$this->assertCount( 1, $this->requests );

		$_GET['force-check'] = '1';
		$this->updater->on_force_check();

		$this->assertCount( 2, $this->requests );

		$read = $this->updater->site_transient_update_plugins_update( $this->update_data() );
		$this->assertSame( '3.0.0', $read->response[ self::ID ]->new_version );
	}

	public function test_check_again_is_ignored_for_users_who_cannot_update_plugins() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->updater->request();

		$_GET['force-check'] = '1';
		$this->updater->on_force_check();

		$this->assertCount( 1, $this->requests );
	}

	public function test_an_update_saved_by_wordpress_is_withdrawn_when_the_answer_changes() {
		// WordPress's check saved the update data with the update in it.
		$saved = $this->updater->on_pre_set_update_plugins( $this->update_data() );
		$this->assertArrayHasKey( self::ID, $saved->response );

		// Then "Check again" gets a refusal, like after the license expired.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->reply_code    = 401;
		$this->reply_body    = '{"success":false,"error_code":"invalid_license_key"}';
		$_GET['force-check'] = '1';
		$this->updater->on_force_check();

		$read = $this->updater->site_transient_update_plugins_update( $saved );

		$this->assertArrayNotHasKey( self::ID, $read->response );
		$this->assertArrayHasKey( self::ID, $read->no_update );
	}

	public function test_an_update_is_listed_once() {
		$data = $this->updater->on_pre_set_update_plugins( $this->update_data() );
		$data = $this->updater->site_transient_update_plugins_update( $data );
		$data = $this->updater->site_transient_update_plugins_update( $data );

		$this->assertCount( 1, $data->response );
		$this->assertArrayNotHasKey( self::ID, $data->no_update );
	}
}
