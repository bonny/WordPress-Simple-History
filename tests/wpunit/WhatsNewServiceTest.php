<?php

use Simple_History\AddOn_Plugin;
use Simple_History\Simple_History;
use Simple_History\Services\Whats_New_Service;

/**
 * Service with a Premium add-on the test controls, instead of the one
 * registered (or not) on the test site.
 */
class Whats_New_Service_With_Test_Premium extends Whats_New_Service {
	/** @var AddOn_Plugin|null */
	public $test_premium = null;

	protected function get_premium_add_on() {
		return $this->test_premium;
	}
}

/**
 * The "What's new" box, billing messages and lapsed card: who sees what, when.
 */
class WhatsNewServiceTest extends \Codeception\TestCase\WPTestCase {
	private const PREMIUM_OPTION = 'simple_history_plusplugin_message_simple-history-premium';

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );

		delete_option( self::PREMIUM_OPTION );
		delete_option( 'simple_history_plusplugin_new_features_simple-history-premium' );
		delete_option( Whats_New_Service::CHANGELOG_CACHE_OPTION );
	}

	public function tearDown(): void {
		delete_option( self::PREMIUM_OPTION );
		delete_option( 'simple_history_plusplugin_new_features_simple-history-premium' );

		parent::tearDown();
	}

	private function service( ?AddOn_Plugin $premium = null ): Whats_New_Service_With_Test_Premium {
		$service               = new Whats_New_Service_With_Test_Premium( Simple_History::get_instance() );
		$service->test_premium = $premium;

		return $service;
	}

	private function premium( string $version = '1.10.0' ): AddOn_Plugin {
		return new AddOn_Plugin( 'simple-history-premium/simple-history-premium.php', 'simple-history-premium', $version, 'Premium', 1 );
	}

	/**
	 * Store a checked Premium license, optionally with a subscription.
	 *
	 * @param string     $status License status.
	 * @param string     $expires_at Expiry.
	 * @param array|null $subscription Subscription, or null for none.
	 */
	private function seed_premium_license( string $status, string $expires_at, ?array $subscription = null ): void {
		update_option(
			self::PREMIUM_OPTION,
			[
				'key'           => 'abc-123',
				'key_activated' => true,
			]
		);

		$license = [
			'valid'      => $status === 'active',
			'status'     => $status,
			'expires_at' => $expires_at,
			'checked_at' => '2026-10-07T12:00:00Z',
		];

		if ( $subscription !== null ) {
			$license['subscription'] = $subscription;
		}

		$this->premium()->update_license_status_from_response( $license );
	}

	private function newest_core_version(): string {
		$versions = \Simple_History\Changelog_Parser::parse( file_get_contents( SIMPLE_HISTORY_PATH . 'readme.txt' ) );

		return (string) array_key_first( $versions );
	}

	// "What's new" box.

	public function test_first_view_stores_the_installed_version_and_shows_nothing() {
		$service = $this->service();

		$this->assertSame( [], $service->get_pending_updates( $this->admin_id ) );

		$seen = get_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, true );
		$this->assertSame( SIMPLE_HISTORY_VERSION, $seen['simple-history']['version'] );
	}

	public function test_update_since_last_seen_version_is_pending_with_counts() {
		$newest = $this->newest_core_version();
		add_filter( 'simple_history/whats_new/plugins', fn( $plugins ) => $this->with_core_version( $plugins, $newest ) );

		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		$updates = $this->service()->get_pending_updates( $this->admin_id );

		$this->assertArrayHasKey( 'simple-history', $updates );
		$this->assertSame( $newest, $updates['simple-history']['summary']['versions'][0] );
		$this->assertStringStartsWith( 'https://simple-history.com/', $updates['simple-history']['summary']['release_url'] );
	}

	public function test_box_counts_as_seen_after_three_views() {
		$newest = $this->newest_core_version();
		add_filter( 'simple_history/whats_new/plugins', fn( $plugins ) => $this->with_core_version( $plugins, $newest ) );
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		$service = $this->service();

		for ( $i = 0; $i < 3; $i++ ) {
			ob_start();
			$service->output_top_slot();
			$output = ob_get_clean();

			$this->assertStringContainsString( 'sh-WhatsNew', $output, "Shown on view {$i}" );
		}

		ob_start();
		$service->output_top_slot();
		$this->assertSame( '', trim( ob_get_clean() ) );
	}

	public function test_views_of_an_older_update_do_not_count_for_a_newer_one() {
		$newest = $this->newest_core_version();
		add_filter( 'simple_history/whats_new/plugins', fn( $plugins ) => $this->with_core_version( $plugins, $newest ) );

		// Shown twice for an earlier update, then a newer one was installed.
		update_user_meta(
			$this->admin_id,
			Whats_New_Service::META_SEEN,
			[
				'simple-history' => [
					'version'   => '5.0.0',
					'views'     => 2,
					'views_for' => '5.0.1',
				],
			]
		);

		$service = $this->service();

		for ( $i = 0; $i < 3; $i++ ) {
			ob_start();
			$service->output_top_slot();

			$this->assertStringContainsString( 'sh-WhatsNew', ob_get_clean(), "Shown on view {$i}" );
		}
	}

	public function test_readme_in_an_unexpected_format_shows_no_box() {
		$readme = wp_tempnam( 'readme.txt' );
		file_put_contents( $readme, "## Changelog\n\n### 9.0.0\n\nSomething new, written as prose without sections." );

		add_filter(
			'simple_history/whats_new/plugins',
			function ( $plugins ) use ( $readme ) {
				$plugins['simple-history']['version'] = '9.0.0';
				$plugins['simple-history']['readme']  = $readme;

				return $plugins;
			}
		);
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		ob_start();
		$this->service()->output_top_slot();
		$output = ob_get_clean();

		unlink( $readme );

		$this->assertSame( '', trim( $output ) );
	}

	public function test_missing_readme_shows_no_box() {
		add_filter(
			'simple_history/whats_new/plugins',
			function ( $plugins ) {
				$plugins['simple-history']['version'] = '9.0.0';
				$plugins['simple-history']['readme']  = '/nonexistent/readme.txt';

				return $plugins;
			}
		);
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		$this->assertSame( [], $this->service()->get_pending_updates( $this->admin_id ) );
	}

	public function test_dismissing_marks_every_plugin_seen() {
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		$service = $this->service();
		$service->mark_all_seen( $this->admin_id );

		$this->assertSame( [], $service->get_pending_updates( $this->admin_id ) );
	}

	public function test_downgrade_shows_nothing() {
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '99.0.0', 'views' => 0 ] ] );

		$this->assertSame( [], $this->service()->get_pending_updates( $this->admin_id ) );
	}

	public function test_update_line_wording_per_combination() {
		$service = $this->service();
		$line    = fn( $features, $fixes, $other ) => $service->get_update_line(
			[
				'name'    => 'Simple History',
				'version' => '5.35.0',
				'summary' => [
					'features' => $features,
					'fixes'    => $fixes,
					'other'    => $other,
				],
			]
		);

		$this->assertSame( 'Simple History 5.35.0: 4 new features and 9 fixes.', $line( 4, 9, 2 ) );
		$this->assertSame( 'Simple History 5.35.0: 1 new feature.', $line( 1, 0, 0 ) );
		$this->assertSame( 'Simple History 5.35.0: 5 fixes.', $line( 0, 5, 0 ) );
		$this->assertSame( 'Simple History 5.35.0: fixes and small improvements.', $line( 0, 2, 1 ) );
		$this->assertSame( 'Simple History 5.35.0: 3 improvements.', $line( 0, 0, 3 ) );
	}

	public function test_box_title_names_the_new_version() {
		$service = $this->service();
		$core    = [
			'slug'    => 'simple-history',
			'name'    => 'Simple History',
			'version' => '5.36.0',
		];
		$premium = [
			'slug'    => 'simple-history-premium',
			'name'    => 'Premium',
			'version' => '1.17.0',
		];

		$this->assertSame( 'Updated to Simple History 5.36.0', $service->get_box_title( [ 'simple-history-premium' => $premium, 'simple-history' => $core ] ) );
		$this->assertSame( 'Updated to Simple History Premium 1.17.0', $service->get_box_title( [ 'simple-history-premium' => $premium ] ) );
	}

	public function test_counts_text_after_the_title() {
		$service = $this->service();
		$counts  = fn( $features, $fixes, $other ) => $service->get_counts_text(
			[
				'features' => $features,
				'fixes'    => $fixes,
				'other'    => $other,
			]
		);

		$this->assertSame( '9 new features and 15 fixes.', $counts( 9, 15, 3 ) );
		$this->assertSame( '1 new feature.', $counts( 1, 0, 0 ) );
		$this->assertSame( 'Fixes and small improvements.', $counts( 0, 1, 0 ) );
	}

	public function test_first_line_leads_with_the_bold_title() {
		$newest = $this->newest_core_version();
		add_filter( 'simple_history/whats_new/plugins', fn( $plugins ) => $this->with_core_version( $plugins, $newest ) );
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		ob_start();
		$this->service()->output_top_slot();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<strong class="sh-WhatsNew-title"[^>]*>Updated to Simple History ' . preg_quote( $newest, '/' ) . '<\/strong>\s*\d+ new features?/', $output );
	}

	public function test_line_links_the_release_post() {
		$newest = $this->newest_core_version();
		add_filter( 'simple_history/whats_new/plugins', fn( $plugins ) => $this->with_core_version( $plugins, $newest ) );
		update_user_meta( $this->admin_id, Whats_New_Service::META_SEEN, [ 'simple-history' => [ 'version' => '5.0.0', 'views' => 0 ] ] );

		ob_start();
		$this->service()->output_top_slot();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'href="https://simple-history.com/', $output );
		$this->assertStringContainsString( 'Release post →', $output );
		$this->assertStringNotContainsString( '<details', $output );
	}

	// Billing messages.

	public function test_failed_payment_shows_billing_message_to_admins_only() {
		$this->seed_premium_license( 'active', '2026-10-20T00:00:00Z', [ 'status' => 'past_due', 'cancelled' => false, 'renews_at' => '2026-10-20T00:00:00Z', 'ends_at' => null ] );
		$service = $this->service( $this->premium() );

		$this->assertSame( 'payment_failed', $service->get_billing_message()['type'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->assertNull( $service->get_billing_message() );
	}

	public function test_cancelled_subscription_shows_end_date_until_dismissed() {
		$ends_at = gmdate( 'Y-m-d\TH:i:s\Z', time() + 20 * DAY_IN_SECONDS );
		$this->seed_premium_license( 'active', $ends_at, [ 'status' => 'cancelled', 'cancelled' => true, 'renews_at' => $ends_at, 'ends_at' => $ends_at ] );
		$service = $this->service( $this->premium() );

		$message = $service->get_billing_message();
		$this->assertSame( 'ends', $message['type'] );

		ob_start();
		$service->output_top_slot();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'Premium keeps working after that', $output );

		update_user_meta( $this->admin_id, Whats_New_Service::META_BILLING_DISMISSED, $message['key'] );
		$this->assertNull( $service->get_billing_message() );
	}

	public function test_license_answer_without_subscription_clears_the_stored_one() {
		$this->seed_premium_license( 'active', '2026-10-20T00:00:00Z', [ 'status' => 'past_due', 'cancelled' => false ] );
		$this->assertNotNull( $this->premium()->get_license_state()['subscription'] );

		// Renewed: the key is a year from expiry, so the server sends no subscription.
		$this->seed_premium_license( 'active', '2027-10-20T00:00:00Z' );

		$this->assertNull( $this->premium()->get_license_state()['subscription'] );
		$this->assertNull( $this->service( $this->premium() )->get_billing_message() );
	}

	// Lapsed card.

	private function seed_new_features( int $count ): void {
		$features = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$features[] = [
				'version' => '1.' . ( 20 + $i ) . '.0',
				'name'    => 'Feature ' . $i,
				'date'    => '2026-0' . ( 1 + $i ) . '-01',
			];
		}

		$this->premium()->update_new_features_from_response( json_decode( wp_json_encode( $features ) ) );
	}

	public function test_lapsed_card_stage_follows_days_since_expiry() {
		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 45 * DAY_IN_SECONDS ) );
		$this->seed_new_features( 4 );

		$card = $this->service( $this->premium() )->get_lapsed_card();

		$this->assertSame( 'recent', $card['stage'] );
		$this->assertCount( 4, $card['features'] );

		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 400 * DAY_IN_SECONDS ) );
		$this->assertSame( 'old', $this->service( $this->premium() )->get_lapsed_card()['stage'] );
	}

	public function test_lapsed_card_needs_three_new_features() {
		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 45 * DAY_IN_SECONDS ) );
		$this->seed_new_features( 2 );

		$this->assertNull( $this->service( $this->premium() )->get_lapsed_card() );
	}

	public function test_lapsed_card_hidden_while_a_billing_message_applies() {
		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 5 * DAY_IN_SECONDS ), [ 'status' => 'past_due', 'cancelled' => false ] );
		$this->seed_new_features( 4 );

		$this->assertNull( $this->service( $this->premium() )->get_lapsed_card() );
	}

	public function test_lapsed_card_dismissal_lasts_one_stage() {
		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 45 * DAY_IN_SECONDS ) );
		$this->seed_new_features( 4 );
		update_user_meta( $this->admin_id, Whats_New_Service::META_LAPSED_DISMISSED, [ 'recent' => true ] );

		$this->assertNull( $this->service( $this->premium() )->get_lapsed_card() );

		$this->seed_premium_license( 'expired', gmdate( 'Y-m-d\TH:i:s\Z', time() - 120 * DAY_IN_SECONDS ) );
		$this->assertSame( 'year', $this->service( $this->premium() )->get_lapsed_card()['stage'] );
	}

	public function test_new_features_only_count_versions_after_the_installed_one() {
		$this->seed_new_features( 4 );

		$features = $this->premium( '1.21.0' )->get_new_features( '1.21.0' );

		$this->assertSame( [ '1.23.0', '1.22.0' ], array_column( $features, 'version' ) );
	}

	/**
	 * Set the core plugin's version in the plugin list.
	 *
	 * @param array  $plugins Plugins.
	 * @param string $version Version.
	 * @return array
	 */
	private function with_core_version( array $plugins, string $version ): array {
		$plugins['simple-history']['version'] = $version;

		return $plugins;
	}
}
