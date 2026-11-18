<?php

use Helper\PremiumTestCase;
use Simple_History\Simple_History;
use Simple_History\AddOns\Pro\Extended_Settings;
use Simple_History\AddOns\Pro\Modules\JSON_Feed_Module;
use Simple_History\AddOns\Pro\Modules\Post_Activity_Panel_Module;
use Simple_History\AddOns\Pro\Modules\Table_Saved_Views_Module;

/**
 * Security fixes in Premium 1.17.0 (issue 364): the JSON feed has the RSS
 * feed's guards, and the block editor activity panel and the table view
 * respect Stealth Mode.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit premium/PremiumFeedAndStealthTest
 *
 * @group premium
 */
class PremiumFeedAndStealthTest extends PremiumTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		$_GET = [];
	}

	public function tearDown(): void {
		$_GET = [];
		delete_option( 'simple_history_rss_secret' );
		delete_option( 'simple_history_enable_rss_feed' );
		parent::tearDown();
	}

	/**
	 * The live instance of a premium module.
	 *
	 * @param string $class_name Module class.
	 * @return object
	 */
	private function get_module( string $class_name ) {
		foreach ( Extended_Settings::get_instance()->get_instantiated_modules() as $module ) {
			if ( $module instanceof $class_name ) {
				return $module;
			}
		}

		$this->fail( "$class_name is not loaded." );
	}

	/**
	 * The JSON feed module, wired to the RSS dropin like on a real request.
	 *
	 * @return JSON_Feed_Module
	 */
	private function get_json_feed_module() {
		$module = $this->get_module( JSON_Feed_Module::class );

		$property = new \ReflectionProperty( JSON_Feed_Module::class, 'rss_dropin' );
		$property->setAccessible( true );
		$property->setValue( $module, Simple_History::get_instance()->get_instantiated_dropin_by_slug( 'RSS_Dropin' ) );

		return $module;
	}

	/**
	 * Run the feed check and report whether it got past every guard.
	 *
	 * The feed itself calls exit, so the last guard, the rss_feed_show filter,
	 * is used as the probe: reaching it with the filter allowing the feed
	 * throws instead of printing the log.
	 *
	 * @param string $stored_secret Secret stored in the RSS settings.
	 * @param string $given_secret  Secret in the request.
	 * @param bool   $feed_shown    What the rss_feed_show filter returns.
	 * @return bool True if the feed would have been output.
	 */
	private function feed_is_output( string $stored_secret, string $given_secret, bool $feed_shown = true ): bool {
		update_option( 'simple_history_enable_rss_feed', '1' );
		update_option( 'simple_history_rss_secret', $stored_secret );

		$_GET = [
			'simple_history_get_json' => '1',
			'json_secret'             => $given_secret,
		];

		add_filter(
			'simple_history/rss_feed_show',
			function () use ( $feed_shown ) {
				if ( $feed_shown ) {
					throw new \RuntimeException( 'feed output' );
				}

				return false;
			},
			PHP_INT_MAX
		);

		try {
			$this->get_json_feed_module()->check_for_json_feed_request();
		} catch ( \RuntimeException $e ) {
			return $e->getMessage() === 'feed output';
		}

		return false;
	}

	public function test_json_feed_is_output_with_the_right_secret(): void {
		$this->assertTrue( $this->feed_is_output( 'abc123secret', 'abc123secret' ), 'Control: the probe sees a valid request.' );
	}

	public function test_json_feed_refuses_a_wrong_secret(): void {
		$this->assertFalse( $this->feed_is_output( 'abc123secret', 'wrong' ) );
	}

	public function test_json_feed_refuses_an_empty_secret_in_the_request(): void {
		$this->assertFalse( $this->feed_is_output( 'abc123secret', '' ) );
	}

	public function test_json_feed_refuses_when_no_secret_is_stored(): void {
		$this->assertFalse( $this->feed_is_output( '', '' ), 'An empty stored secret must never match an empty request secret.' );
	}

	public function test_json_feed_respects_the_setting_that_hides_the_rss_feed(): void {
		$this->assertFalse( $this->feed_is_output( 'abc123secret', 'abc123secret', false ) );
	}

	/**
	 * Call a module's private or public permission check.
	 *
	 * @param object $module Module.
	 * @param string $method Method name.
	 * @return bool
	 */
	private function call_permission_check( $module, string $method ): bool {
		$reflection = new \ReflectionMethod( $module, $method );
		$reflection->setAccessible( true );

		return (bool) $reflection->invoke( $module );
	}

	public function test_activity_panel_and_table_view_follow_stealth_mode(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$panel = $this->get_module( Post_Activity_Panel_Module::class );
		$table = $this->get_module( Table_Saved_Views_Module::class );

		$this->assertTrue( $this->call_permission_check( $panel, 'user_can_view_history' ), 'Control: an admin sees the panel.' );
		$this->assertTrue( $this->call_permission_check( $table, 'check_permission' ), 'Control: an admin sees the table view.' );

		add_filter( 'simple_history/full_stealth_mode_enabled', '__return_true' );

		$this->assertFalse( $this->call_permission_check( $panel, 'user_can_view_history' ), 'Full stealth mode hides the activity panel.' );
		$this->assertFalse( $this->call_permission_check( $table, 'check_permission' ), 'Full stealth mode hides the table view.' );
	}
}
