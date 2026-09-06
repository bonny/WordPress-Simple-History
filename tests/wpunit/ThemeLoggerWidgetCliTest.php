<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Theme_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that Theme_Logger captures widget add/delete/edit changes made outside
 * wp-admin, specifically via WP-CLI.
 *
 * Issue #185: sidebar_admin_setup hooks drive widget add and delete detection.
 * These hooks are admin-only and the callbacks read $_POST directly, so WP-CLI
 * widget operations go unlogged.
 *
 * Fix: hook pre_update_option_sidebars_widgets to snapshot the layout before
 * any write, then diff against the new value in update_option_sidebars_widgets.
 *
 * widget_update_callback (edit) is a global filter — it may already work under
 * WP-CLI if wp widget update calls WP_Widget::update(). The edit test below
 * verifies this; it is currently marked as potentially green.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ThemeLoggerWidgetCliTest
 */
class ThemeLoggerWidgetCliTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Theme_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var string */
	private $sidebar_id = 'sidebar-1';

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleThemeLogger' );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Register a sidebar so wp_set_sidebars_widgets has a target.
		register_sidebar( array(
			'id'   => $this->sidebar_id,
			'name' => 'Test Sidebar',
		) );

		// Register the text widget so we have a known widget to add.
		wp_widgets_init();
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/is_rest_request' );
		// Reset sidebars_widgets to avoid leaking widget state between tests.
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( isset( $sidebars[ $this->sidebar_id ] ) ) {
			$sidebars[ $this->sidebar_id ] = array();
			update_option( 'sidebars_widgets', $sidebars );
		}
		parent::tearDown();
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger );
		$this->assertInstanceOf( Theme_Logger::class, $this->logger );
	}

	/**
	 * Regression guard: when sidebars_widgets is updated from within wp-admin,
	 * the non-admin handler must not fire. Admin widget add/remove is handled by
	 * the pre-existing sidebar_admin_setup path that reads $_POST — if our new
	 * update_option_sidebars_widgets handler doesn't bail on is_admin(), every
	 * admin widget change would be logged twice in production.
	 */
	public function test_admin_widget_add_does_not_double_log() {
		set_current_screen( 'widgets' );
		$this->assertTrue( is_admin(), 'is_admin() must be true for this test to be meaningful' );

		$count_before = $this->get_event_count();

		// Add a widget to the sidebar. In production this would trigger
		// sidebar_admin_setup via $_POST; here we exercise only the
		// update_option_sidebars_widgets path that our new handler hooks.
		$widget_number = 97;
		update_option( 'widget_text', array( $widget_number => array( 'text' => 'admin add' ) ) );

		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! isset( $sidebars[ $this->sidebar_id ] ) ) {
			$sidebars[ $this->sidebar_id ] = array();
		}
		$sidebars[ $this->sidebar_id ][] = "text-{$widget_number}";
		update_option( 'sidebars_widgets', $sidebars );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'Admin context must not produce a widget_added event from the non-admin handler (double-log guard)'
		);

		set_current_screen( 'front' );
	}

	/**
	 * WP-CLI: adding a widget by updating sidebars_widgets directly with
	 * WP_CLI=true must produce a widget_added log event.
	 *
	 * Simulates the internal state change that wp widget add performs:
	 * it ultimately calls update_option('sidebars_widgets', ...) with the
	 * new widget appended.
	 *
	 * Currently FAILS — detection runs through sidebar_admin_setup + $_POST.
	 */
	public function test_logs_widget_add_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! isset( $sidebars[ $this->sidebar_id ] ) ) {
			$sidebars[ $this->sidebar_id ] = array();
		}

		// Add a text widget instance.
		$widget_number = 99;
		update_option( 'widget_text', array( $widget_number => array( 'text' => 'hello' ) ) );

		$sidebars[ $this->sidebar_id ][] = "text-{$widget_number}";
		update_option( 'sidebars_widgets', $sidebars );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI widget add (sidebars_widgets update) must produce a widget_added log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleThemeLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'widget_added' );
	}

	/**
	 * WP-CLI: removing a widget by updating sidebars_widgets with WP_CLI=true
	 * must produce a widget_removed log event.
	 *
	 * Currently FAILS — same root cause as add.
	 */
	public function test_logs_widget_delete_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		// Set up: place a widget in the sidebar first.
		$widget_number = 98;
		update_option( 'widget_text', array( $widget_number => array( 'text' => 'to delete' ) ) );

		$sidebars = get_option( 'sidebars_widgets', array() );
		$sidebars[ $this->sidebar_id ]   = array( "text-{$widget_number}" );
		update_option( 'sidebars_widgets', $sidebars );

		$count_before = $this->get_event_count();

		// Remove the widget.
		$sidebars[ $this->sidebar_id ] = array();
		update_option( 'sidebars_widgets', $sidebars );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI widget delete (sidebars_widgets update) must produce a widget_removed log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleThemeLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'widget_removed' );
	}

	/**
	 * No spurious event when sidebars_widgets is written without any actual
	 * widget content change (e.g. WordPress touching the option on theme switch).
	 * A write with identical content must not produce a log row.
	 */
	public function test_no_event_when_sidebars_widgets_unchanged() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$current = get_option( 'sidebars_widgets', array() );
		$count_before = $this->get_event_count();

		// Write the same value back — no structural change.
		update_option( 'sidebars_widgets', $current );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'Writing sidebars_widgets with identical content must not produce a log row'
		);
	}

	private function get_event_count(): int {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'SimpleThemeLogger'
			)
		);
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}
}
