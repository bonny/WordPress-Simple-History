<?php

use Simple_History\Channels\Channel_Event_Details;
use Simple_History\Simple_History;

/**
 * Issue 375: post events in the log file, forwarding and alerts.
 *
 * The events are real: posts are created and updated through
 * wp_insert_post() and wp_update_post(), and the context the logger passes
 * to the inserted hook is what the tests read. That keeps the content diff
 * format (plain text diff or compact JSON diff) the same as on a real site.
 *
 * The GUI tests pin how the event details look in the log today, so moving
 * the post logger to structured details can't change what users see.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerChannelDetailsTest
 */
class PostLoggerChannelDetailsTest extends \Codeception\TestCase\WPTestCase {
	/** @var array<int, array{context: array, data: array}> Events captured from the inserted hook. */
	private $inserted = [];

	public function setUp(): void {
		parent::setUp();

		$this->inserted = [];

		add_action( 'simple_history/log/inserted', [ $this, 'capture_inserted' ], 10, 2 );

		// The post logger logs changes made outside wp-admin only for REST and WP-CLI.
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function tearDown(): void {
		remove_action( 'simple_history/log/inserted', [ $this, 'capture_inserted' ], 10 );
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/post_logger/label_for_key' );
		parent::tearDown();
	}

	/**
	 * @param array $context Event context.
	 * @param array $data    Event row data.
	 */
	public function capture_inserted( $context, $data ) {
		$this->inserted[] = [
			'context' => $context,
			'data'    => $data,
		];
	}

	/*
	 * GUI: how the details look in the log today. These must keep passing.
	 */

	public function test_gui_lists_changed_fields_in_order() {
		$event = $this->update_post_with_many_changes();

		$this->assertSame(
			[ 'Status', 'Comment status', 'Title', 'Permalink', 'Menu order', 'Excerpt', 'Content', 'Custom fields' ],
			$this->get_gui_labels( $event )
		);
	}

	public function test_gui_shows_word_diffs_for_title_permalink_and_excerpt() {
		$html = $this->get_gui_html( $this->update_post_with_many_changes() );

		$this->assertMatchesRegularExpression( '#<dt>Title</dt>\s*<dd><div class=\'SimpleHistory__diff__contents\'#', $html );
		$this->assertStringContainsString( '<del>Old</del> title', $html );
		$this->assertStringContainsString( '<ins>New</ins> title', $html );
		$this->assertStringContainsString( '<del>old</del>-slug', $html );
		$this->assertStringContainsString( '<ins>new</ins>-slug', $html );
		$this->assertStringContainsString( '<del>Old</del> excerpt', $html );
		$this->assertStringContainsString( '<ins>New</ins> excerpt', $html );
		$this->assertMatchesRegularExpression( '#Deleted: </span>0</td><td class=\'diff-addedline\'>.*Added: </span>3</td>#', $html );
	}

	public function test_gui_shows_status_changes_inline() {
		$html = $this->get_gui_html( $this->update_post_with_many_changes() );

		$this->assertMatchesRegularExpression( '#<dt>Status</dt>\s*<dd>\s*<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">publish</ins>\s*<del class="SimpleHistoryLogitem__keyValueTable__removedThing">draft</del>#', $html );
		$this->assertMatchesRegularExpression( '#<dt>Comment status</dt>\s*<dd>\s*<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">closed</ins>#', $html );
	}

	public function test_gui_shows_long_content_change_as_side_by_side_diff() {
		$event = $this->update_post_with_many_changes();

		$this->assertArrayHasKey( 'post_content_diff', $event['context'], 'Long content is stored as a compact JSON diff' );

		$html = $this->get_gui_html( $event );

		$this->assertStringContainsString( '<dt>Content</dt><dd><div class="SimpleHistory__diff__contents"', $html );
		$this->assertStringContainsString( 'diff-side-by-side', $html );
		$this->assertStringContainsString( 'now edited', $html );
	}

	public function test_gui_shows_short_content_change_as_text_diff() {
		$post_id = $this->factory->post->create( [ 'post_content' => 'First paragraph.' ] );

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => 'First paragraph, edited.',
			]
		);

		$event = $this->get_inserted( 'post_updated' );

		$this->assertArrayNotHasKey( 'post_content_diff', $event['context'], 'Short content is stored in full' );

		$html = $this->get_gui_html( $event );

