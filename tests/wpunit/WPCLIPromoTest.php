<?php

use Simple_History\Services\WP_CLI_Commands\WP_CLI_Promo;

/**
 * Tests for the WP-CLI premium upsell footer gating logic.
 *
 * The footer is shown on human-readable WP-CLI output (default table format)
 * and suppressed on machine-readable formats (json / csv / yaml / count / ids /
 * anything non-table) and when the project-wide promo gate says no.
 */
class WPCLIPromoTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var array
	 */
	private $original_active_plugins;

	public function setUp(): void {
		parent::setUp();
		$this->original_active_plugins = get_option( 'active_plugins', array() );
		// Ensure premium is NOT active for the default-state tests.
		update_option( 'active_plugins', array() );
		remove_all_filters( 'simple_history/wp_cli_promo_footer' );
	}

	public function tearDown(): void {
		update_option( 'active_plugins', $this->original_active_plugins );
		remove_all_filters( 'simple_history/wp_cli_promo_footer' );
		parent::tearDown();
	}

	public function test_footer_shows_on_default_format() {
		$this->assertTrue( WP_CLI_Promo::should_show_footer( array() ) );
		$this->assertNotEmpty( WP_CLI_Promo::get_footer_lines( array() ) );
	}

	public function test_footer_shows_on_table_format() {
		$this->assertTrue( WP_CLI_Promo::should_show_footer( array( 'format' => 'table' ) ) );
		$this->assertNotEmpty( WP_CLI_Promo::get_footer_lines( array( 'format' => 'table' ) ) );
	}

	/**
	 * @dataProvider machine_readable_formats
	 */
	public function test_footer_suppressed_on_machine_readable_format( $format ) {
		$args = array( 'format' => $format );
		$this->assertFalse( WP_CLI_Promo::should_show_footer( $args ) );
		$this->assertSame( array(), WP_CLI_Promo::get_footer_lines( $args ) );
	}

	public static function machine_readable_formats() {
		return array(
			'count' => array( 'count' ),
			'json'  => array( 'json' ),
			'csv'   => array( 'csv' ),
			'yaml'  => array( 'yaml' ),
			'ids'   => array( 'ids' ),
		);
	}

	public function test_footer_suppressed_when_premium_active() {
		update_option(
			'active_plugins',
			array( 'simple-history-premium/simple-history-premium.php' )
		);

		$this->assertFalse( WP_CLI_Promo::should_show_footer( array() ) );
		$this->assertSame( array(), WP_CLI_Promo::get_footer_lines( array() ) );
	}

	public function test_footer_suppressed_by_show_promo_boxes_filter() {
		add_filter( 'simple_history/show_promo_boxes', '__return_false' );

		$this->assertFalse( WP_CLI_Promo::should_show_footer( array() ) );
		$this->assertSame( array(), WP_CLI_Promo::get_footer_lines( array() ) );

		remove_filter( 'simple_history/show_promo_boxes', '__return_false' );
	}

	public function test_filter_can_suppress_footer() {
		add_filter( 'simple_history/wp_cli_promo_footer', '__return_empty_array' );

		$this->assertTrue( WP_CLI_Promo::should_show_footer( array() ) );
		$this->assertSame( array(), WP_CLI_Promo::get_footer_lines( array() ) );
	}

	public function test_filter_can_replace_footer_content() {
		add_filter(
			'simple_history/wp_cli_promo_footer',
			static function () {
				return array( 'Custom line A', 'Custom line B' );
			}
		);

		$lines = WP_CLI_Promo::get_footer_lines( array() );
		$this->assertSame( array( 'Custom line A', 'Custom line B' ), $lines );
	}

	public function test_footer_contains_premium_url() {
		$lines = WP_CLI_Promo::get_footer_lines( array() );
		$joined = implode( "\n", $lines );

		$this->assertStringContainsString( 'simple-history.com/premium', $joined );
	}
}
