<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_History_Logger;
use Simple_History\Event_Details\Event_Details_Container;

/**
 * Test Simple History settings change logging.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest
 */
class SimpleHistorySettingsLoggerTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );

		$this->assertNotNull( $this->logger, 'SimpleHistoryLogger should be instantiated' );

		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );
	}

	public function tearDown(): void {
		// Flush the per-request tracked-settings cache so a filter added by
		// one test cannot leak into the next via the shared logger instance.
		$this->logger->get_tracked_settings( true );

		parent::tearDown();
	}

	public function test_core_keys_are_tracked() {
		$tracked = $this->logger->get_tracked_settings();

		$this->assertArrayHasKey( 'simple_history_show_on_dashboard', $tracked );
		$this->assertSame( 'Show on dashboard', $tracked['simple_history_show_on_dashboard'] );
	}

	public function test_filter_can_add_tracked_keys() {
		$callback = static function ( $tracked ) {
			$tracked['my_addon_option'] = 'My add-on option';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );

		// Rebuild (the map is cached per request; use a fresh logger lookup).
		$tracked = $this->logger->get_tracked_settings( true );

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$this->assertArrayHasKey( 'my_addon_option', $tracked );
		$this->assertSame( 'My add-on option', $tracked['my_addon_option'] );
	}

	public function test_logs_tracked_option_change_on_commit() {
		$callback = static function ( $tracked ) {
			$tracked['sh_test_tracked_option'] = 'Test tracked option';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );
		$this->logger->get_tracked_settings( true );

		add_option( 'sh_test_tracked_option', 'old-value' );
		update_option( 'sh_test_tracked_option', 'new-value' );

		// Commit is normally on 'shutdown'; call directly in the test.
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$row = \Simple_History\tests\get_latest_row();
		$this->assertSame( 'SimpleHistoryLogger', $row['logger'], 'Event should be logged by the Simple History logger' );

		$context = \Simple_History\tests\get_latest_context();
		$context_map = [];
		foreach ( $context as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertArrayHasKey( '_message_key', $context_map );
		$this->assertSame( 'modified_settings', $context_map['_message_key'] );
		$this->assertSame( 'old-value', $context_map['sh_test_tracked_option_prev'] );
		$this->assertSame( 'new-value', $context_map['sh_test_tracked_option_new'] );
	}

	public function test_does_not_log_untracked_option_change() {
		update_option( 'some_unrelated_option', 'whatever' );
		$this->logger->commit_settings_changes();

		$context = \Simple_History\tests\get_latest_context();
		$context_map = [];
		foreach ( $context as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		// No tracked change accumulated, so the latest event must not be a settings change for this option.
		$this->assertArrayNotHasKey( 'some_unrelated_option_new', $context_map );
	}

	public function test_details_output_renders_tracked_key_with_label() {
		$callback = static function ( $tracked ) {
			$tracked['shp_message_control'] = 'Message Control settings';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );
		$this->logger->get_tracked_settings( true );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'shp_message_control_prev' => 'a',
				'shp_message_control_new'  => 'b',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );

		// Event_Details_Group is not stringable on its own; render it through an
		// Event_Details_Container (the proper API), which applies the context and
		// exposes __toString().
		$html = (string) ( new Event_Details_Container( $output, $row->context ) );

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$this->assertStringContainsString( 'Message Control settings', $html );
	}

	public function test_details_output_falls_back_to_raw_base_when_no_label() {
		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'sh_unlabeled_option_prev' => 'x',
				'sh_unlabeled_option_new'  => 'y',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new \Simple_History\Event_Details\Event_Details_Container( $output, $row->context ) );

		$this->assertStringContainsString( 'sh_unlabeled_option', $html );
	}

	public function test_details_output_renders_multiple_changed_settings() {
		$callback = static function ( $tracked ) {
			$tracked['shp_one'] = 'Setting One';
			$tracked['shp_two'] = 'Setting Two';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );
		$this->logger->get_tracked_settings( true );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'shp_one_prev' => '1a',
				'shp_one_new'  => '1b',
				'shp_two_prev' => '2a',
				'shp_two_new'  => '2b',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new \Simple_History\Event_Details\Event_Details_Container( $output, $row->context ) );

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$this->assertStringContainsString( 'Setting One', $html );
		$this->assertStringContainsString( 'Setting Two', $html );
	}

	public function test_details_output_empty_for_non_settings_message() {
		$row = (object) [
			'context_message_key' => 'cleared_log',
			'context'             => [
				'num_rows_deleted' => '5',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );

		$this->assertSame( '', $output );
	}
}
