<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
require_once __DIR__ . '/_redirection_test_trait.php';

use Simple_History\Loggers\Plugin_Redirection_Logger;

/**
 * Tests for Plugin_Redirection_Logger::get_action_links() (issue 313).
 *
 * Covers:
 * - redirection_redirection_edited -> "Edit redirect" (filterby[url], action
 *   "edit") then "All redirects" (action "view").
 * - redirection_redirection_enabled/_disabled/_deleted and the global bulk keys
 *   -> "All redirects" only: the redirects list has no filter that can single
 *   out a redirect by id.
 * - redirection_redirection_added -> "Edit redirect" (filterby[url]) then
 *   "All redirects", since Redirection 5.10.0's redirects list honours
 *   `filterby[url]` on load.
 * - redirection_group_edited -> "Redirects in group" (filterby[group]) then
 *   "All groups"; every other redirection_group_* key -> "All groups" only.
 * - redirection_options_saved/_count -> "Redirection options" only.
 * - redirection_options_removed_all -> no links, the plugin is deactivating.
 * - A subscriber (lacking manage_options, Redirection's default capability) sees
 *   no links at all.
 *
 * The "Redirection inactive" case (`Red_Item` undefined -> no links) is not
 * covered here: tests/wpunit.suite.yml loads Redirection for the whole suite, so
 * the class is always defined in this process.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginRedirectionLoggerActionLinksTest
 */
class PluginRedirectionLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use RedirectionTestTrait;
	use ActionLinksTestTrait;

	/** @var Plugin_Redirection_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	/**
	 * `redirection.php` only requires `redirection-admin.php` (which in turn
	 * requires `redirection-capabilities.php`) when `red_is_admin()` or
	 * `red_is_wpcli()` is true at plugin-load time — neither is true for a
	 * Codeception/wpunit CLI run, so `\Redirection_Capabilities` never gets
	 * defined even though `\Red_Item` does (it's required unconditionally).
	 * Load the real file ourselves, same as PluginRedirectionLoggerSettingsDiffTest —
	 * this is Redirection's own class, not a stand-in for it.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::require_redirection_capabilities();
	}

	public function setUp(): void {
		parent::setUp();

		$this->skip_without_redirection();

		$this->logger_slug = 'Plugin_Redirection';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Redirection 5.10.0's redirects page only honours the filters in its own
	 * `allowedFilters` list (`build/redirection.js`): url, url-exact, target,
	 * title, group, status, match and action — no `id`. A `filterby[id]` link
	 * is silently ignored and lands on the unfiltered list, so an edited
	 * redirect is linked by its source URL instead.
	 */
	public function test_redirection_edited_shows_redirect_filtered_by_new_source_url_then_overview() {
		$row = $this->build_row( [
			'_message_key'    => 'redirection_redirection_edited',
			'redirection_id'  => 42,
			'new_source_url'  => '/new-source-url/',
			'prev_source_url' => '/old-source-url/',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertCount( 2, $links );

		$this->assertSame( 'Edit redirect', $links[0]['label'] );
		$this->assertStringContainsString( 'tools.php?page=redirection.php', $links[0]['url'] );
		$this->assertStringContainsString( 'filterby%5Burl%5D=' . rawurlencode( '/new-source-url/' ), $links[0]['url'] );
		$this->assertStringNotContainsString( 'filterby%5Bid%5D', $links[0]['url'] );
		$this->assertSame( 'edit', $links[0]['action'] );

		$this->assertSame( 'All redirects', $links[1]['label'] );
		$this->assertStringContainsString( 'tools.php?page=redirection.php', $links[1]['url'] );
		$this->assertStringNotContainsString( 'filterby', $links[1]['url'] );
		$this->assertSame( 'view', $links[1]['action'] );
	}

	/**
	 * Events logged before the source URL was stored on both sides, and edits
	 * that only changed the target, fall back to the previous source URL.
	 */
	public function test_redirection_edited_falls_back_to_previous_source_url() {
		$row = $this->build_row( [
			'_message_key'    => 'redirection_redirection_edited',
			'redirection_id'  => 42,
			'prev_source_url' => '/old-source-url/',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertStringContainsString( 'filterby%5Burl%5D=' . rawurlencode( '/old-source-url/' ), $links[0]['url'] );
	}

	public function test_redirection_edited_without_any_source_url_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key'   => 'redirection_redirection_edited',
			'redirection_id' => 42,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All redirects' ], $labels );
	}

	/**
	 * Enable/disable events store item ids and nothing else, and ids are not a
	 * filter the redirects list understands — so there is no per-item link to
	 * build, however many items the event carries.
	 */
	public function test_redirection_enabled_with_single_item_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_redirection_enabled',
			'items'        => wp_json_encode( [ 7 ] ),
			'items_count'  => 1,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All redirects' ], $labels );
	}

	public function test_redirection_disabled_with_multiple_items_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_redirection_disabled',
			'items'        => wp_json_encode( [ 7, 8, 9 ] ),
			'items_count'  => 3,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All redirects' ], $labels );
	}

	/**
	 * The global ("select all") bulk actions carry no items at all.
	 *
	 * @dataProvider global_bulk_message_key_provider
	 */
	public function test_global_bulk_events_show_the_matching_overview( $message_key, $expected_label ) {
		$row = $this->build_row( [ '_message_key' => $message_key ] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ $expected_label ], $labels );
	}

	public function global_bulk_message_key_provider() {
		return [
			'redirects enabled'  => [ 'redirection_redirection_enabled_all', 'All redirects' ],
			'redirects disabled' => [ 'redirection_redirection_disabled_all', 'All redirects' ],
			'redirects deleted'  => [ 'redirection_redirection_deleted_all', 'All redirects' ],
			'redirects reset'    => [ 'redirection_redirection_reset_all', 'All redirects' ],
			'groups enabled'     => [ 'redirection_group_enabled_all', 'All groups' ],
			'groups disabled'    => [ 'redirection_group_disabled_all', 'All groups' ],
			'groups deleted'     => [ 'redirection_group_deleted_all', 'All groups' ],
		];
	}

	public function test_redirection_deleted_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_redirection_deleted',
			'items'        => wp_json_encode( [ 7 ] ),
			'items_count'  => 1,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All redirects' ], $labels );
	}

	public function test_redirection_added_shows_redirect_filtered_by_url_then_overview() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_redirection_added',
			'source_url'   => '/old-url/',
			'target_url'   => '/new-url/',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertCount( 2, $links );

		$this->assertSame( 'Edit redirect', $links[0]['label'] );
		$this->assertStringContainsString( 'filterby%5Burl%5D=' . rawurlencode( '/old-url/' ), $links[0]['url'] );
		$this->assertSame( 'edit', $links[0]['action'] );

		$this->assertSame( 'All redirects', $links[1]['label'] );
	}

	public function test_group_edited_shows_redirects_in_group_then_all_groups() {
		$row = $this->build_row( [
			'_message_key'    => 'redirection_group_edited',
			'group_id'        => 3,
			'prev_group_name' => 'Old name',
			'new_group_name'  => 'New name',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'Redirects in group', 'All groups' ], $labels );

		$redirects_in_group = $this->find_by_label( $links, 'Redirects in group' );
		$this->assertStringContainsString( 'tools.php?page=redirection.php', $redirects_in_group['url'] );
		$this->assertStringContainsString( 'filterby%5Bgroup%5D=3', $redirects_in_group['url'] );
		$this->assertSame( 'view', $redirects_in_group['action'] );

		$all_groups = $this->find_by_label( $links, 'All groups' );
		$this->assertStringContainsString( 'sub=groups', $all_groups['url'] );
		$this->assertSame( 'view', $all_groups['action'] );
	}

	public function test_group_added_shows_all_groups_only() {
		// No group_id in context for "added" — only the overview renders.
		$row = $this->build_row( [
			'_message_key' => 'redirection_group_added',
			'group_name'   => 'New group',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All groups' ], $labels );
	}

	public function test_options_saved_shows_redirection_options_only() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_options_saved',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'Redirection options' ], $labels );

		$options_link = $this->find_by_label( $links, 'Redirection options' );
		$this->assertStringContainsString( 'sub=options', $options_link['url'] );
	}

	public function test_options_saved_count_shows_redirection_options_only() {
		$row = $this->build_row( [
			'_message_key'           => 'redirection_options_saved_count',
			'settings_changed_count' => 2,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'Redirection options' ], $labels );
	}

	public function test_options_removed_all_shows_no_links() {
		$row = $this->build_row( [
			'_message_key' => 'redirection_options_removed_all',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertSame( [], $links );
	}

	public function test_subscriber_gets_no_links_for_any_message_key() {
		wp_set_current_user( $this->subscriber_user_id );

		$edited_row = $this->build_row( [
			'_message_key'   => 'redirection_redirection_edited',
			'redirection_id' => 42,
			'new_source_url' => '/new-source-url/',
		] );
		$this->assertSame( [], $this->logger->get_action_links( $edited_row ) );

		$group_row = $this->build_row( [
			'_message_key' => 'redirection_group_added',
			'group_name'   => 'Some group',
		] );
		$this->assertSame( [], $this->logger->get_action_links( $group_row ) );

		// group_edited's extra "Redirects in group" link needs its own cap
		// check (CAP_REDIRECT_MANAGE) — confirm it doesn't leak through.
		$group_edited_row = $this->build_row( [
			'_message_key' => 'redirection_group_edited',
			'group_id'     => 3,
		] );
		$this->assertSame( [], $this->logger->get_action_links( $group_edited_row ) );

		$options_row = $this->build_row( [
			'_message_key' => 'redirection_options_saved',
		] );
		$this->assertSame( [], $this->logger->get_action_links( $options_row ) );
	}
}
