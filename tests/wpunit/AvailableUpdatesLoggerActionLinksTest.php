<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Available_Updates_Logger;

/**
 * Tests for Available_Updates_Logger::get_action_links().
 *
 * Covers:
 * - plugin_update_available with a slug → "Changelog" + "Plugin info"
 *   thickbox links plus the "All updates" overview.
 * - No slug (non-wp.org plugin) → only the overview link.
 * - core/theme update events → only the overview link.
 * - Per-plugin links require `install_plugins` (the thickbox destination
 *   wp_dies without it); a user with only update_* caps sees just the
 *   overview link.
 * - All links use action type "view" (Changelog historically used "edit",
 *   which rendered a misleading pencil icon).
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit AvailableUpdatesLoggerActionLinksTest
 */
class AvailableUpdatesLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var Available_Updates_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $editor_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'AvailableUpdatesLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->editor_user_id     = $this->factory->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	public function test_plugin_update_available_shows_changelog_plugin_info_and_overview() {
		$row = $this->build_row( [
			'_message_key' => 'plugin_update_available',
			'plugin_slug'  => 'akismet',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'Changelog', 'Plugin info', 'All updates' ], $labels );

		$changelog = $this->find_by_label( $links, 'Changelog' );
		$this->assertStringContainsString( 'plugin-install.php', $changelog['url'] );
		$this->assertStringContainsString( 'section=changelog', $changelog['url'] );
		$this->assertStringContainsString( 'plugin=akismet', $changelog['url'] );

		$plugin_info = $this->find_by_label( $links, 'Plugin info' );
		$this->assertStringContainsString( 'plugin-install.php', $plugin_info['url'] );
		$this->assertStringContainsString( 'plugin=akismet', $plugin_info['url'] );
		$this->assertStringNotContainsString( 'section=changelog', $plugin_info['url'] );
	}

	public function test_all_links_use_view_action() {
		$row = $this->build_row( [
			'_message_key' => 'plugin_update_available',
			'plugin_slug'  => 'akismet',
		] );

		$links   = $this->logger->get_action_links( $row );
		$actions = array_unique( wp_list_pluck( $links, 'action' ) );

		$this->assertSame( [ 'view' ], array_values( $actions ) );
	}

	public function test_plugin_update_without_slug_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key' => 'plugin_update_available',
			'plugin_slug'  => '',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All updates' ], $labels );
	}

	public function test_core_update_available_shows_overview_only() {
		$row = $this->build_row( [
			'_message_key' => 'core_update_available',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All updates' ], $labels );
	}

	public function test_subscriber_gets_no_links_at_all() {
		wp_set_current_user( $this->subscriber_user_id );

		$row = $this->build_row( [
			'_message_key' => 'plugin_update_available',
			'plugin_slug'  => 'akismet',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertSame( [], $links );
	}

	public function test_user_with_update_but_not_install_sees_overview_only() {
		// Synthetic role: can reach update-core.php but not the
		// plugin-information thickbox. Pins the gate: per-plugin links
		// require install_plugins, the overview only an update_* cap.
		// Without the gate the user would see links that wp_die on click.
		$role = get_role( 'editor' );
		$role->add_cap( 'update_plugins' );

		try {
			wp_set_current_user( $this->editor_user_id );

			$row = $this->build_row( [
				'_message_key' => 'plugin_update_available',
				'plugin_slug'  => 'akismet',
			] );

			$links  = $this->logger->get_action_links( $row );
			$labels = wp_list_pluck( $links, 'label' );

			$this->assertSame( [ 'All updates' ], $labels );
		} finally {
			$role->remove_cap( 'update_plugins' );
		}
	}
}
