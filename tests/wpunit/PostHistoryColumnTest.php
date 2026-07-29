<?php

require_once 'functions.php';

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Services\Post_History_Column;

/**
 * Tests for the experimental "History" column on post list tables.
 *
 * Covers:
 * - The simple_history/post_history_column/enabled filter, so the feature can
 *   be turned off on its own without disabling all experimental features.
 * - Both database query strategies. The window function query (ROW_NUMBER)
 *   needs MySQL 8.0+ / MariaDB 10.2+; everything else — older MySQL/MariaDB
 *   and SQLite — uses the portable GROUP_CONCAT query. Both must return the
 *   same events, and the portable one is exercised here on whatever database
 *   the test suite runs against.
 *
 * The portable query's SQLite dialect (no ORDER BY inside GROUP_CONCAT, sorted
 * in PHP instead) can't be reached from this suite, which always runs on
 * MySQL/MariaDB. It is covered by the standalone SQLite harness noted in the
 * pull request instead.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PostHistoryColumnTest
 *
 * Run against MySQL 5.7, where the window function query is a syntax error:
 *   DB_IMAGE=biarms/mysql:5.7 DB_DATA_DIR=./data/mysql-5.7 npm run test:wpunit
 */
class PostHistoryColumnTest extends \Codeception\TestCase\WPTestCase {

	/** @var \Simple_History\Loggers\Post_Logger */
	private $logger;

	/** @var int */
	private $user_id;

	public function setUp(): void {
		parent::setUp();

		$simple_history = Simple_History::get_instance();
		$this->logger   = $simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$this->user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->user_id );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/post_history_column/enabled' );
		remove_all_filters( 'simple_history/experimental_features_enabled' );
		remove_all_filters( 'simple_history/db_supports_window_functions' );
		delete_option( 'simple_history_experimental_features_enabled' );

