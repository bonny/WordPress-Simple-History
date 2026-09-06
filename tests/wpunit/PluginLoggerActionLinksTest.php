<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Plugin_Logger;

/**
 * Tests for Plugin_Logger::get_action_links().
 *
 * Covers:
 * - GitHub-hosted plugins get a nonce-wrapped thickbox link to the GitHub
 *   info AJAX endpoint.
 * - plugin_updated / plugin_bulk_updated → "Changelog" thickbox.
 * - plugin_installed / plugin_activated / plugin_deactivated → "Plugin info".
 * - Always include the "All plugins" overview when the viewer has the cap
 *   appropriate for the destination (single-site: activate_plugins;
 *   multisite: manage_network_plugins — only the single-site branch is
 *   exercised here, multisite installs require a separate suite).
 * - Per-plugin links require `install_plugins` (the destination wp_dies
 *   without it). Editors and subscribers see only the overview link.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginLoggerActionLinksTest
 */
class PluginLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var Plugin_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $editor_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'SimplePluginLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->editor_user_id     = $this->factory->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	public function test_plugin_updated_shows_changelog_and_overview() {
		$row = $this->build_row( [
			'_message_key' => 'plugin_updated',
			'plugin_slug'  => 'akismet',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Changelog', $labels );
		$this->assertContains( 'All plugins', $labels );

		$changelog = $this->find_by_label( $links, 'Changelog' );
		$this->assertStringContainsString( 'plugin-install.php', $changelog['url'] );
		$this->assertStringContainsString( 'section=changelog', $changelog['url'] );
		$this->assertStringContainsString( 'plugin=akismet', $changelog['url'] );
	}

	public function test_plugin_activated_shows_plugin_info_and_overview() {
		$row = $this->build_row( [
			'_message_key' => 'plugin_activated',
			'plugin_slug'  => 'akismet',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Plugin info', $labels );
		$this->assertContains( 'All plugins', $labels );
	}

	public function test_github_plugin_uses_ajax_thickbox() {
		$row = $this->build_row( [
			'_message_key'      => 'plugin_activated',
			'plugin_slug'       => 'some-plugin',
			'plugin_github_url' => 'https://github.com/user/repo',
		] );

		$links = $this->logger->get_action_links( $row );
		$info  = $this->find_by_label( $links, 'Plugin info' );

		$this->assertNotNull( $info );
		$this->assertStringContainsString( 'action=SimplePluginLogger_GetGitHubPluginInfo', $info['url'] );
		$this->assertStringContainsString( '_wpnonce=', $info['url'] );
	}

	public function test_overview_link_only_when_no_per_plugin_action_matches() {
		// Unknown message key — no per-plugin link, but overview still renders.
		$row = $this->build_row( [
			'_message_key' => 'plugin_unknown_event',
			'plugin_slug'  => 'akismet',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All plugins' ], $labels );
	}

	public function test_subscriber_gets_no_links_at_all() {
		wp_set_current_user( $this->subscriber_user_id );

		$row = $this->build_row( [
			'_message_key' => 'plugin_activated',
			'plugin_slug'  => 'akismet',
		] );

		$links = $this->logger->get_action_links( $row );

		// Subscriber lacks both install_plugins (per-plugin) and activate_plugins (overview).
		$this->assertSame( [], $links );
	}

	public function test_editor_without_install_plugins_only_sees_overview_when_capable() {
		// Editor has neither install_plugins nor activate_plugins by default,
		// so no links are surfaced.
		wp_set_current_user( $this->editor_user_id );

		$row = $this->build_row( [
			'_message_key' => 'plugin_activated',
			'plugin_slug'  => 'akismet',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertSame( [], $links );
	}

	public function test_user_with_activate_but_not_install_sees_overview_only() {
		// Synthetic role: can read the plugins listing but cannot install/inspect.
		// Pins the gate: per-plugin "Plugin info" requires install_plugins,
		// while the overview only requires activate_plugins. Without the gate
		// the user would see a "Plugin info" link that wp_dies on click.
		$role = get_role( 'editor' );
		$role->add_cap( 'activate_plugins' );

		try {
			wp_set_current_user( $this->editor_user_id );

			$row = $this->build_row( [
				'_message_key' => 'plugin_activated',
				'plugin_slug'  => 'akismet',
			] );

			$links  = $this->logger->get_action_links( $row );
			$labels = wp_list_pluck( $links, 'label' );

			$this->assertNotContains( 'Plugin info', $labels );
			$this->assertNotContains( 'Changelog', $labels );
			$this->assertContains( 'All plugins', $labels );
		} finally {
			$role->remove_cap( 'activate_plugins' );
		}
	}

	public function test_github_link_also_gated_by_install_plugins() {
		// Synthetic role: activate_plugins but no install_plugins.
		$role = get_role( 'editor' );
		$role->add_cap( 'activate_plugins' );

		try {
			wp_set_current_user( $this->editor_user_id );

			$row = $this->build_row( [
				'_message_key'      => 'plugin_activated',
				'plugin_slug'       => 'some-plugin',
				'plugin_github_url' => 'https://github.com/user/repo',
			] );

			$links  = $this->logger->get_action_links( $row );
			$labels = wp_list_pluck( $links, 'label' );

			$this->assertNotContains( 'Plugin info', $labels, 'GitHub thickbox needs install_plugins' );
			$this->assertContains( 'All plugins', $labels );
		} finally {
			$role->remove_cap( 'activate_plugins' );
		}
	}

}
