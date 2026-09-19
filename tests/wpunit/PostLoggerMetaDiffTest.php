<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;

/**
 * Tests for Post_Logger::add_post_data_diff_to_context() meta diffing.
 *
 * Covers the added / changed / removed buckets for post meta. The "removed"
 * bucket was silently dropped before — this test guards against the
 * regression returning.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerMetaDiffTest
 */
class PostLoggerMetaDiffTest extends \Codeception\TestCase\WPTestCase {

	/** @var Post_Logger */
	private $logger;

	/** @var int */
	private $post_id;

	public function setUp(): void {
		parent::setUp();

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$this->post_id = $this->factory->post->create( [ 'post_title' => 'Meta diff test' ] );
	}

	private function snapshot( array $meta ): array {
		return [
			'post_data'  => get_post( $this->post_id ),
			'post_meta'  => array_map(
				static fn( $v ) => is_array( $v ) ? $v : [ $v ],
				$meta
			),
			'post_terms' => [],
		];
	}

	public function test_added_changed_and_removed_meta_are_all_counted() {
		$old = $this->snapshot(
			[
				'kept_same'   => 'unchanged',
				'will_change' => 'before',
				'will_remove' => 'goodbye',
			]
		);

		$new = $this->snapshot(
			[
				'kept_same'   => 'unchanged',
				'will_change' => 'after',
				'newly_added' => 'hello',
			]
		);

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertSame( 1, $context['post_meta_added'] ?? null, 'one key added' );
		$this->assertSame( 1, $context['post_meta_changed'] ?? null, 'one key changed' );
		$this->assertSame( 1, $context['post_meta_removed'] ?? null, 'one key removed' );
	}

	public function test_removed_meta_only() {
		$old = $this->snapshot( [ 'goes_away' => 'bye' ] );
		$new = $this->snapshot( [] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_added', $context );
		$this->assertArrayNotHasKey( 'post_meta_changed', $context );
		$this->assertSame( 1, $context['post_meta_removed'] ?? null );
	}

	public function test_no_meta_changes_sets_no_keys() {
		$snapshot = $this->snapshot( [ 'stable' => 'value' ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $snapshot, $snapshot );

		$this->assertArrayNotHasKey( 'post_meta_added', $context );
		$this->assertArrayNotHasKey( 'post_meta_changed', $context );
		$this->assertArrayNotHasKey( 'post_meta_removed', $context );
	}

	public function test_key_names_are_stored_per_bucket() {
		$old = $this->snapshot(
			[
				'kept_same'   => 'unchanged',
				'will_change' => 'before',
				'will_remove' => 'goodbye',
			]
		);

		$new = $this->snapshot(
			[
				'kept_same'   => 'unchanged',
				'will_change' => 'after',
				'newly_added' => 'hello',
			]
		);

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertSame( [ 'newly_added' ], json_decode( $context['post_meta_added_keys'], true ) );
		$this->assertSame( [ 'will_change' ], json_decode( $context['post_meta_changed_keys'], true ) );
		$this->assertSame( [ 'will_remove' ], json_decode( $context['post_meta_removed_keys'], true ) );

		// A key whose value did not change belongs to no bucket at all.
		$this->assertStringNotContainsString( 'kept_same', wp_json_encode( $context ) );
	}

	public function test_key_names_are_capped_but_the_count_is_not() {
		$new_meta = [];
		for ( $i = 1; $i <= 25; $i++ ) {
			$new_meta[ 'field_' . $i ] = 'value';
		}

		$context = $this->logger->add_post_data_diff_to_context(
			[],
			$this->snapshot( [] ),
			$this->snapshot( $new_meta )
		);

		$this->assertSame( 25, $context['post_meta_added'], 'the count is the truth' );
		$this->assertCount(
			Post_Logger::MAX_META_KEYS_IN_CONTEXT,
			json_decode( $context['post_meta_added_keys'], true ),
			'the names are a capped sample'
		);
	}

	/**
	 * Call a protected method on the logger.
	 *
	 * @param string $name Method name.
	 * @param array  $args Arguments.
	 * @return mixed
	 */
	private function call_protected( string $name, array $args ) {
		$method = new ReflectionMethod( Post_Logger::class, $name );
		$method->setAccessible( true );

		return $method->invokeArgs( $this->logger, $args );
	}

	public function test_summary_lists_names_when_they_are_stored() {
		$this->assertSame(
			'Changed: price, capacity',
			$this->call_protected( 'get_custom_fields_summary', [ 'Changed', 2, '["price","capacity"]' ] )
		);
	}

	public function test_summary_reports_fields_beyond_the_cap() {
		$this->assertSame(
			'Added: price and 3 more',
			$this->call_protected( 'get_custom_fields_summary', [ 'Added', 4, '["price"]' ] )
		);
	}

	public function test_summary_falls_back_to_the_count_for_older_events() {
		// Events logged before the names were stored have no *_keys context.
		$this->assertSame(
			'Removed: 3',
			$this->call_protected( 'get_custom_fields_summary', [ 'Removed', 3, '' ] )
		);
	}
}
