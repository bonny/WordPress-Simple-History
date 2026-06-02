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
}
