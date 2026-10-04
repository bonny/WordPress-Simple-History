<?php

use Simple_History\Simple_History;
use Simple_History\Loggers\Comments_Logger;

/**
 * The comments logger masks the commenter's IP address like every other IP in the log.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit CommentsLoggerIpAddressTest
 *
 * @coversDefaultClass Simple_History\Loggers\Comments_Logger
 */
class CommentsLoggerIpAddressTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Comments_Logger
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new Comments_Logger( Simple_History::get_instance() );
	}

	function test_commenter_ip_address_is_masked() {
		$comment_id = $this->factory->comment->create( array( 'comment_author_IP' => '192.168.1.23' ) );

		$context = $this->logger->get_context_for_comment( $comment_id );

		$this->assertSame( '192.168.1.x', $context['comment_author_IP'] );
	}

	function test_commenter_ip_address_is_kept_when_masking_is_off() {
		$comment_id = $this->factory->comment->create( array( 'comment_author_IP' => '192.168.1.23' ) );

		add_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );
		$context = $this->logger->get_context_for_comment( $comment_id );
		remove_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );

		$this->assertSame( '192.168.1.23', $context['comment_author_IP'] );
	}

	function test_empty_commenter_ip_address_stays_empty() {
		// wp_privacy_anonymize_ip() turns an empty string into "0.0.0.0".
		$comment_id = $this->factory->comment->create( array( 'comment_author_IP' => '' ) );

		$context = $this->logger->get_context_for_comment( $comment_id );

		$this->assertSame( '', $context['comment_author_IP'] );
	}
}
