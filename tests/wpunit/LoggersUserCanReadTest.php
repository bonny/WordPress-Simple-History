<?php

use Simple_History\Simple_History;
use Simple_History\Loggers\SimpleUserLogger;
use Simple_History\Loggers\SimplePostLogger;
use Simple_History\Loggers\SimpleMediaLogger;

/**
 * Test the get_loggers_that_user_can_read() method and logger permission functionality.
 *
 * @coversDefaultClass Simple_History\Simple_History
 */
class LoggersUserCanReadTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Simple History instance
	 *
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Editor user ID
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Subscriber user ID
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sh = Simple_History::get_instance();

		// Create users with different roles
		$this->admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->editor_user_id = $this->factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		$this->subscriber_user_id = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}

	/**
	 * Test that get_loggers_that_user_can_read returns array by default
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_get_loggers_returns_array_by_default() {
		wp_set_current_user( $this->admin_user_id );
		$loggers = $this->sh->get_loggers_that_user_can_read();
		$this->assertIsArray( $loggers );
		$this->assertNotEmpty( $loggers );
	}

	/**
	 * Test that get_loggers_that_user_can_read returns SQL string when format='sql'
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_get_loggers_returns_sql_string() {
		wp_set_current_user( $this->admin_user_id );
		$sql_string = $this->sh->get_loggers_that_user_can_read( null, 'sql' );
		$this->assertIsString( $sql_string );
		$this->assertStringStartsWith( '(', $sql_string );
		$this->assertStringEndsWith( ')', $sql_string );
	}

	/**
	 * Test that get_loggers_that_user_can_read returns array of slugs when format='slugs'
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_get_loggers_returns_slugs_array() {
		wp_set_current_user( $this->admin_user_id );
		$slugs = $this->sh->get_loggers_that_user_can_read( null, 'slugs' );
		$this->assertIsArray( $slugs );
		$this->assertNotEmpty( $slugs );
		// Check that all items are strings (slugs)
		foreach ( $slugs as $slug ) {
			$this->assertIsString( $slug );
		}
	}

	/**
	 * Test that admin users can read all loggers
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_admin_can_read_all_loggers() {
		wp_set_current_user( $this->admin_user_id );
		$loggers = $this->sh->get_loggers_that_user_can_read();
		$all_loggers = $this->sh->get_instantiated_loggers();

		// Admin should be able to read most loggers (some might have special permissions)
		$this->assertGreaterThan( 10, count( $loggers ) );
	}

	/**
	 * Test that non-admin users have limited logger access
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_editor_has_limited_logger_access() {
		// Set current user to editor
		wp_set_current_user( $this->editor_user_id );
		$editor_loggers = $this->sh->get_loggers_that_user_can_read( $this->editor_user_id, 'slugs' );

		// Set current user to admin
		wp_set_current_user( $this->admin_user_id );
		$admin_loggers = $this->sh->get_loggers_that_user_can_read( $this->admin_user_id, 'slugs' );

		// Editor should have access to fewer loggers than admin
		$this->assertLessThan( count( $admin_loggers ), count( $editor_loggers ) );

		// Editor should typically have access to post/page/media loggers
		$this->assertContains( 'SimplePostLogger', $editor_loggers );
		$this->assertContains( 'SimpleMediaLogger', $editor_loggers );
	}

	/**
	 * Test that subscriber users have very limited logger access
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_subscriber_has_minimal_logger_access() {
		wp_set_current_user( $this->subscriber_user_id );
		$subscriber_loggers = $this->sh->get_loggers_that_user_can_read( $this->subscriber_user_id, 'slugs' );

		wp_set_current_user( $this->editor_user_id );
		$editor_loggers = $this->sh->get_loggers_that_user_can_read( $this->editor_user_id, 'slugs' );

		// Subscriber should have access to fewer loggers than editor
		$this->assertLessThanOrEqual( count( $editor_loggers ), count( $subscriber_loggers ) );
	}

	/**
	 * Test that loggers are returned in consistent sorted order
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_loggers_are_sorted_consistently() {
		wp_set_current_user( $this->admin_user_id );

		// Get loggers multiple times
		$loggers1 = $this->sh->get_loggers_that_user_can_read( null, 'slugs' );
		$loggers2 = $this->sh->get_loggers_that_user_can_read( null, 'slugs' );
		$loggers3 = $this->sh->get_loggers_that_user_can_read( null, 'slugs' );

		// They should be identical (same order)
		$this->assertEquals( $loggers1, $loggers2 );
		$this->assertEquals( $loggers2, $loggers3 );

		// Check they are actually sorted alphabetically
		$sorted_loggers = $loggers1;
		sort( $sorted_loggers );
		$this->assertEquals( $sorted_loggers, $loggers1 );
	}

	/**
	 * Test that specifying a user_id parameter works correctly
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_specific_user_id_parameter() {
		// Set current user to subscriber
		wp_set_current_user( $this->subscriber_user_id );

		// But query for admin's loggers
		$admin_loggers_via_param = $this->sh->get_loggers_that_user_can_read( $this->admin_user_id, 'slugs' );

		// Now set current user to admin and get their loggers
		wp_set_current_user( $this->admin_user_id );
		$admin_loggers_direct = $this->sh->get_loggers_that_user_can_read( null, 'slugs' );

		// They should be the same
		$this->assertEquals( $admin_loggers_via_param, $admin_loggers_direct );
	}

	/**
	 * Test that SQL format doesn't return empty parentheses when user can't read any loggers
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_sql_format_with_no_readable_loggers() {
		// Create a user with no capabilities
		$no_cap_user_id = $this->factory->user->create(
			array(
				'role' => '', // No role means no capabilities
			)
		);

		wp_set_current_user( $no_cap_user_id );
		$sql_string = $this->sh->get_loggers_that_user_can_read( null, 'sql' );

		// Should return (NULL) when user can't read any loggers
		$this->assertEquals( '(NULL)', $sql_string );
	}

	/**
	 * Test that the same users with same permissions get same cache keys
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_cache_keys_are_consistent_for_same_permissions() {
		// Create two editors
		$editor1_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$editor2_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Get loggers for both editors
		$editor1_loggers = $this->sh->get_loggers_that_user_can_read( $editor1_id, 'slugs' );
		$editor2_loggers = $this->sh->get_loggers_that_user_can_read( $editor2_id, 'slugs' );

		// They should have the same loggers since they have the same role
		$this->assertEquals( $editor1_loggers, $editor2_loggers );

		// Create cache keys like our implementation does
		$cache_key1 = md5( implode( ',', $editor1_loggers ) );
		$cache_key2 = md5( implode( ',', $editor2_loggers ) );

		// Cache keys should be identical
		$this->assertEquals( $cache_key1, $cache_key2 );
	}

	/**
	 * Test that different roles get different cache keys
	 *
	 * @covers ::get_loggers_that_user_can_read
	 */
	public function test_cache_keys_differ_for_different_permissions() {
		// Get loggers for admin and editor
		$admin_loggers = $this->sh->get_loggers_that_user_can_read( $this->admin_user_id, 'slugs' );
		$editor_loggers = $this->sh->get_loggers_that_user_can_read( $this->editor_user_id, 'slugs' );

		// Create cache keys like our implementation does
		$admin_cache_key = md5( implode( ',', $admin_loggers ) );
		$editor_cache_key = md5( implode( ',', $editor_loggers ) );

		// Cache keys should be different
		$this->assertNotEquals( $admin_cache_key, $editor_cache_key );
	}
}