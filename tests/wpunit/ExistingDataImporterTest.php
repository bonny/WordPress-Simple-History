<?php
/**
 * Tests for the Existing_Data_Importer class.
 *
 * @package Simple_History
 */

// phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.Files.FileName.NotHyphenatedLowercase

use Simple_History\Existing_Data_Importer;
use Simple_History\Simple_History;

/**
 * Tests for the Existing_Data_Importer class.
 *
 * Tests the backfill functionality that imports historical data
 * from WordPress into Simple History.
 */
class ExistingDataImporterTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Importer instance.
	 *
	 * @var Existing_Data_Importer
	 */
	private $importer;

	/**
	 * Admin user ID for tests.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->importer = new Existing_Data_Importer( Simple_History::get_instance() );

		// Create and set admin user for tests.
		$this->admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Test that importing posts creates events with backfilled context.
	 */
	public function test_import_posts_creates_events() {
		// Create test posts.
		$post_id_1 = $this->factory->post->create(
			array(
				'post_title'  => 'Test Post 1',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$post_id_2 = $this->factory->post->create(
			array(
				'post_title'  => 'Test Post 2',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Run import.
		$results = $this->importer->import_all(
			array(
				'post_types'   => array( 'post' ),
				'import_users' => false,
				'limit'        => 100,
				'days_back'    => null, // No date filter.
			)
		);

		// Assert posts were imported.
		$this->assertGreaterThanOrEqual( 2, $results['posts_imported'], 'Should import at least 2 posts' );
		$this->assertGreaterThanOrEqual( 2, $results['post_events_created'], 'Should create at least 2 post events' );

		// Verify backfilled events exist in the database.
		$backfilled_count = $this->importer->get_backfilled_events_count();
		$this->assertGreaterThanOrEqual( 2, $backfilled_count, 'Should have at least 2 backfilled events' );
	}

	/**
	 * Test that importing skips posts that are already logged.
	 */
	public function test_import_posts_skips_already_logged() {
		// Create a post.
		$post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Already Logged Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Manually log this post (simulating normal logging).
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimplePostLogger' );
		$logger->info_message(
			'post_created',
			array(
				'post_id'    => $post_id,
				'post_type'  => 'post',
				'post_title' => 'Already Logged Post',
			)
		);

		// Run import.
		$results = $this->importer->import_all(
			array(
				'post_types'   => array( 'post' ),
				'import_users' => false,
				'limit'        => 100,
				'days_back'    => null,
			)
		);

		// The already logged post should be skipped.
		$this->assertGreaterThanOrEqual( 1, $results['posts_skipped_logged'], 'Should skip at least 1 already logged post' );
	}

	/**
	 * Test that importing users creates events with backfilled context.
	 */
	public function test_import_users_creates_events() {
		global $wpdb;

		// Create a user directly in the database to avoid triggering Simple History hooks.
		// This simulates a user that existed before Simple History was installed.
		$user_login = 'backfilltest_' . wp_generate_password( 8, false );
		$user_email = $user_login . '@example.com';
		$user_pass  = wp_hash_password( 'testpass123' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->users,
			array(
				'user_login'          => $user_login,
				'user_pass'           => $user_pass,
				'user_nicename'       => $user_login, // Required for cache key.
				'user_email'          => $user_email,
				'user_url'            => '',
				'user_registered'     => current_time( 'mysql', true ),
				'user_activation_key' => '',
				'user_status'         => 0,
				'display_name'        => $user_login,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		$direct_user_id = $wpdb->insert_id;

		// Run import with users.
		$results = $this->importer->import_all(
			array(
				'post_types'   => array(),
				'import_users' => true,
				'limit'        => 100,
				'days_back'    => null,
			)
		);

		// Assert at least one user was imported (the one we inserted directly).
		$this->assertGreaterThanOrEqual( 1, $results['users_imported'], 'Should import at least 1 user' );
		$this->assertGreaterThanOrEqual( 1, $results['user_events_created'], 'Should create at least 1 user event' );

		// Verify the user we created has a backfilled event.
		$contexts_table = Simple_History::get_instance()->get_contexts_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_events = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$contexts_table} c1
				INNER JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id
				WHERE c1.key = 'created_user_id' AND c1.value = %s
				AND c2.key = %s",
				$direct_user_id,
				Existing_Data_Importer::BACKFILLED_CONTEXT_KEY
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( 1, (int) $user_events, 'User should have a backfilled event' );

		// Clean up: delete the test user.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->users, array( 'ID' => $direct_user_id ), array( '%d' ) );
	}

	/**
	 * Test that delete_all_imported removes backfilled events.
	 */
	public function test_delete_all_imported_removes_backfilled_events() {
		// Create and import posts.
		$this->factory->post->create(
			array(
				'post_title'  => 'Post to Delete',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		$this->importer->import_all(
			array(
				'post_types'   => array( 'post' ),
				'import_users' => false,
				'limit'        => 100,
				'days_back'    => null,
			)
		);

		// Verify backfilled events exist.
		$count_before = $this->importer->get_backfilled_events_count();
		$this->assertGreaterThan( 0, $count_before, 'Should have backfilled events before delete' );

		// Delete all imported events.
		$delete_results = $this->importer->delete_all_imported();

		// Verify delete succeeded.
		$this->assertTrue( $delete_results['success'], 'Delete should succeed' );
		$this->assertGreaterThan( 0, $delete_results['events_deleted'], 'Should have deleted some events' );

		// Verify no backfilled events remain.
		$count_after = $this->importer->get_backfilled_events_count();
		$this->assertEquals( 0, $count_after, 'Should have no backfilled events after delete' );
	}

	/**
	 * Test that import respects days_back filter.
	 */
	public function test_import_respects_days_back_filter() {
		global $wpdb;

		// Create a recent post (should be imported).
		$recent_post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Recent Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Create an old post by directly updating the database.
		$old_post_id = $this->factory->post->create(
			array(
				'post_title'  => 'Old Post',
				'post_status' => 'publish',
				'post_type'   => 'post',
			)
		);

		// Set the old post's date to 100 days ago.
		$old_date     = gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) );
		$old_date_gmt = $old_date;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'         => $old_date,
				'post_date_gmt'     => $old_date_gmt,
				'post_modified'     => $old_date,
				'post_modified_gmt' => $old_date_gmt,
			),
			array( 'ID' => $old_post_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Clear any caches.
		clean_post_cache( $old_post_id );

		// Run import with 30 days filter (should skip the 100-day-old post).
		$results = $this->importer->import_all(
			array(
				'post_types'   => array( 'post' ),
				'import_users' => false,
				'limit'        => 100,
				'days_back'    => 30,
			)
		);

		// The recent post should be imported, the old post should not.
		// We check that at least 1 post was imported (the recent one).
		$this->assertGreaterThanOrEqual( 1, $results['posts_imported'], 'Should import at least 1 recent post' );

		// Verify the old post was NOT imported by checking if it has a backfilled event.
		$contexts_table = Simple_History::get_instance()->get_contexts_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old_post_events = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$contexts_table} c1
				INNER JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id
				WHERE c1.key = 'post_id' AND c1.value = %s
				AND c2.key = %s",
				$old_post_id,
				Existing_Data_Importer::BACKFILLED_CONTEXT_KEY
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$this->assertEquals( 0, (int) $old_post_events, 'Old post should NOT have been backfilled' );
	}
}
