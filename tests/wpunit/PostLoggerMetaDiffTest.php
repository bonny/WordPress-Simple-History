<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;
use Simple_History\Event_Details\Event_Details_Container_Interface;

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

	public function tearDown(): void {
		unset( $_POST['acf'] );

		parent::tearDown();
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

	public function test_added_with_empty_string_value_is_skipped() {
		$old = $this->snapshot( [] );
		$new = $this->snapshot( [ 'seo_meta' => '' ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_added', $context, 'a key added with an empty value is not a real addition' );
	}

	public function test_added_with_all_empty_values_in_array_is_skipped() {
		$old = $this->snapshot( [] );
		$new = $this->snapshot( [ 'seo_meta' => [ '', '' ] ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_added', $context );
	}

	public function test_added_with_empty_array_value_is_skipped() {
		$old = $this->snapshot( [] );
		$new = $this->snapshot( [ 'weird_meta' => [] ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_added', $context );
	}

	public function test_added_with_non_empty_value_is_still_counted() {
		$old = $this->snapshot( [] );
		$new = $this->snapshot( [ 'seo_meta' => 'hello' ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertSame( 1, $context['post_meta_added'] ?? null );
	}

	public function test_removed_with_empty_old_value_is_skipped() {
		$old = $this->snapshot( [ 'seo_meta' => '' ] );
		$new = $this->snapshot( [] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_removed', $context, 'a key that only ever held an empty value is not a real removal' );
	}

	public function test_removed_with_non_empty_old_value_is_still_counted() {
		$old = $this->snapshot( [ 'seo_meta' => 'value' ] );
		$new = $this->snapshot( [] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertSame( 1, $context['post_meta_removed'] ?? null );
	}

	public function test_key_going_from_empty_to_non_empty_is_not_dropped() {
		// The key already existed (with an empty value), so this is the
		// "changed" bucket, not "added" — and it must not be skipped just
		// because the old value was empty.
		$old = $this->snapshot( [ 'seo_meta' => '' ] );
		$new = $this->snapshot( [ 'seo_meta' => 'now has a value' ] );

		$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );

		$this->assertArrayNotHasKey( 'post_meta_added', $context );
		$this->assertSame( 1, $context['post_meta_changed'] ?? null );
	}

	/**
	 * The `meta_keys_to_ignore` filter, used by Plugin_ACF_Logger and by
	 * site owners, is passed the raw old/new meta arrays (added in 5.34.0)
	 * so a callback can decide what to ignore based on what's actually there.
	 */
	public function test_meta_keys_to_ignore_filter_receives_old_and_new_meta() {
		$captured = [];

		$callback = function ( $keys, $context, $old_meta, $new_meta ) use ( &$captured ) {
			$captured['old_meta'] = $old_meta;
			$captured['new_meta'] = $new_meta;

			return $keys;
		};

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', $callback, 10, 4 );

		try {
			$old = $this->snapshot( [ 'kept' => 'old value' ] );
			$new = $this->snapshot( [ 'kept' => 'new value' ] );

			$this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', $callback, 10 );
		}

		$this->assertSame( [ 'old value' ], $captured['old_meta']['kept'] ?? null );
		$this->assertSame( [ 'new value' ], $captured['new_meta']['kept'] ?? null );
	}

	/**
	 * An ignore-list entry ending in "*" matches any key with that prefix,
	 * e.g. "_seopress_social_*".
	 */
	public function test_meta_keys_to_ignore_supports_prefix_wildcard() {
		$callback = function ( $keys ) {
			$keys[] = '_seopress_social_*';

			return $keys;
		};

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', $callback );

		try {
			$old = $this->snapshot( [] );
			$new = $this->snapshot(
				[
					'_seopress_social_title' => 'Some title',
					'_seopress_social_desc'  => 'Some desc',
					'kept_field'             => 'value',
				]
			);

			$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', $callback );
		}

		$this->assertSame( 1, $context['post_meta_added'] ?? null, 'only the non-wildcard-matched field should count' );
		$this->assertSame( [ 'kept_field' ], json_decode( $context['post_meta_added_keys'], true ) );
	}

	/**
	 * ACF's own noise rules (the "_acf_changed" key and its per-field
	 * "_fieldname" => "field_xxxxx" reference keys) live in Plugin_ACF_Logger,
	 * not in Post_Logger. Exercised here through the real filter callback.
	 *
	 * ACF itself is not loaded in the wpunit test suite (it's not in the
	 * plugins list in tests/wpunit.suite.yml), so this instantiates
	 * Plugin_ACF_Logger directly and hooks its callback onto the filter,
	 * rather than going through a real ACF plugin activation.
	 *
	 * This simulates an actual ACF form submission ($_POST['acf'] set) — the
	 * value key is only ignored here because Plugin_ACF_Logger's own diff
	 * will report it instead. See test_acf_value_key_is_not_ignored_outside_an_acf_form_submission()
	 * for the programmatic-update case, where it must not be ignored.
	 */
	public function test_acf_logger_ignores_its_bookkeeping_keys() {
		$_POST['acf'] = [];

		$acf_logger = new \Simple_History\Loggers\Plugin_ACF_Logger( Simple_History::get_instance() );

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10, 4 );

		try {
			$old = $this->snapshot( [] );
			$new = $this->snapshot(
				[
					'_acf_changed' => '1',
					'my_field'     => 'Some value',
					'_my_field'    => 'field_5f9a1b2c3d4e',
				]
			);

			$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10 );
		}

		// Neither the ACF reference key, its bookkeeping key, nor the field's
		// own value key counts here — ACF fields are reported by
		// Plugin_ACF_Logger's own diff, not duplicated by the core meta diff.
		$this->assertArrayNotHasKey( 'post_meta_added', $context );
	}

	/**
	 * Without $_POST['acf'] — e.g. a WP-CLI or programmatic
	 * `wp_update_post( [ 'meta_input' => [...] ] )` update — ACF's own save
	 * routine never runs, so nothing else reports the field change. The
	 * value key must stay visible in the core meta diff; only the "_"
	 * reference key and "_acf_changed" are ignored.
	 */
	public function test_acf_value_key_is_not_ignored_outside_an_acf_form_submission() {
		$acf_logger = new \Simple_History\Loggers\Plugin_ACF_Logger( Simple_History::get_instance() );

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10, 4 );

		try {
			$old = $this->snapshot( [ 'my_field' => 'Old value', '_my_field' => 'field_5f9a1b2c3d4e' ] );
			$new = $this->snapshot( [ 'my_field' => 'New value', '_my_field' => 'field_5f9a1b2c3d4e' ] );

			$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10 );
		}

		$this->assertSame( 1, $context['post_meta_changed'] ?? null );
		$this->assertSame( [ 'my_field' ], json_decode( $context['post_meta_changed_keys'] ?? '', true ) );
	}

	/**
	 * ACF field keys aren't always hex hashes — code-registered and
	 * local-JSON fields commonly use hand-written keys like
	 * "field_hero_title". The reference-key check must recognise these too,
	 * not just "field_" followed by a hex hash.
	 */
	public function test_acf_reference_key_recognises_non_hex_field_keys() {
		$_POST['acf'] = [];

		$acf_logger = new \Simple_History\Loggers\Plugin_ACF_Logger( Simple_History::get_instance() );

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10, 4 );

		try {
			$old = $this->snapshot( [] );
			$new = $this->snapshot(
				[
					'hero_title'  => 'Some value',
					'_hero_title' => 'field_hero_title',
				]
			);

			$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10 );
		}

		$this->assertArrayNotHasKey( 'post_meta_added', $context, 'a code/local-JSON ACF field key must be recognised as a reference key too' );
	}

	public function test_acf_reference_key_without_matching_field_is_not_skipped() {
		// "_orphan" looks like an ACF reference key, but "orphan" (without the
		// leading underscore) does not exist in this meta set, so it is not
		// recognised as one and is reported like any other added field.
		$acf_logger = new \Simple_History\Loggers\Plugin_ACF_Logger( Simple_History::get_instance() );

		add_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10, 4 );

		try {
			$old = $this->snapshot( [] );
			$new = $this->snapshot( [ '_orphan' => 'field_5f9a1b2c3d4e' ] );

			$context = $this->logger->add_post_data_diff_to_context( [], $old, $new );
		} finally {
			remove_filter( 'simple_history/post_logger/meta_keys_to_ignore', [ $acf_logger, 'add_acf_meta_keys_to_ignore' ], 10 );
		}

		$this->assertSame( 1, $context['post_meta_added'] ?? null );
	}

	public function test_custom_fields_only_change_produces_structured_details_group() {
		$row          = new stdClass();
		$row->logger  = 'SimplePostLogger';
		$row->context = [
			'_message_key'         => 'post_updated',
			'post_meta_added'      => 1,
			'post_meta_added_keys' => '["price"]',
		];

		$sh     = Simple_History::get_instance();
		$output = $sh->get_log_row_details_output( $row );

		$this->assertInstanceOf(
			Event_Details_Container_Interface::class,
			$output,
			'a custom-fields-only change must still produce a real Event_Details container'
		);

		$item = $this->find_item_by_name( $output->to_json(), 'Custom fields' );

		$this->assertNotNull( $item, 'Custom fields item should be present in to_json output' );
		$this->assertSame( 1, $item['added']['count'] ?? null );
		$this->assertSame( [ 'price' ], $item['added']['names'] ?? null );

		// The HTML must render too, not just the JSON.
		$html = (string) $output;
		$this->assertStringContainsString( 'Custom fields', $html );
		$this->assertStringContainsString( 'price', $html );
	}

	public function test_terms_only_change_produces_structured_details_group() {
		$row          = new stdClass();
		$row->logger  = 'SimplePostLogger';
		$row->context = [
			'_message_key'     => 'post_updated',
			'post_terms_added' => wp_json_encode(
				[
					[
						'name'     => 'News',
						'taxonomy' => 'category',
					],
				]
			),
		];

		$sh     = Simple_History::get_instance();
		$output = $sh->get_log_row_details_output( $row );

		$this->assertInstanceOf( Event_Details_Container_Interface::class, $output );

		$item = $this->find_item_by_name( $output->to_json(), 'Added term' );

		$this->assertNotNull( $item, 'Added term item should be present in to_json output' );
		$this->assertSame( [ [ 'name' => 'News', 'taxonomy' => 'category' ] ], $item['terms'] ?? null );

		$html = (string) $output;
		$this->assertStringContainsString( 'News', $html );
	}

	/**
	 * Flatten to_json() output into a single list of items and find one by name.
	 *
	 * Container-level to_json() returns groups, each group has its own
	 * 'items' array.
	 *
	 * @param array<mixed> $json Container to_json() output.
	 * @param string       $name Item name to look for.
	 * @return array<string,mixed>|null
	 */
	private function find_item_by_name( array $json, string $name ) {
		foreach ( $json as $group ) {
			foreach ( $group['items'] ?? [] as $item ) {
				if ( ( $item['name'] ?? null ) === $name ) {
					return $item;
				}
			}
		}

		return null;
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
