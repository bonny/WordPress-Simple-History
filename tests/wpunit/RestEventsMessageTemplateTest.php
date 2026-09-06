<?php

use Simple_History\Simple_History;

/**
 * The message_template field labels a hidden event type in the GUI. It must
 * be the template in the site language, not the English template stored
 * with the event, and fall back to the stored one when the logger has no
 * translation for the key.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RestEventsMessageTemplateTest
 */
class RestEventsMessageTemplateTest extends \Codeception\TestCase\WPTestCase {
	/** @var \Simple_History\Loggers\Post_Logger */
	private $post_logger;

	/** @var string */
	private $original_translated_text;

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->post_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		// Loads the messages, then swap in a "translation" for one of them.
		$this->original_translated_text = $this->post_logger->get_translated_message( 'post_updated' );

		$this->post_logger->messages['post_updated']['translated_text'] = 'Uppdaterade {post_type} "{post_title}"';
	}

	public function tearDown(): void {
		$this->post_logger->messages['post_updated']['translated_text'] = $this->original_translated_text;

		parent::tearDown();
	}

	public function test_message_template_is_the_translated_template_and_uninterpolated_is_the_stored_one() {
		$this->post_logger->info_message(
			'post_updated',
			array(
				'post_type'  => 'post',
				'post_title' => 'Hello world',
			)
		);

		$event = $this->get_latest_event();

		$this->assertSame( 'post_updated', $event['message_key'] );
		$this->assertSame( 'Uppdaterade {post_type} "{post_title}"', $event['message_template'] );
		$this->assertSame( 'Updated {post_type} "{post_title}"', $event['message_uninterpolated'] );
	}

	public function test_message_template_falls_back_to_the_stored_message_without_a_message_key() {
		$this->post_logger->info( 'Did something to {thing}', array( 'thing' => 'a post' ) );

		$event = $this->get_latest_event();

		$this->assertSame( 'Did something to {thing}', $event['message_template'] );
		$this->assertSame( 'Did something to {thing}', $event['message_uninterpolated'] );
	}

	private function get_latest_event(): array {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( '_fields', 'message_key,message_template,message_uninterpolated' );

		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertNotEmpty( $data );

		return $data[0];
	}
}
