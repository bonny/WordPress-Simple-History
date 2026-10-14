<?php

use Simple_History\Channels\Channel_Event_Details;
use Simple_History\Channels\Human_Readable_Formatter;

/**
 * Issue 365: event details (the "what changed" part) reach the file log and
 * the forwarding channels, not only the GUI.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ChannelEventDetailsTest
 */
class ChannelEventDetailsTest extends \Codeception\TestCase\WPTestCase {
	/** @var array<int, array{context: array, data: array}> Events captured from the inserted hook. */
	private $inserted = [];

	public function setUp(): void {
		parent::setUp();

		$this->inserted = [];

		add_action( 'simple_history/log/inserted', [ $this, 'capture_inserted' ], 10, 2 );

		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// The user logger only collects profile changes in admin, REST or WP-CLI requests.
		add_filter( 'simple_history/is_rest_request', '__return_true' );
	}

	public function tearDown(): void {
		remove_action( 'simple_history/log/inserted', [ $this, 'capture_inserted' ], 10 );
		remove_all_filters( 'simple_history/is_rest_request' );
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
	 * The original report: a role change on the profile screen only wrote
	 * "Edited the profile for user john" to the file log.
	 */
	public function test_role_change_on_profile_has_role_details() {
		$user_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_update_user(
			[
				'ID'   => $user_id,
				'role' => 'author',
			]
		);

		$event   = $this->get_inserted( 'user_updated_profile' );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		$this->assertStringContainsString( 'Role added: author', $details['text'] );
		$this->assertStringContainsString( 'Role removed: editor', $details['text'] );

		$this->assertSame( 'author', $details['items']['user_added_roles']['new'] );
		$this->assertSame( 'editor', $details['items']['user_removed_roles']['new'] );
		$this->assertSame( 'Role added', $details['items']['user_added_roles']['label'] );
	}

	/**
	 * Context keys that are not part of the logger's details, like the IP
	 * address or user agent, must never leak into the channel details.
	 */
	public function test_details_only_contain_logger_defined_items() {
		$user_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_update_user(
			[
				'ID'   => $user_id,
				'role' => 'author',
			]
		);

		$event   = $this->get_inserted( 'user_updated_profile' );
		$context = $event['context'];

		$context['_server_remote_addr'] = '192.0.2.10';
		$context['_server_http_user_agent'] = 'Secret agent';

		$details = Channel_Event_Details::get( $context, $event['data'] );

		$this->assertStringNotContainsString( '192.0.2.10', $details['text'] );
		$this->assertStringNotContainsString( 'Secret agent', $details['text'] );
		$this->assertSame( [ 'user_added_roles', 'user_removed_roles' ], array_keys( $details['items'] ) );
	}

	public function test_long_values_are_truncated() {
		$user_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_update_user(
			[
				'ID'           => $user_id,
				'display_name' => str_repeat( 'x', 1000 ),
			]
		);

		$event   = $this->get_inserted( 'user_updated_profile' );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		$this->assertNotSame( '', $details['text'] );

		foreach ( explode( "\n", $details['text'] ) as $line ) {
			$this->assertLessThanOrEqual( Channel_Event_Details::MAX_VALUE_LENGTH, preg_match_all( '/./us', $line ) );
		}
	}

	public function test_event_without_details_gives_empty_details() {
		SimpleLogger()->info( 'A plain message without details' );

		$event   = end( $this->inserted );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		$this->assertSame( '', $details['text'] );
		$this->assertSame( [], $details['items'] );
	}

	public function test_human_readable_formatter_writes_details_on_the_same_line() {
		$formatter = new Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'        => 'info',
				'logger'       => 'SimpleUserLogger',
				'initiator'    => 'wp_user',
				'context'      => [ '_message_key' => 'user_updated_profile' ],
				'details_text' => "Role added: administrator\nRole removed: author",
			],
			'Edited the profile for user "john"'
		);