		parent::tearDown();
	}

	public function test_feature_is_disabled_by_default() {
		$this->assertFalse( Post_History_Column::is_feature_enabled() );
	}

	public function test_feature_is_enabled_with_experimental_features() {
		update_option( 'simple_history_experimental_features_enabled', 1 );

		$this->assertTrue( Post_History_Column::is_feature_enabled() );
	}

	public function test_filter_can_disable_feature_while_experimental_features_stay_on() {
		update_option( 'simple_history_experimental_features_enabled', 1 );
		add_filter( 'simple_history/post_history_column/enabled', '__return_false' );

		$this->assertFalse( Post_History_Column::is_feature_enabled() );
		$this->assertTrue(
			Helpers::experimental_features_is_enabled(),
			'Other experimental features should be unaffected.'
		);
	}

	public function test_filter_can_enable_feature_without_experimental_features() {
		add_filter( 'simple_history/post_history_column/enabled', '__return_true' );

		$this->assertTrue( Post_History_Column::is_feature_enabled() );
	}

	public function test_window_function_support_is_detected_for_current_database() {
		global $wpdb;

		$expected = version_compare( $this->current_db_version(), $this->minimum_db_version(), '>=' );

		$this->assertSame(
			$expected,
			Helpers::db_supports_window_functions(),
			sprintf( 'Unexpected detection for server "%s".', $wpdb->db_server_info() )
		);
	}

	public function test_window_function_detection_can_be_overridden_by_filter() {
		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );
		$this->assertFalse( Helpers::db_supports_window_functions() );

		remove_all_filters( 'simple_history/db_supports_window_functions' );

		add_filter( 'simple_history/db_supports_window_functions', '__return_true' );
		$this->assertTrue( Helpers::db_supports_window_functions() );
	}

	/**
	 * The fallback query must return the two most recent events per post,
	 * newest first, on a database without window functions.
	 */
	public function test_fallback_query_returns_two_most_recent_events_per_post() {
		$first_post_id  = $this->factory->post->create( array( 'post_title' => 'First post' ) );
		$second_post_id = $this->factory->post->create( array( 'post_title' => 'Second post' ) );

		$this->log_event( $first_post_id, 'post_updated' );
		$this->log_event( $first_post_id, 'post_trashed' );
		$this->log_event( $first_post_id, 'post_restored' );
		$this->log_event( $second_post_id, 'post_updated' );

		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );

		$history_data = $this->load_history_data( array( $first_post_id, $second_post_id ) );

		$this->assertCount( 2, $history_data[ $first_post_id ], 'Only the 2 most recent events are loaded.' );
		$this->assertSame(
			array( 'post_restored', 'post_trashed' ),
			wp_list_pluck( $history_data[ $first_post_id ], 'message_key' ),
			'Events are ordered newest first.'
		);

		$this->assertCount( 1, $history_data[ $second_post_id ], 'A post with a single event returns just that event.' );
		$this->assertSame( 'post_updated', $history_data[ $second_post_id ][0]['message_key'] );
		$this->assertSame( (string) $this->user_id, $history_data[ $second_post_id ][0]['user_id'] );
	}

	public function test_fallback_query_ignores_posts_without_events() {
		$post_with_events_id = $this->factory->post->create();
		$post_without_id     = $this->factory->post->create();

		$this->log_event( $post_with_events_id, 'post_updated' );

		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );

		$history_data = $this->load_history_data( array( $post_with_events_id, $post_without_id ) );

		$this->assertArrayHasKey( $post_with_events_id, $history_data );
		$this->assertArrayNotHasKey( $post_without_id, $history_data );
	}

	public function test_fallback_query_handles_posts_with_no_events_at_all() {
		$post_id = $this->factory->post->create();

		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );

		$this->assertSame( array(), $this->load_history_data( array( $post_id ) ) );
	}

	/**
	 * The purge cron deletes events and contexts in two separate statements,
	 * so an interrupted purge can leave a context whose event row is gone.
	 * Such a context must not take up one of the two ranked slots and hide a
	 * still-present older event.
	 */
	public function test_fallback_query_skips_contexts_whose_event_is_gone() {
		global $wpdb;

		$post_id = $this->factory->post->create();

		$this->log_event( $post_id, 'post_updated' );
		$this->log_event( $post_id, 'post_trashed' );
		$this->log_event( $post_id, 'post_restored' );

		// Orphan the newest event by removing only its events table row,
		// exactly what an interrupted purge leaves behind.
		$newest_event_id = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT MAX(id) FROM %i', Simple_History::$dbtable )
		);
		$wpdb->delete( Simple_History::$dbtable, array( 'id' => $newest_event_id ), array( '%d' ) );

		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );

		$history_data = $this->load_history_data( array( $post_id ) );

		$this->assertSame(
			array( 'post_trashed', 'post_updated' ),
			wp_list_pluck( $history_data[ $post_id ], 'message_key' ),
			'The orphaned context is skipped and the two surviving events are returned.'
		);
	}

	/**
	 * The fallback exists only to work around missing window functions, so it
	 * must agree with the window function query wherever both can run.
	 */
	public function test_fallback_query_matches_window_function_query() {
		if ( ! Helpers::db_supports_window_functions() ) {
			$this->markTestSkipped( 'Database does not support window functions, nothing to compare against.' );
		}

		$post_ids = array(
			$this->factory->post->create(),
			$this->factory->post->create(),
			$this->factory->post->create(),
		);

		foreach ( $post_ids as $index => $post_id ) {
			for ( $i = 0; $i <= $index; $i++ ) {
				$this->log_event( $post_id, 'post_updated' );
			}
		}

		$with_window_functions = $this->load_history_data( $post_ids );

		add_filter( 'simple_history/db_supports_window_functions', '__return_false' );

		$without_window_functions = $this->load_history_data( $post_ids );

		$this->assertEquals( $with_window_functions, $without_window_functions );
	}

	/**
	 * Log an event for a post the way Post_Logger does, so the row carries the
	 * post_id, _message_key and _user_id contexts the column query reads.
	 *
	 * @param int    $post_id     Post to log for.
	 * @param string $message_key Logger message key.
	 */
	private function log_event( $post_id, $message_key ) {
		$this->logger->info_message(
			$message_key,
			array(
				'post_id'   => $post_id,
				'post_type' => get_post_type( $post_id ),
			)
		);
	}

	/**
	 * Run the column service's batch query for the given posts and return the
	 * loaded history data.
	 *
	 * The service reads the posts from $wp_query and keeps the result in a
	 * private property, so both are handled here.
	 *
	 * @param array $post_ids Posts to load history for.
	 * @return array History data keyed by post id.
	 */
	private function load_history_data( $post_ids ) {
		global $wp_query;

		$previous_posts  = $wp_query->posts;
		$wp_query->posts = array_map( 'get_post', $post_ids );

		$service = new Post_History_Column( Simple_History::get_instance() );

		$load_history_data = new ReflectionMethod( $service, 'load_history_data' );
		$load_history_data->setAccessible( true );
		$load_history_data->invoke( $service );

		$history_data = new ReflectionProperty( $service, 'history_data' );
		$history_data->setAccessible( true );
		$loaded = $history_data->getValue( $service );

		$wp_query->posts = $previous_posts;

		return $loaded;
	}

	/**
	 * Version number of the database server the tests run against.
	 *
	 * @return string
	 */
	private function current_db_version() {
		global $wpdb;

		$server_info = preg_replace( '/^5\.5\.5-/', '', $wpdb->db_server_info() );
		preg_match( '/^[0-9]+(\.[0-9]+)*/', $server_info, $matches );

		return $matches[0] ?? '0';
	}

	/**
	 * Lowest server version with window function support, which differs
	 * between MySQL and MariaDB.
	 *
	 * @return string
	 */
	private function minimum_db_version() {
		global $wpdb;

		return stripos( $wpdb->db_server_info(), 'mariadb' ) !== false ? '10.2' : '8.0';
	}
}
