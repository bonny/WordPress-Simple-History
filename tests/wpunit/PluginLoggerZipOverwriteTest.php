<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Issue 281 (plugin half): replacing an installed plugin by uploading a
 * newer zip runs Plugin_Upgrader::install() with overwrite_package, so the
 * upgrader reports an install. It must be logged as a plugin update with
 * the previous version.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginLoggerZipOverwriteTest
 */
class PluginLoggerZipOverwriteTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG = 'sh-probe-plugin';

	/** @var string */
	private $plugin_dir;

	/** @var string */
	private $zip_file;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		add_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );

		$this->plugin_dir = WP_PLUGIN_DIR . '/' . self::SLUG;
		$this->zip_file   = get_temp_dir() . self::SLUG . '-2.0.0.zip';

		$this->remove_plugin_dir();
		$this->write_plugin_zip( '2.0.0' );

		delete_option( 'SimplePluginLogger_plugin_info_before_update' );
	}

	public function tearDown(): void {
		remove_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );
		remove_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ) );
		$this->remove_plugin_dir();

		if ( file_exists( $this->zip_file ) ) {
			unlink( $this->zip_file );
		}

		wp_clean_plugins_cache( false );
		delete_option( 'SimplePluginLogger_plugin_info_before_update' );

		parent::tearDown();
	}

	public function test_overwriting_an_installed_plugin_with_a_zip_logs_plugin_updated() {
		$this->write_plugin_dir( '1.0.0' );
		wp_clean_plugins_cache( false );
		$this->assertArrayHasKey( self::SLUG . '/' . self::SLUG . '.php', get_plugins() );

		$this->install_zip( true );

		$row = get_latest_row();
		$this->assertSame( 'SimplePluginLogger', $row['logger'] );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'plugin_updated', $context['_message_key'] );
		$this->assertSame( 'SH Probe Plugin', $context['plugin_name'] );
		$this->assertSame( '2.0.0', $context['plugin_version'] );
		$this->assertSame( '1.0.0', $context['plugin_prev_version'] );
	}

	public function test_installing_a_new_plugin_from_a_zip_still_logs_plugin_installed() {
		wp_clean_plugins_cache( false );
		$this->assertArrayNotHasKey( self::SLUG . '/' . self::SLUG . '.php', get_plugins() );

		$this->install_zip( false );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'plugin_installed', $context['_message_key'] );
		$this->assertSame( '2.0.0', $context['plugin_version'] );
		$this->assertArrayNotHasKey( 'plugin_prev_version', $context );
	}

	public function filter_filesystem_method() {
		return 'direct';
	}

	public function filter_pre_http_request( $preempt, $args, $url ) {
		if ( strpos( $url, 'api.wordpress.org' ) === false ) {
			return $preempt;
		}

		return array(
			'headers'  => array(),
			'body'     => '{"plugins":[],"themes":[],"no_update":[],"translations":[]}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	private function install_zip( bool $overwrite ): void {
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $this->zip_file, array( 'overwrite_package' => $overwrite ) );

		if ( $result !== true ) {
			$this->fail( 'Install failed: ' . implode( ' | ', $upgrader->skin->get_upgrade_messages() ) . ' result=' . wp_json_encode( $result ) );
		}
	}

	private function plugin_files( string $version ): array {
		return array(
			self::SLUG . '.php' => "<?php\n/*\nPlugin Name: SH Probe Plugin\nVersion: {$version}\nAuthor: Simple History tests\n*/\n",
		);
	}

	private function write_plugin_dir( string $version ): void {
		wp_mkdir_p( $this->plugin_dir );

		foreach ( $this->plugin_files( $version ) as $name => $content ) {
			file_put_contents( $this->plugin_dir . '/' . $name, $content );
		}
	}

	private function write_plugin_zip( string $version ): void {
		$zip = new ZipArchive();
		$zip->open( $this->zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		foreach ( $this->plugin_files( $version ) as $name => $content ) {
			$zip->addFromString( self::SLUG . '/' . $name, $content );
		}

		$zip->close();
	}

	private function remove_plugin_dir(): void {
		if ( ! is_dir( $this->plugin_dir ) ) {
			return;
		}

		foreach ( glob( $this->plugin_dir . '/*' ) as $file ) {
			unlink( $file );
		}

		rmdir( $this->plugin_dir );
	}

	private function context_to_assoc( array $context ): array {
		$assoc = array();

		foreach ( $context as $row ) {
			$assoc[ $row['key'] ] = $row['value'];
		}

		return $assoc;
	}
}
