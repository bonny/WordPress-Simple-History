<?php

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Finding the plugin that made a change from the call stack, and showing it
 * as the event's "via". Issue 331.
 */
class CallingPluginTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Build a backtrace from file paths, innermost frame first.
	 *
	 * @param string ...$files File paths.
	 * @return array
	 */
	private function backtrace( ...$files ) {
		return array_map(
			function ( $file ) {
				return [ 'file' => $file ];
			},
			$files
		);
	}

	public function test_finds_innermost_plugin_frame() {
		$plugin = Helpers::get_calling_plugin(
			$this->backtrace(
				ABSPATH . 'wp-includes/class-wp-role.php',
				WP_PLUGIN_DIR . '/wp-crontrol/src/bootstrap.php',
				WP_PLUGIN_DIR . '/redirection/redirection.php',
				ABSPATH . 'wp-settings.php'
			)
		);

		$this->assertSame(
			[
				'slug' => 'wp-crontrol',
				'type' => 'plugin',
				'file' => 'wp-crontrol/src/bootstrap.php',
			],
			$plugin
		);
	}

	public function test_skips_simple_history_frames() {
		$plugin = Helpers::get_calling_plugin(
			$this->backtrace(
				SIMPLE_HISTORY_PATH . 'loggers/class-role-capability-logger.php',
				ABSPATH . 'wp-includes/option.php',
				WP_PLUGIN_DIR . '/redirection/redirection.php'
			)
		);

		$this->assertSame( 'redirection', $plugin['slug'] );
	}

	public function test_single_file_plugin_slug_has_no_extension() {
		$plugin = Helpers::get_calling_plugin( $this->backtrace( WP_PLUGIN_DIR . '/hello.php' ) );

		$this->assertSame( 'hello', $plugin['slug'] );
		$this->assertSame( 'hello.php', $plugin['file'] );
	}

	public function test_finds_must_use_plugin() {
		$plugin = Helpers::get_calling_plugin( $this->backtrace( WPMU_PLUGIN_DIR . '/site-tweaks.php' ) );

		$this->assertSame( 'site-tweaks', $plugin['slug'] );
		$this->assertSame( 'mu-plugin', $plugin['type'] );
	}

	public function test_core_or_theme_only_is_null() {
		$plugin = Helpers::get_calling_plugin(
			$this->backtrace(
				ABSPATH . 'wp-includes/class-wp-role.php',
				get_theme_root() . '/twentytwentyfive/functions.php',
				ABSPATH . 'wp-admin/includes/upgrade.php'
			)
		);

		$this->assertNull( $plugin );
	}

	public function test_frames_without_file_are_skipped() {
		$plugin = Helpers::get_calling_plugin(
			[
				[ 'function' => '{closure}' ],
				[ 'file' => WP_PLUGIN_DIR . '/redirection/redirection.php' ],
			]
		);

		$this->assertSame( 'redirection', $plugin['slug'] );
	}

	public function test_symlinked_plugin_real_path_is_mapped() {
		global $wp_plugin_paths;

		$saved           = $wp_plugin_paths;
		$wp_plugin_paths = [ wp_normalize_path( WP_PLUGIN_DIR . '/linked-plugin' ) => '/Users/someone/src/linked-plugin' ];

		$plugin = Helpers::get_calling_plugin( $this->backtrace( '/Users/someone/src/linked-plugin/inc/setup.php' ) );

		$wp_plugin_paths = $saved;

		$this->assertSame( 'linked-plugin', $plugin['slug'] );
		$this->assertSame( 'linked-plugin/inc/setup.php', $plugin['file'] );
	}

	public function test_plugin_name_is_read_from_the_header() {
		$name = Helpers::get_calling_plugin_name(
			[
				'slug' => 'wp-crontrol',
				'type' => 'plugin',
				'file' => 'wp-crontrol/src/bootstrap.php',
			]
		);

		$this->assertSame( 'WP Crontrol', $name );
	}

	public function test_plugin_name_falls_back_to_slug() {
		$name = Helpers::get_calling_plugin_name(
			[
				'slug' => 'no-such-plugin',
				'type' => 'plugin',
				'file' => 'no-such-plugin/no-such-plugin.php',
			]
		);

		$this->assertSame( 'no-such-plugin', $name );
	}

	public function test_via_uses_the_event_plugin() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleOptionsLogger' );

		$via = $logger->get_via( (object) [ 'context' => [ '_via_plugin' => 'Wordfence Security' ] ] );

		$this->assertSame( 'Using plugin Wordfence Security', $via );
	}

	public function test_via_falls_back_to_logger_name_via() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'PluginWPCrontrolLogger' );

		$this->assertSame( 'Using plugin WP Crontrol', $logger->get_via( (object) [ 'context' => [] ] ) );
	}

	public function test_via_is_empty_without_either() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleOptionsLogger' );

		$this->assertSame( '', $logger->get_via( (object) [ 'context' => [] ] ) );
	}

	public function test_header_escapes_plugin_name() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleOptionsLogger' );

		$html = $logger->get_log_row_header_using_plugin_output(
			(object) [ 'context' => [ '_via_plugin' => '<img src=x onerror=alert(1)>' ] ]
		);

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( '&lt;img', $html );
	}
}
