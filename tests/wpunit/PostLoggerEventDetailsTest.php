<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;
use Simple_History\Event_Details\Event_Details_Container_Interface;

/**
 * Verifies that simple value-pair fields on post_updated events render as
 * structured Event_Details_Items so REST API, "Copy as JSON", and "Copy as
 * Markdown" consumers get structured data instead of HTML.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerEventDetailsTest
 */
class PostLoggerEventDetailsTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Post_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimplePostLogger' );
	}

	/**
	 * Once routed through Simple_History::get_log_row_details_output(), which is
	 * what the REST controller calls, the result must implement the container
	 * interface so to_json() returns structured data — not the empty array
	 * Event_Details_Simple_Container produces for raw HTML strings.
	 */
	public function test_post_updated_returns_event_details_container_not_string() {
		$row = $this->make_row( array(
			'_message_key'         => 'post_updated',
			'post_prev_post_status' => 'draft',
			'post_new_post_status'  => 'publish',
		) );

		$output = $this->sh->get_log_row_details_output( $row );

		$this->assertInstanceOf(
			Event_Details_Container_Interface::class,
			$output,
			'post_updated must be backed by a real Event_Details container so to_json() is non-empty'
		);
		$this->assertNotSame(
			array(),
			$output->to_json(),
			'to_json() should be populated with the migrated structured items'
		);
	}

	public function test_post_status_change_renders_in_to_json() {
		$row = $this->make_row( array(
			'_message_key'          => 'post_updated',
			'post_prev_post_status' => 'draft',
			'post_new_post_status'  => 'publish',
		) );

		$json = $this->to_json_items( $row );

		$this->assertItemExists(
			$json,
			array(
				'name'       => 'Status',
				'new_value'  => 'publish',
				'prev_value' => 'draft',
			)
		);
	}

	public function test_post_date_change_renders_in_to_json() {
		$row = $this->make_row( array(
			'_message_key'        => 'post_updated',
			'post_prev_post_date' => '2026-01-01 10:00:00',
			'post_new_post_date'  => '2026-02-15 14:30:00',
		) );

		$json = $this->to_json_items( $row );

		$this->assertItemExists(
			$json,
			array(
				'name'       => 'Publish date',
				'new_value'  => '2026-02-15 14:30:00',
				'prev_value' => '2026-01-01 10:00:00',
			)
		);
	}

	public function test_comment_status_change_renders_in_to_json() {
		$row = $this->make_row( array(
			'_message_key'            => 'post_updated',
			'post_prev_comment_status' => 'open',
			'post_new_comment_status'  => 'closed',
		) );

		$json = $this->to_json_items( $row );

		$this->assertItemExists(
			$json,
			array(
				'name'       => 'Comment status',
				'new_value'  => 'closed',
				'prev_value' => 'open',
			)
		);
	}

	public function test_post_author_change_includes_name_and_email_in_to_json() {
		$row = $this->make_row( array(
			'_message_key'                          => 'post_updated',
			'post_prev_post_author'                 => '1',
			'post_new_post_author'                  => '2',
			'post_prev_post_author/display_name'    => 'Alice',
			'post_prev_post_author/user_email'      => 'alice@example.com',
			'post_new_post_author/display_name'     => 'Bob',
			'post_new_post_author/user_email'       => 'bob@example.com',
		) );

		$json = $this->to_json_items( $row );

		$item = $this->find_item_by_name( $json, 'Author' );

		$this->assertNotNull( $item, 'Author item should be present in to_json output' );
		$this->assertStringContainsString( 'Alice', $item['prev_value'] ?? '' );
		$this->assertStringContainsString( 'alice@example.com', $item['prev_value'] ?? '' );
		$this->assertStringContainsString( 'Bob', $item['new_value'] ?? '' );
		$this->assertStringContainsString( 'bob@example.com', $item['new_value'] ?? '' );
	}

	public function test_page_template_change_includes_name_and_filename_in_to_json() {
		$row = $this->make_row( array(
			'_message_key'                  => 'post_updated',
			'post_prev_page_template'       => 'default',
			'post_new_page_template'        => 'templates/full-width.php',
			'post_prev_page_template_name'  => 'Default',
			'post_new_page_template_name'   => 'Full Width',
		) );

		$json = $this->to_json_items( $row );

		$item = $this->find_item_by_name( $json, 'Template' );

		$this->assertNotNull( $item, 'Template item should be present in to_json output' );
		$this->assertStringContainsString( 'Full Width', $item['new_value'] ?? '' );
		$this->assertStringContainsString( 'templates/full-width.php', $item['new_value'] ?? '' );
		$this->assertStringContainsString( 'Default', $item['prev_value'] ?? '' );
	}

	/**
	 * Title text_diff renders inside the legacy SimpleHistoryLogitem__keyValueTable.
	 * Word-level visual diffs aren't a clean value-pair fit for Event_Details_Items,
	 * so they stay in the HTML table.
	 */
	public function test_legacy_table_still_present_in_to_html_for_title_diff() {
		$row = $this->make_row( array(
			'_message_key'         => 'post_updated',
			'post_prev_post_title' => 'Old title',
			'post_new_post_title'  => 'New title',
		) );

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable', $html );
		// helpers::text_diff splits the changed words from the unchanged tail,
		// so we look for the diff markers + a fragment rather than the raw strings.
		$this->assertStringContainsString( '<del>Old</del>', $html );
		$this->assertStringContainsString( '<ins>New</ins>', $html );
	}

	/**
	 * Back-compat guard: the diff_table_output filter must keep firing with
	 * an HTML string as the first arg. The in-tree ACF logger and any
	 * external plugin hooked there depends on this contract.
	 */
	public function test_diff_table_output_filter_still_receives_string() {
		$captured = array( 'fired' => false, 'first_arg_type' => null );

		$callback = function ( $diff_html, $context ) use ( &$captured ) {
			$captured['fired']          = true;
			$captured['first_arg_type'] = gettype( $diff_html );
			return $diff_html;
		};

		add_filter( 'simple_history/post_logger/post_updated/diff_table_output', $callback, 10, 2 );

		try {
			$row = $this->make_row( array(
				'_message_key'         => 'post_updated',
				'post_prev_post_title' => 'Old',
				'post_new_post_title'  => 'New',
			) );

			(string) $this->sh->get_log_row_details_output( $row );
		} finally {
			remove_filter( 'simple_history/post_logger/post_updated/diff_table_output', $callback, 10 );
		}

		$this->assertTrue( $captured['fired'], 'diff_table_output filter should still fire' );
		$this->assertSame( 'string', $captured['first_arg_type'], 'diff_table_output filter must still receive a string as first arg' );
	}

	public function test_post_updated_details_are_a_definition_list() {
		$row = $this->make_row( array(
			'_message_key'         => 'post_updated',
			'post_prev_post_title' => 'Old',
			'post_new_post_title'  => 'New',
		) );

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringContainsString( '<dl class="SimpleHistoryLogitem__keyValueTable">', $html );
		$this->assertStringContainsString( '<dt>Title</dt>', $html );
		$this->assertStringContainsString( '<dd>', $html );
		$this->assertStringNotContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html );
	}

	/**
	 * A callback written against the pre-5.33 contract appends <tr> rows.
	 * Browsers drop tr/td inside a <dl>, so the whole list must fall back to a
	 * table, with our own pairs translated so nothing is lost.
	 */
	public function test_legacy_table_rows_from_filter_render_as_table() {
		$callback = function ( $diff_html, $context ) {
			return $diff_html . '<tr><td>SEO title</td><td>New title</td></tr>';
		};

		add_filter( 'simple_history/post_logger/post_updated/diff_table_output', $callback, 10, 2 );

		try {
			$row = $this->make_row( array(
				'_message_key'         => 'post_updated',
				'post_prev_post_title' => 'Old',
				'post_new_post_title'  => 'New',
			) );

			$html = (string) $this->sh->get_log_row_details_output( $row );
		} finally {
			remove_filter( 'simple_history/post_logger/post_updated/diff_table_output', $callback, 10 );
		}

		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html );
		$this->assertStringContainsString( '<tr><td>Title</td>', $html, 'Our own pair is converted to a table row' );
		$this->assertStringContainsString( '<tr><td>SEO title</td><td>New title</td></tr>', $html, 'The appended legacy row is kept as is' );
		$this->assertStringNotContainsString( '<dt>', $html );
		$this->assertStringNotContainsString( '<dl', $html );
		$this->assertSame( 2, substr_count( $html, '<tr><td>' ), 'Exactly two key-value rows' );
	}

	/**
	 * Build a mock row object suitable for get_log_row_details_output().
	 *
	 * @param array $context Context keys.
	 * @return object
	 */
	private function make_row( array $context ): object {
		$row          = new stdClass();
		$row->logger  = 'SimplePostLogger';
		$row->context = $context;
		return $row;
	}

	/**
	 * Flatten to_json() output into a single list of items.
	 * Container-level to_json() returns groups, each group has its own
	 * 'items' array — flatten them so tests can assert by item name.
	 *
	 * Routes through Simple_History::get_log_row_details_output() so a
	 * returned Event_Details_Group gets wrapped in Event_Details_Container
	 * with context applied — same path the REST controller takes.
	 *
	 * @param object $row Mock row.
	 * @return array<int,array<string,mixed>>
	 */
	private function to_json_items( $row ): array {
		$output = $this->sh->get_log_row_details_output( $row );

		$json = method_exists( $output, 'to_json' ) ? $output->to_json() : array();

		$flat = array();

		foreach ( (array) $json as $entry ) {
			if ( is_array( $entry ) && isset( $entry['items'] ) && is_array( $entry['items'] ) ) {
				foreach ( $entry['items'] as $item ) {
					$flat[] = $item;
				}
			}
		}

		return $flat;
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 * @param string                          $name
	 * @return array<string,mixed>|null
	 */
	private function find_item_by_name( array $items, string $name ) {
		foreach ( $items as $item ) {
			if ( isset( $item['name'] ) && $item['name'] === $name ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * Assert that an item with the given name/new_value/prev_value exists
	 * in the flattened to_json output.
	 *
	 * @param array<int,array<string,mixed>> $items    Flattened items.
	 * @param array<string,string>           $expected Subset to match.
	 */
	private function assertItemExists( array $items, array $expected ): void {
		$item = $this->find_item_by_name( $items, $expected['name'] );

		$this->assertNotNull(
			$item,
			sprintf( 'Item "%s" should be present in to_json output. Got: %s', $expected['name'], wp_json_encode( $items ) )
		);

		foreach ( $expected as $key => $value ) {
			$this->assertSame(
				$value,
				$item[ $key ] ?? null,
				sprintf( 'Item "%s" key "%s" should equal "%s", got "%s"', $expected['name'], $key, $value, $item[ $key ] ?? '' )
			);
		}
	}
}
