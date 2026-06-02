<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Core_Updates_Logger;

/**
 * Tests for Core_Updates_Logger::get_action_links() and its version helpers.
 *
 * The action-link rules:
 * - Only `core_updated` / `core_auto_updated` messages get links (settings
 *   changes, failures, db updates do not).
 * - A link only appears when the bump crosses a major X or Y boundary;
 *   patch-only bumps (Z) and RC-to-RC bumps inside the same X.Y are silent.
 * - The local "About this version" link is drift-gated: it only renders when
 *   the currently installed WP X.Y still matches the event's new_version X.Y.
 *   Otherwise the page content would be about a different version than the
 *   event message claims.
 * - The external "WordPress {version} release notes" link uses the X.Y of
 *   new_version (matching WP's own `sanitize_title()`-based URL pattern in
 *   wp-admin/about.php) and is always shown on major-bump events because the
 *   wordpress.org docs page is version-specific and historically accurate.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit CoreUpdatesLoggerActionLinksTest
 */
class CoreUpdatesLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var Core_Updates_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var string */
	private $original_wp_version;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'SimpleCoreUpdatesLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		$this->original_wp_version  = $GLOBALS['wp_version'] ?? '';
	}

	public function tearDown(): void {
		$GLOBALS['wp_version'] = $this->original_wp_version;
		parent::tearDown();
	}

	/* -------------------------------------------------------------------- */
	/* extract_major_version()                                              */
	/* -------------------------------------------------------------------- */

	/**
	 * @dataProvider provider_extract_major_version
	 */
	public function test_extract_major_version( $input, $expected ) {
		$actual = $this->invoke_private( 'extract_major_version', [ $input ] );

		$this->assertSame( $expected, $actual );
	}

	public function provider_extract_major_version() {
		return [
			'X.Y stays as X.Y'              => [ '6.9', '6.9' ],
			'X.Y.Z drops the Z'             => [ '6.5.3', '6.5' ],
			'Single-digit patch'            => [ '7.0.1', '7.0' ],
			'RC suffix is stripped'         => [ '7.0-RC5', '7.0' ],
			'Beta suffix is stripped'       => [ '6.4-beta1', '6.4' ],
			'Alpha suffix is stripped'      => [ '7.0-alpha2', '7.0' ],
			'Major-only number returns ""'  => [ '7', '' ],
			'Empty string returns ""'       => [ '', '' ],
			'Garbage returns ""'            => [ 'nonsense', '' ],
		];
	}

	/* -------------------------------------------------------------------- */
	/* is_major_version_update()                                            */
	/* -------------------------------------------------------------------- */

	/**
	 * @dataProvider provider_is_major_version_update
	 */
	public function test_is_major_version_update( $prev, $new, $expected ) {
		$actual = $this->invoke_private( 'is_major_version_update', [ $prev, $new ] );

		$this->assertSame( $expected, $actual );
	}

	public function provider_is_major_version_update() {
		return [
			'X bumps (6.9 → 7.0)'                  => [ '6.9', '7.0', true ],
			'Y bumps (6.5.3 → 6.6)'                => [ '6.5.3', '6.6', true ],
			'Patch only (6.5 → 6.5.1)'             => [ '6.5', '6.5.1', false ],
			'Patch only (6.9.1 → 6.9.2)'           => [ '6.9.1', '6.9.2', false ],
			'RC-to-RC same X.Y'                    => [ '7.0-RC4', '7.0-RC5', false ],
			'Stable → RC crossing major'           => [ '6.9', '7.0-RC1', true ],
			'RC → Stable same X.Y'                 => [ '7.0-RC5', '7.0', false ],
			'Empty prev'                            => [ '', '7.0', false ],
			'Empty new'                             => [ '6.9', '', false ],
			'Both empty'                            => [ '', '', false ],
		];
	}

	/* -------------------------------------------------------------------- */
	/* get_action_links() — non-update message keys                         */
	/* -------------------------------------------------------------------- */

	public function test_no_links_on_settings_change() {
		$row = $this->build_row( [
			'_message_key' => 'core_major_auto_updates_setting_enabled',
		] );

		$this->assertSame( [], $this->logger->get_action_links( $row ) );
	}

	public function test_no_links_on_update_failure() {
		$row = $this->build_row( [
			'_message_key' => 'core_update_failed',
			'prev_version' => '6.9',
			'new_version'  => '7.0',
		] );

		$this->assertSame( [], $this->logger->get_action_links( $row ) );
	}

	/* -------------------------------------------------------------------- */
	/* get_action_links() — version-bump gating                             */
	/* -------------------------------------------------------------------- */

	public function test_no_links_on_patch_bump() {
		$GLOBALS['wp_version'] = '6.9.2';

		$row = $this->build_row( [
			'_message_key' => 'core_updated',
			'prev_version' => '6.9.1',
			'new_version'  => '6.9.2',
		] );

		$this->assertSame( [], $this->logger->get_action_links( $row ) );
	}

	public function test_no_links_on_rc_to_rc_inside_same_major() {
		$GLOBALS['wp_version'] = '7.0-RC5';

		$row = $this->build_row( [
			'_message_key' => 'core_updated',
			'prev_version' => '7.0-RC4',
			'new_version'  => '7.0-RC5',
		] );

		$this->assertSame( [], $this->logger->get_action_links( $row ) );
	}

	/* -------------------------------------------------------------------- */
	/* get_action_links() — drift gate                                      */
	/* -------------------------------------------------------------------- */

	public function test_major_bump_when_current_matches_event() {
		$GLOBALS['wp_version'] = '6.9.4';

		$row = $this->build_row( [
			'_message_key' => 'core_updated',
			'prev_version' => '6.8.1',
			'new_version'  => '6.9',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertCount( 2, $links, 'Matching event/current X.Y must surface both links' );

		$this->assertEquals( 'About this version', $links[0]['label'] );
		$this->assertStringEndsWith( '/wp-admin/about.php', $links[0]['url'] );

		$this->assertEquals( 'WordPress 6.9 release notes', $links[1]['label'] );
		$this->assertSame(
			'https://wordpress.org/documentation/wordpress-version/version-6-9/',
			$links[1]['url']
		);
	}

	public function test_major_bump_when_current_drifted_only_external_link() {
		// The user is currently on 7.0.x but the event is for an older 6.6 bump.
		// Local about.php would land them on 7.0 content — misleading. Drop it.
		$GLOBALS['wp_version'] = '7.0.1';

		$row = $this->build_row( [
			'_message_key' => 'core_auto_updated',
			'prev_version' => '6.5.3',
			'new_version'  => '6.6',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertCount( 1, $links, 'Drifted event must surface only the external release-notes link' );

		$this->assertEquals( 'WordPress 6.6 release notes', $links[0]['label'] );
		$this->assertSame(
			'https://wordpress.org/documentation/wordpress-version/version-6-6/',
			$links[0]['url']
		);
	}

	public function test_rc_new_version_strips_suffix_in_url_and_label() {
		// 6.9 → 7.0-RC1 — still a major bump; URL/label use the X.Y, not the RC suffix.
		$GLOBALS['wp_version'] = '7.0-RC1';

		$row = $this->build_row( [
			'_message_key' => 'core_updated',
			'prev_version' => '6.9',
			'new_version'  => '7.0-RC1',
		] );

		$links = $this->logger->get_action_links( $row );

		// About this version renders (event 7.0 matches current 7.0-RC1 → both extract to 7.0).
		$this->assertCount( 2, $links );

		$this->assertEquals( 'WordPress 7.0 release notes', $links[1]['label'] );
		$this->assertSame(
			'https://wordpress.org/documentation/wordpress-version/version-7-0/',
			$links[1]['url']
		);
	}

	/* -------------------------------------------------------------------- */
	/* Capability gating                                                    */
	/* -------------------------------------------------------------------- */

	public function test_no_links_when_user_not_logged_in() {
		wp_set_current_user( 0 );
		$GLOBALS['wp_version'] = '7.0';

		$row = $this->build_row( [
			'_message_key' => 'core_updated',
			'prev_version' => '6.9',
			'new_version'  => '7.0',
		] );

		$this->assertSame( [], $this->logger->get_action_links( $row ) );
	}

	/* -------------------------------------------------------------------- */
	/* Helpers                                                              */
	/* -------------------------------------------------------------------- */

	/**
	 * Call a private method on the logger via reflection.
	 */
	private function invoke_private( string $method, array $args ) {
		$ref = new ReflectionMethod( Core_Updates_Logger::class, $method );
		$ref->setAccessible( true );

		return $ref->invoke( $this->logger, ...$args );
	}
}
