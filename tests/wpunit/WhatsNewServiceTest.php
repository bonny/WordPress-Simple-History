<?php

use Simple_History\Simple_History;
use Simple_History\Services\Whats_New_Service;

/**
 * Tests for the Whats_New_Service feed fetch/cache robustness rules.
 *
 * The render path must soft-fail silently for every feed failure mode:
 * no card, no PHP errors, no uncached HTTP requests.
 */
class WhatsNewServiceTest extends \Codeception\TestCase\WPTestCase {
	/** @var array<string,mixed>|null Request args captured by the HTTP mock. */
	private $captured_request_args = null;

	public function setUp(): void {
		parent::setUp();

		$this->captured_request_args = null;

		delete_site_transient( Whats_New_Service::FEED_TRANSIENT );
		delete_transient( Whats_New_Service::LOCK_TRANSIENT );
		delete_option( Whats_New_Service::LAST_KNOWN_GOOD_OPTION );
		delete_option( Whats_New_Service::HTTP_META_OPTION );
	}

	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'simple_history/whats_new/license_state' );

		parent::tearDown();
	}

	private function service(): Whats_New_Service {
		return new Whats_New_Service( Simple_History::get_instance() );
	}

	/**
	 * Short-circuit all HTTP requests with a canned response.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Response body.
	 */
	private function mock_http_response( int $code, string $body ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args ) use ( $code, $body ) {
				$this->captured_request_args = $args;

				return [
					'headers'  => [],
					'body'     => $body,
					'response' => [
						'code'    => $code,
						'message' => '',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			2
		);
	}

	/**
	 * A feed entry that passes schema validation.
	 *
	 * @return array<string,mixed>
	 */
	private function valid_entry( string $title = 'Slack alerts' ): array {
		return [
			'version'             => '5.24.0',
			'plugin'              => 'premium',
			'title'               => $title,
			'summary'             => 'Get alerts in Slack when things happen.',
			'url'                 => 'https://simple-history.com/add-ons/premium/',
			'min_version_to_show' => '',
			'audience'            => [ 'all' ],
		];
	}

	public function test_404_results_in_no_highlights_and_no_error() {
		$this->mock_http_response( 404, 'Not found' );

		$service = $this->service();
		$service->fetch_feed();

		$this->assertSame( [], $service->get_highlights() );
		$this->assertFalse( get_site_transient( Whats_New_Service::FEED_TRANSIENT ) );
		$this->assertFalse( get_option( Whats_New_Service::LAST_KNOWN_GOOD_OPTION ) );
	}

	public function test_malformed_json_caches_empty_and_logs() {
		$this->mock_http_response( 200, '{this is not json' );

		$service = $this->service();
		$service->fetch_feed();

		$this->assertSame( [], $service->get_highlights() );

		// Empty array cached (not false), so no refetch storm before next cron.
		$this->assertSame( [], get_site_transient( Whats_New_Service::FEED_TRANSIENT ) );

		// Malformed payload must never become last-known-good.
		$this->assertFalse( get_option( Whats_New_Service::LAST_KNOWN_GOOD_OPTION ) );
	}

	public function test_wrong_top_level_shape_treated_as_malformed() {
		$this->mock_http_response( 200, wp_json_encode( [ 'items' => [ $this->valid_entry() ] ] ) );

		$service = $this->service();
		$service->fetch_feed();

		$this->assertSame( [], $service->get_highlights() );
		$this->assertSame( [], get_site_transient( Whats_New_Service::FEED_TRANSIENT ) );
	}

	public function test_invalid_entries_are_skipped_but_valid_ones_kept() {
		$entry_missing_title = $this->valid_entry();
		unset( $entry_missing_title['title'] );

		$entry_bad_plugin           = $this->valid_entry( 'Bad plugin entry' );
		$entry_bad_plugin['plugin'] = 'not-a-real-plugin';

		$body = wp_json_encode(
			[
				'highlights' => [
					$entry_missing_title,
					$this->valid_entry( 'Kept entry' ),
					$entry_bad_plugin,
					'not even an array',
				],
			]
		);

		$this->mock_http_response( 200, $body );

		$service = $this->service();
		$service->fetch_feed();

		$highlights = $service->get_highlights();

		$this->assertCount( 1, $highlights );
		$this->assertSame( 'Kept entry', $highlights[0]['title'] );
	}

	public function test_empty_highlights_renders_no_card() {
		$this->mock_http_response( 200, wp_json_encode( [ 'highlights' => [] ] ) );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Simulate a lapsed license so only the empty feed suppresses the card.
		add_filter(
			'simple_history/whats_new/license_state',
			function () {
				return [
					'state'      => 'expired',
					'expires_at' => gmdate( 'Y-m-d', time() - 100 * DAY_IN_SECONDS ),
				];
			}
		);

		$service = $this->service();
		$service->fetch_feed();

		ob_start();
		$service->output_expired_card();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_fetch_passes_timeout_argument() {
		$this->mock_http_response( 200, wp_json_encode( [ 'highlights' => [] ] ) );

		$this->service()->fetch_feed();

		$this->assertIsArray( $this->captured_request_args );
		$this->assertArrayHasKey( 'timeout', $this->captured_request_args );
		$this->assertSame( 10, $this->captured_request_args['timeout'] );
	}

	public function test_card_renders_for_expired_license_with_enough_highlights() {
		$body = wp_json_encode(
			[
				'highlights' => [
					$this->valid_entry( 'Slack alerts' ),
					$this->valid_entry( 'Scheduled email reports' ),
					$this->valid_entry( 'Custom log channels' ),
				],
			]
		);

		$this->mock_http_response( 200, $body );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		add_filter(
			'simple_history/whats_new/license_state',
			function () {
				return [
					'state'      => 'expired',
					'expires_at' => gmdate( 'Y-m-d', time() - 45 * DAY_IN_SECONDS ),
				];
			}
		);

		$service = $this->service();
		$service->fetch_feed();

		ob_start();
		$service->output_expired_card();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'sh-WhatsNewCard', $output );
		$this->assertStringContainsString( 'Your Premium license lapsed', $output );
		$this->assertStringContainsString( 'Slack alerts', $output );
		$this->assertStringContainsString( 'utm_campaign=premium_whats_new', $output );
		$this->assertStringContainsString( 'Dismiss renewal reminder', $output );
	}

	public function test_fewer_than_three_highlights_suppresses_card() {
		$body = wp_json_encode(
			[
				'highlights' => [
					$this->valid_entry( 'Slack alerts' ),
					$this->valid_entry( 'Scheduled email reports' ),
				],
			]
		);

		$this->mock_http_response( 200, $body );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		add_filter(
			'simple_history/whats_new/license_state',
			function () {
				return [
					'state'      => 'expired',
					'expires_at' => gmdate( 'Y-m-d', time() - 45 * DAY_IN_SECONDS ),
				];
			}
		);

		$service = $this->service();
		$service->fetch_feed();

		ob_start();
		$service->output_expired_card();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
