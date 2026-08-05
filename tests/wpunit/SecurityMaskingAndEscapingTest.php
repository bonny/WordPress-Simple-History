<?php

use Simple_History\Dropins\Detective_Mode_Dropin;
use Simple_History\Simple_History;

/**
 * Sensitive-value masking and logger output escaping.
 *
 * Companion to SecurityAuthorizationTest — that file covers who may read what,
 * this one covers what ends up stored and how it is rendered.
 */
class SecurityMaskingAndEscapingTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Call a protected masking method on the dropin.
	 *
	 * Built without its constructor, since none of the masking helpers touch
	 * instance state.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function call_protected( $method, $args ) {
		$dropin     = ( new ReflectionClass( Detective_Mode_Dropin::class ) )->newInstanceWithoutConstructor();
		$reflection = new ReflectionMethod( Detective_Mode_Dropin::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $dropin, $args );
	}

	/**
	 * Names that must be masked. Prefix matching missed most of these:
	 * old_password does not start with "pass", and tokens, secrets and card
	 * numbers were never covered at all.
	 *
	 * @return array<array<string>>
	 */
	public function sensitive_field_names() {
		return [
			[ 'password' ],
			[ 'old_password' ],
			[ 'current_password' ],
			[ 'confirm_password_2' ],
			[ 'user_pass' ],
			[ 'pwd' ],
			[ 'api_token' ],
			[ 'refresh_token' ],
			[ 'client_secret' ],
			[ 'Authorization' ],
			[ 'api_key' ],
			[ 'private_key' ],
			[ 'card_number' ],
			[ 'cc_number' ],
			[ 'credit_card' ],
			[ 'cvv' ],
		];
	}

	/**
	 * @dataProvider sensitive_field_names
	 * @param string $field_name Field name that must be masked.
	 */
	public function test_sensitive_fields_are_masked( $field_name ) {
		$masked = $this->call_protected( 'mask_sensitive_data', [ [ $field_name => 'SENSITIVE' ] ] );

		$this->assertNotSame( 'SENSITIVE', $masked[ $field_name ], "{$field_name} must be masked." );
	}

	/**
	 * Ordinary names that must survive. Over-broad needles are their own bug:
	 * "auth" would swallow author/post_author and a bare "cc" would swallow
	 * account, which quietly guts the feature's usefulness.
	 *
	 * @return array<array<string>>
	 */
	public function ordinary_field_names() {
		return [
			[ 'author' ],
			[ 'post_author' ],
			[ 'account' ],
			[ 'account_id' ],
			[ 'username' ],
			[ 'user_login' ],
			[ 'post_title' ],
			[ 'keyword' ],
			[ 'action' ],
		];
	}

	/**
	 * @dataProvider ordinary_field_names
	 * @param string $field_name Field name that must not be masked.
	 */
	public function test_ordinary_fields_are_not_masked( $field_name ) {
		$masked = $this->call_protected( 'mask_sensitive_data', [ [ $field_name => 'ORDINARY' ] ] );

		$this->assertSame( 'ORDINARY', $masked[ $field_name ], "{$field_name} must not be masked." );
	}

	/**
	 * Request bodies nest, and a top-level-only walk stored nested credentials
	 * in full.
	 */
	public function test_masking_recurses_into_nested_arrays() {
		$masked = $this->call_protected(
			'mask_sensitive_data',
			[
				[
					'user' => [
						'password' => 'SENSITIVE',
						'name'     => 'ORDINARY',
					],
					'deep' => [ 'a' => [ 'refresh_token' => 'SENSITIVE' ] ],
				],
			]
		);

		$this->assertNotSame( 'SENSITIVE', $masked['user']['password'] );
		$this->assertNotSame( 'SENSITIVE', $masked['deep']['a']['refresh_token'] );
		$this->assertSame( 'ORDINARY', $masked['user']['name'], 'Nested ordinary values must survive.' );
	}

	/**
	 * REQUEST_URI repeats every query parameter, so masking $_GET alone left
	 * the same secret in plain sight one context key over.
	 */
	public function test_query_string_values_are_masked() {
		$masked = $this->call_protected(
			'mask_sensitive_query_string',
			[ '/wp-admin/admin.php?page=x&api_key=SECRET&token=SECRET&keep=visible' ]
		);

		$this->assertStringNotContainsString( 'SECRET', $masked );
		$this->assertStringContainsString( 'keep=visible', $masked, 'Ordinary parameters must stay readable.' );
		$this->assertStringContainsString( '/wp-admin/admin.php?page=x', $masked, 'The path must stay intact.' );
	}

	/**
	 * WP-CLI commands routinely carry credentials as flags.
	 */
	public function test_cli_argument_values_are_masked() {
		$this->assertStringNotContainsString(
			'hunter2',
			$this->call_protected( 'mask_sensitive_cli_argument', [ '--user_pass=hunter2' ] )
		);

		$this->assertSame(
			'--role=editor',
			$this->call_protected( 'mask_sensitive_cli_argument', [ '--role=editor' ] ),
			'Ordinary flags must stay readable.'
		);

		$this->assertSame(
			'user',
			$this->call_protected( 'mask_sensitive_cli_argument', [ 'user' ] ),
			'A bare argument has no name to judge, so it is left alone.'
		);
	}

	/**
	 * Loggers that override get_log_row_plain_text_output() skip the base
	 * esc_html(), and their output is rendered with dangerouslySetInnerHTML.
	 * The comments logger interpolates the parent post title, which a user
	 * holding unfiltered_html controls.
	 */
	public function test_comments_logger_escapes_interpolated_values() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleCommentsLogger' );

		$row = (object) [
			'id'        => 1,
			'logger'    => 'SimpleCommentsLogger',
			'level'     => 'info',
			'date'      => current_time( 'mysql' ),
			'initiator' => 'wp_user',
			'message'   => 'Approved a comment to "{comment_post_title}" by {comment_author}',
			'context'   => [
				'_message_key'         => 'comment_approved',
				'comment_post_ID'      => 0,
				'comment_post_title'   => 'TITLE<img src=x onerror=alert(1)>END',
				'comment_author'       => 'AUTH<script>alert(2)</script>OR',
				'comment_author_email' => 'a@example.com',
				'comment_type'         => 'comment',
			],
		];

		$output = $logger->get_log_row_plain_text_output( $row );

		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*onerror/i', $output, 'Post title must not produce a live img tag.' );
		$this->assertStringNotContainsString( '<script', strtolower( $output ), 'Comment author must not produce a script tag.' );
		$this->assertStringContainsString( 'TITLE', $output, 'The readable part of the title must survive.' );
	}
}