		$this->assertStringContainsString(
			'Edited the profile for user "john" | Role added: administrator; Role removed: author | initiator=wp_user',
			$output
		);
		$this->assertSame( 1, substr_count( $output, "\n" ), 'Only the trailing newline is allowed.' );
	}

	public function test_human_readable_formatter_escapes_line_breaks_in_details() {
		$formatter = new Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'        => 'info',
				'logger'       => 'SimplePostLogger',
				'initiator'    => 'wp_user',
				'context'      => [],
				'details_text' => "Title: old → new\r[2026-01-01T00:00:00Z] CRITICAL forged",
			],
			'Updated post'
		);

		$this->assertSame( 1, substr_count( $output, "\n" ), 'Only the trailing newline is allowed.' );
		$this->assertStringNotContainsString( "\r", $output );
	}

	public function test_human_readable_formatter_without_details_is_unchanged() {
		$formatter = new Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'     => 'info',
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'wp_user',
				'context'   => [ '_message_key' => 'user_logged_in' ],
			],
			'Logged in'
		);

		$this->assertStringContainsString( 'Logged in | initiator=wp_user message_key=user_logged_in', $output );
	}

	/**
	 * The list has every item in display order, also profile fields that
	 * have no context key, which the keyed items leave out.
	 */
	public function test_list_has_all_items_in_order() {
		$user_id = $this->factory->user->create(
			[
				'role'       => 'editor',
				'first_name' => 'John',
			]
		);

		wp_update_user(
			[
				'ID'         => $user_id,
				'role'       => 'author',
				'first_name' => 'Johnny',
			]
		);

		$event   = $this->get_inserted( 'user_updated_profile' );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );
		$labels  = array_column( $details['list'], 'label' );

		$this->assertSame( [ 'First name', 'Role added', 'Role removed' ], $labels );
		$this->assertSame( 'John', $details['list'][0]['prev'] );
		$this->assertSame( 'Johnny', $details['list'][0]['new'] );
		$this->assertArrayNotHasKey( 'key', $details['list'][0], 'The first name item has no context key.' );
		$this->assertSame( 'user_added_roles', $details['list'][1]['key'] );
		$this->assertSame( 'author', $details['list'][1]['new'] );
	}

	/**
	 * Two long texts: cut-off starts say nothing, so the item only says it changed.
	 */
	public function test_list_marks_a_change_between_long_values_as_changed() {
		$user_id = $this->factory->user->create(
			[
				'role'        => 'editor',
				'description' => str_repeat( 'a', 500 ),
			]
		);

		wp_update_user(
			[
				'ID'          => $user_id,
				'description' => str_repeat( 'b', 500 ),
			]
		);

		$event   = $this->get_inserted( 'user_updated_profile' );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		$this->assertCount( 1, $details['list'] );
		$this->assertTrue( $details['list'][0]['changed'] );
		$this->assertArrayNotHasKey( 'new', $details['list'][0] );
		$this->assertArrayNotHasKey( 'prev', $details['list'][0] );
	}

	public function test_list_keeps_prev_and_new_of_a_raw_item_without_label() {
		$filter = function () {
			$item = ( new \Simple_History\Event_Details\Event_Details_Item( null, '' ) )->set_formatter(
				( new \Simple_History\Event_Details\Event_Details_Item_RAW_Formatter() )
					->set_html_output( '<p>Old → New</p>' )
					->set_json_output(
						[
							'new_value'  => 'New',
							'prev_value' => 'Old',
						]
					)
			);

			return ( new \Simple_History\Event_Details\Event_Details_Group() )->add_item( $item );
		};

		add_filter( 'simple_history/row_details_output', $filter );

		SimpleLogger()->info( 'Raw item with values' );

		$event   = end( $this->inserted );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		remove_filter( 'simple_history/row_details_output', $filter );

		$this->assertSame(
			[
				[
					'label' => '',
					'new'   => 'New',
					'prev'  => 'Old',
				],
			],
			$details['list']
		);
	}

	public function test_event_without_details_has_an_empty_list() {
		SimpleLogger()->info( 'A plain message without details' );

		$event   = end( $this->inserted );
		$details = Channel_Event_Details::get( $event['context'], $event['data'] );

		$this->assertSame( [], $details['list'] );
	}
}