		$this->assertSame( [ 'Content' ], $this->get_gui_labels( $event ) );
		$this->assertStringContainsString( 'First paragraph<ins>, edited</ins>.', $html );
	}

	public function test_gui_label_filter_renames_title() {
		add_filter(
			'simple_history/post_logger/label_for_key',
			function ( $label, $key ) {
				return $key === 'post_title' ? 'Headline' : $label;
			},
			10,
			2
		);

		$this->assertContains( 'Headline', $this->get_gui_labels( $this->update_post_with_many_changes() ) );
	}

	public function test_gui_for_created_post_shows_content_excerpt_and_status() {
		wp_insert_post(
			[
				'post_title'   => 'Created',
				'post_excerpt' => 'An excerpt',
				'post_content' => 'Some content',
				'post_status'  => 'draft',
			]
		);

		$event = $this->get_inserted( 'post_created' );
		$html  = $this->get_gui_html( $event );

		$this->assertSame( [ 'Content', 'Excerpt', 'Status' ], $this->get_gui_labels( $event ) );
		$this->assertStringContainsString( 'Added: </span>Some content</td>', $html );
		$this->assertStringContainsString( 'Added: </span>draft</td>', $html );
	}

	/*
	 * Channels: what the log file, forwarding and alerts get.
	 */

	public function test_channel_text_has_readable_word_diffs() {
		$details = $this->get_channel_details( $this->update_post_with_many_changes() );
		$lines   = explode( "\n", $details['text'] );

		$this->assertContains( 'Status: draft → publish', $lines );
		$this->assertContains( 'Comment status: open → closed', $lines );
		$this->assertContains( 'Title: Old title → New title', $lines );
		$this->assertContains( 'Permalink: old-slug → new-slug', $lines );
		$this->assertContains( 'Menu order: 0 → 3', $lines );
		$this->assertContains( 'Excerpt: Old excerpt → New excerpt', $lines );
	}

	public function test_channel_text_has_one_labelled_line_for_a_long_content_change() {
		$details = $this->get_channel_details( $this->update_post_with_many_changes() );
		$lines   = explode( "\n", $details['text'] );

		$content_lines = array_values( preg_grep( '/^Content: /', $lines ) );

		$this->assertCount( 1, $content_lines, 'One line for the content change. Got: ' . $details['text'] );
		$this->assertStringContainsString( 'now edited', $content_lines[0] );

		foreach ( $lines as $line ) {
			$this->assertMatchesRegularExpression( '/^[^:]+: \S/', $line, 'Every line has a label' );
		}
	}

	public function test_channel_items_have_post_updated_changes() {
		$items = $this->get_channel_details( $this->update_post_with_many_changes() )['items'];

		$expected = [
			'post_new_post_status'    => [ 'Status', 'publish', 'draft' ],
			'post_new_comment_status' => [ 'Comment status', 'closed', 'open' ],
			'post_new_post_title'     => [ 'Title', 'New title', 'Old title' ],
			'post_new_post_name'      => [ 'Permalink', 'new-slug', 'old-slug' ],
			'post_new_menu_order'     => [ 'Menu order', '3', '0' ],
			'post_new_post_excerpt'   => [ 'Excerpt', 'New excerpt', 'Old excerpt' ],
		];

		foreach ( $expected as $key => list( $label, $new, $prev ) ) {
			$this->assertArrayHasKey( $key, $items, 'Got: ' . wp_json_encode( $items ) );
			$this->assertSame(
				[
					'label' => $label,
					'new'   => $new,
					'prev'  => $prev,
				],
				$items[ $key ]
			);
		}
	}

	public function test_channel_items_and_list_have_changed_lines_of_long_content() {
		$details = $this->get_channel_details( $this->update_post_with_many_changes() );
		$change  = [
			'new'  => '<p>Paragraph number 7, now edited, with some words in it.</p>',
			'prev' => '<p>Paragraph number 7 with some words in it.</p>',
		];

		$this->assertSame(
			[
				'label'   => 'Content',
				'changes' => [ $change ],
			],
			$details['items']['post_content_diff'] ?? null,
			'Got: ' . wp_json_encode( $details['items'] )
		);

		$this->assertContains( [ 'label' => 'Content' ] + $change + [ 'key' => 'post_content_diff' ], $details['list'] );
	}

	public function test_channel_changes_between_long_lines_say_changed() {
		$content = $this->get_long_content();
		$long    = str_repeat( 'Long line words ', 20 );

		$post_id = wp_insert_post(
			[
				'post_title'   => 'Long lines',
				'post_status'  => 'draft',
				'post_content' => $content . "\n" . $long . 'old end',
			]
		);

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => $content . "\n" . $long . 'new end',
			]
		);

		$event = $this->get_inserted( 'post_updated' );

		$this->assertArrayHasKey( 'post_content_diff', $event['context'] );

		$details = $this->get_channel_details( $event );

		$this->assertSame( [ [ 'changed' => true ] ], $details['items']['post_content_diff']['changes'] ?? null, 'Got: ' . wp_json_encode( $details['items'] ) );
	}

	public function test_channel_list_has_a_label_for_every_entry() {
		$list = $this->get_channel_details( $this->update_post_with_many_changes() )['list'];

		$labels = array_column( $list, 'label' );

		$this->assertNotContains( '', $labels, 'Got: ' . wp_json_encode( $list ) );

		foreach ( [ 'Status', 'Comment status', 'Title', 'Permalink', 'Menu order', 'Excerpt', 'Content' ] as $label ) {
			$this->assertContains( $label, $labels );
		}
	}

	public function test_rest_json_has_title_change() {
		$event = $this->update_post_with_many_changes();
		$items = [];

		foreach ( $this->get_gui_details( $event )->to_json() as $group ) {
			foreach ( $group['items'] ?? [] as $item ) {
				$items[ $item['name'] ?? '' ] = $item;
			}
		}

		$this->assertArrayHasKey( 'Title', $items, 'Got: ' . wp_json_encode( $items ) );
		$this->assertSame( 'New title', $items['Title']['new_value'] );
		$this->assertSame( 'Old title', $items['Title']['prev_value'] );
	}

	public function test_rest_json_has_changed_lines_of_long_content() {
		$event = $this->update_post_with_many_changes();
		$items = [];

		foreach ( $this->get_gui_details( $event )->to_json() as $group ) {
			foreach ( $group['items'] ?? [] as $item ) {
				$items[ $item['name'] ?? '' ] = $item;
			}
		}

		$this->assertArrayHasKey( 'Content', $items, 'Got: ' . wp_json_encode( $items ) );
		$this->assertSame(
			[
				'name'     => 'Content',
				'slug_new' => 'post_content_diff',
				'changes'  => [
					[
						'prev_value' => '<p>Paragraph number 7 with some words in it.</p>',
						'new_value'  => '<p>Paragraph number 7, now edited, with some words in it.</p>',
					],
				],
			],
			$items['Content']
		);
	}

	public function test_json_diff_changes_for_added_and_removed_lines() {
		$diff = wp_json_encode(
			[
				[
					[
						'tag' => 'eq',
						'old' => [ 'offset' => 0, 'lines' => [ 'Same' ] ],
						'new' => [ 'offset' => 0, 'lines' => [ 'Same' ] ],
					],
					[
						'tag' => 'del',
						'old' => [ 'offset' => 1, 'lines' => [ '<del>Gone &amp; done</del>' ] ],
						'new' => [ 'offset' => 1, 'lines' => [] ],
					],
					[
						'tag' => 'ins',
						'old' => [ 'offset' => 2, 'lines' => [] ],
						'new' => [ 'offset' => 4, 'lines' => [ '<ins>&lt;p&gt;New</ins>', 'two&lt;/p&gt;' ] ],
					],
				],
			]
		);

		$this->assertSame(
			[
				[ 'prev_value' => 'Gone & done' ],
				[ 'new_value' => "<p>New\ntwo</p>" ],
			],
			\Simple_History\Helpers::get_json_diff_changes( $diff )
		);

		$this->assertSame( [], \Simple_History\Helpers::get_json_diff_changes( 'not json' ) );

		// Two lines replaced by two lines: one change per line.
		$replace = wp_json_encode(
			[
				[
					[
						'tag' => 'rep',
						'old' => [ 'offset' => 0, 'lines' => [ 'A', 'B' ] ],
						'new' => [ 'offset' => 0, 'lines' => [ 'X', 'Y' ] ],
					],
					[
						'tag' => 'rep',
						'old' => [ 'offset' => 2, 'lines' => [ 'C' ] ],
						'new' => [ 'offset' => 2, 'lines' => [ 'Z1', 'Z2' ] ],
					],
				],
			]
		);

		$this->assertSame(
			[
				[ 'prev_value' => 'A', 'new_value' => 'X' ],
				[ 'prev_value' => 'B', 'new_value' => 'Y' ],
				[ 'prev_value' => 'C', 'new_value' => "Z1\nZ2" ],
			],
			\Simple_History\Helpers::get_json_diff_changes( $replace )
		);
	}

	public function test_change_from_zero_to_empty_is_kept() {
		$post_id = wp_insert_post(
			[
				'post_title'   => 'Zero',
				'post_excerpt' => '0',
				'post_status'  => 'draft',
			]
		);

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_excerpt' => '',
			]
		);

		$items = [];

		foreach ( $this->get_gui_details( $this->get_inserted( 'post_updated' ) )->to_json() as $group ) {
			foreach ( $group['items'] ?? [] as $item ) {
				$items[ $item['name'] ?? '' ] = $item;
			}
		}

		$this->assertArrayHasKey( 'Excerpt', $items, 'Got: ' . wp_json_encode( $items ) );
		$this->assertSame( '0', $items['Excerpt']['prev_value'] );
	}

	public function test_channel_text_for_created_post_has_no_line_per_content_line() {
		wp_insert_post(
			[
				'post_title'   => 'Created',
				'post_content' => $this->get_long_content(),
				'post_status'  => 'draft',
			]
		);

		$details = $this->get_channel_details( $this->get_inserted( 'post_created' ) );
		$lines   = explode( "\n", $details['text'] );

		$this->assertContains( 'Status: draft', $lines, 'Got: ' . $details['text'] );
		$this->assertCount( 1, preg_grep( '/^Content: /', $lines ), 'Got: ' . $details['text'] );

		foreach ( $lines as $line ) {
			$this->assertMatchesRegularExpression( '/^[^:→]+: [^→]/u', $line, 'Every line has a label and no empty "before" side' );
		}
	}

	/*
	 * Helpers.
	 */

	/**
	 * Create a draft and change most of its fields in one update.
	 * The content is long enough to be stored as a compact JSON diff.
	 *
	 * @return array{context: array, data: array}
	 */
	private function update_post_with_many_changes() {
		$content = $this->get_long_content();

		// A fixed date, or publishing the draft logs a publish date change
		// whenever the clock passes a second between the two calls.
		$date = '2026-01-01 10:00:00';

		$post_id = wp_insert_post(
			[
				'post_title'   => 'Old title',
				'post_name'    => 'old-slug',
				'post_excerpt' => 'Old excerpt',
				'post_status'  => 'draft',
				'post_content' => $content,
				'post_date'    => $date,
			]
		);

		wp_update_post(
			[
				'ID'             => $post_id,
				'post_date'      => $date,
				'edit_date'      => true,
				'post_title'     => 'New title',
				'post_name'      => 'new-slug',
				'post_excerpt'   => 'New excerpt',
				'post_status'    => 'publish',
				'post_content'   => str_replace( 'Paragraph number 7 with', 'Paragraph number 7, now edited, with', $content ),
				'menu_order'     => 3,
				'comment_status' => 'closed',
			]
		);

		return $this->get_inserted( 'post_updated' );
	}

	/**
	 * @return string Block content with 30 paragraphs.
	 */
	private function get_long_content() {
		$paragraphs = [];

		foreach ( range( 1, 30 ) as $i ) {
			$paragraphs[] = "<!-- wp:paragraph -->\n<p>Paragraph number {$i} with some words in it.</p>\n<!-- /wp:paragraph -->";
		}

		return implode( "\n\n", $paragraphs );
	}

	/**
	 * Find the last captured event with a message key.
	 *
	 * @param string $message_key Message key.
	 * @return array{context: array, data: array}
	 */
	private function get_inserted( $message_key ) {
		foreach ( array_reverse( $this->inserted ) as $event ) {
			if ( ( $event['context']['_message_key'] ?? '' ) === $message_key ) {
				return $event;
			}
		}

		$this->fail( "No event with message key {$message_key} was logged." );
	}

	/**
	 * Details as the GUI gets them: context values as stored, strings or JSON.
	 *
	 * @param array{context: array, data: array} $event Event.
	 * @return \Simple_History\Event_Details\Event_Details_Container_Interface
	 */
	private function get_gui_details( $event ) {
		$context = array_map(
			function ( $value ) {
				return is_string( $value ) ? $value : wp_json_encode( $value );
			},
			$event['context']
		);

		$row = (object) [
			'logger'  => 'SimplePostLogger',
			'context' => $context,
		];

		return Simple_History::get_instance()->get_log_row_details_output( $row );
	}

	/**
	 * @param array{context: array, data: array} $event Event.
	 * @return string
	 */
	private function get_gui_html( $event ) {
		return $this->get_gui_details( $event )->to_html();
	}

	/**
	 * @param array{context: array, data: array} $event Event.
	 * @return string[] The <dt> labels in the order they are shown.
	 */
	private function get_gui_labels( $event ) {
		preg_match_all( '#<dt>\s*(.*?)\s*</dt>#s', $this->get_gui_html( $event ), $matches );

		return $matches[1];
	}

	/**
	 * @param array{context: array, data: array} $event Event.
	 * @return array{text: string, items: array, list: array}
	 */
	private function get_channel_details( $event ) {
		return Channel_Event_Details::get( $event['context'], $event['data'] );
	}
}
