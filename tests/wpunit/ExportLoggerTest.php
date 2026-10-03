<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Loggers\Export_Logger;
use function Simple_History\tests\get_latest_context;

/**
 * Test that XML export events say what the export contained.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ExportLoggerTest
 */
class ExportLoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Export_Logger
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleExportLogger' );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Turn the latest context rows into a key => value array.
	 *
	 * @return array<string, string>
	 */
	private function get_latest_context_array() {
		$context = [];

		foreach ( get_latest_context() as $context_row ) {
			$context[ $context_row['key'] ] = $context_row['value'];
		}

		return $context;
	}

	/**
	 * Make a log row object with the given context.
	 *
	 * @param array $context Context.
	 * @return object
	 */
	private function make_row( $context ) {
		return (object) [
			'message' => 'Created XML export',
			'context' => array_merge( [ '_message_key' => 'created_export' ], $context ),
		];
	}

	/**
	 * Get the item names and values of a details group.
	 *
	 * @param Event_Details_Group $group Group.
	 * @return array<string, string>
	 */
	private function get_details( $group ) {
		$details = [];

		foreach ( $group->items as $item ) {
			$details[ $item->name ] = $item->new_value;
		}

		return $details;
	}

	public function test_export_stores_readable_filters() {
		$author_id   = $this->factory->user->create( [ 'display_name' => 'Ada Author' ] );
		$category_id = $this->factory->category->create( [ 'name' => 'News' ] );

		do_action(
			'export_wp',
			[
				'content'    => 'post',
				'author'     => $author_id,
				'category'   => $category_id,
				'start_date' => '2026-01',
				'end_date'   => '2026-03',
				'status'     => 'publish',
			]
		);

		$context = $this->get_latest_context_array();

		$this->assertSame( 'created_export', $context['_message_key'] );
		$this->assertSame( 'post', $context['export_content'] );
		$this->assertSame( 'Posts', $context['export_content_label'] );
		$this->assertSame( 'Ada Author', $context['export_author_name'] );
		$this->assertSame( 'News', $context['export_category_name'] );
		$this->assertSame( '2026-01', $context['export_start_date'] );
		$this->assertSame( '2026-03', $context['export_end_date'] );
		$this->assertSame( 'Published', $context['export_status'] );
	}

	public function test_export_of_all_content_stores_no_filters() {
		do_action(
			'export_wp',
			[
				'content'    => 'all',
				'author'     => false,
				'category'   => false,
				'start_date' => false,
				'end_date'   => false,
				'status'     => false,
			]
		);

		$context = $this->get_latest_context_array();

		$this->assertSame( 'all', $context['export_content'] );
		$this->assertArrayNotHasKey( 'export_author_name', $context );
		$this->assertArrayNotHasKey( 'export_start_date', $context );
	}

	public function test_message_for_all_content() {
		$row = $this->make_row( [ 'export_content' => 'all' ] );

		$this->assertSame( 'Created XML export of all content', $this->logger->get_log_row_plain_text_output( $row ) );
	}

	public function test_message_uses_stored_post_type_name() {
		$row = $this->make_row(
			[
				'export_content'       => 'gone_type',
				'export_content_label' => 'Recipes',
			]
		);

		$this->assertSame( 'Created XML export of Recipes', $this->logger->get_log_row_plain_text_output( $row ) );
	}

	public function test_message_for_old_event_looks_up_post_type_name() {
		$row = $this->make_row( [ 'export_content' => 'page' ] );

		$this->assertSame( 'Created XML export of Pages', $this->logger->get_log_row_plain_text_output( $row ) );
	}

	public function test_message_escapes_post_type_name() {
		$row = $this->make_row(
			[
				'export_content'       => 'evil',
				'export_content_label' => '<b>Bold</b>',
			]
		);

		$this->assertSame( 'Created XML export of &lt;b&gt;Bold&lt;/b&gt;', $this->logger->get_log_row_plain_text_output( $row ) );
	}

	public function test_message_without_content_is_unchanged() {
		$row = $this->make_row( [] );

		$this->assertSame( 'Created XML export', $this->logger->get_log_row_plain_text_output( $row ) );
	}

	public function test_details_show_stored_filters() {
		$row = $this->make_row(
			[
				'export_content'       => 'post',
				'export_author_name'   => 'Ada Author',
				'export_category_name' => 'News',
				'export_start_date'    => '2026-01',
				'export_status'        => 'Published',
			]
		);

		$details = $this->get_details( $this->logger->get_log_row_details_output( $row ) );

		$this->assertSame(
			[
				'Author'     => 'Ada Author',
				'Category'   => 'News',
				'Start date' => 'January 2026',
				'Status'     => 'Published',
			],
			$details
		);
	}

	public function test_details_for_old_event_read_export_args() {
		$author_id = $this->factory->user->create( [ 'display_name' => 'Old Author' ] );

		$row = $this->make_row(
			[
				'export_content' => 'page',
				'export_args'    => wp_json_encode(
					[
						'content'    => 'page',
						'author'     => $author_id,
						'category'   => false,
						'start_date' => false,
						'end_date'   => '2025-12',
						'status'     => 'draft',
					]
				),
			]
		);

		$details = $this->get_details( $this->logger->get_log_row_details_output( $row ) );

		$this->assertSame(
			[
				'Author'   => 'Old Author',
				'End date' => 'December 2025',
				'Status'   => 'Draft',
			],
			$details
		);
	}

	public function test_details_for_all_content_are_empty() {
		$row = $this->make_row(
			[
				'export_content' => 'all',
				'export_args'    => wp_json_encode( [ 'content' => 'all' ] ),
			]
		);

		$this->assertSame( [], $this->get_details( $this->logger->get_log_row_details_output( $row ) ) );
	}
}
