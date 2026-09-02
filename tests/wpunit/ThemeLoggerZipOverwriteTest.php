<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Issue 281: updating a theme by uploading a newer zip ("Replace installed
 * with uploaded") goes through Theme_Upgrader::install() with
 * overwrite_package, so upgrader_process_complete fires with action
 * "install", not "update". That path must log theme_updated with the
 * previous version, not nothing.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ThemeLoggerZipOverwriteTest
 */
class ThemeLoggerZipOverwriteTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG = 'sh-probe-theme';

	/** @var string */
	private $theme_dir;

	/** @var string */
	private $zip_file;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// The upgrader asks for filesystem credentials; answer "direct" as a real
		// install with writable files would.
		add_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );

		// The upgrader triggers plugin/theme update checks against wordpress.org.
		// Answer them locally so the test needs no network and logs no warnings.
		add_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ), 10, 3 );

		$this->theme_dir = trailingslashit( get_theme_root() ) . self::SLUG;
		$this->zip_file  = get_temp_dir() . self::SLUG . '-2.0.0.zip';

		$this->remove_theme_dir();
		$this->write_theme_zip( '2.0.0' );

		delete_option( 'SimpleThemeLogger_theme_info_before_update' );
	}

	public function tearDown(): void {
		remove_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );
		remove_filter( 'pre_http_request', array( $this, 'filter_pre_http_request' ) );
		$this->remove_theme_dir();

		if ( file_exists( $this->zip_file ) ) {
			unlink( $this->zip_file );
		}

		wp_clean_themes_cache();
		delete_option( 'SimpleThemeLogger_theme_info_before_update' );

		parent::tearDown();
	}

	public function test_overwriting_an_installed_theme_with_a_zip_logs_theme_updated() {
		$this->write_theme_dir( '1.0.0' );
		wp_clean_themes_cache();
		$this->assertSame( '1.0.0', wp_get_theme( self::SLUG )->get( 'Version' ) );

		$result = $this->install_zip( true );
		$this->assertTrue( $result, 'Overwrite install should succeed' );

		$row = get_latest_row();
		$this->assertSame( 'SimpleThemeLogger', $row['logger'] );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'theme_updated', $context['_message_key'] );
		$this->assertSame( 'SH Probe Theme', $context['theme_name'] );
		$this->assertSame( '2.0.0', $context['theme_version'] );
		$this->assertSame( '1.0.0', $context['theme_prev_version'] );
	}

	public function test_overwriting_with_an_older_zip_logs_theme_downgraded() {
		$this->write_theme_dir( '3.0.0' );
		wp_clean_themes_cache();

		$this->install_zip( true );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'theme_downgraded', $context['_message_key'] );
		$this->assertSame( '2.0.0', $context['theme_version'] );
		$this->assertSame( '3.0.0', $context['theme_prev_version'] );
	}

	public function test_overwriting_with_the_same_version_logs_theme_reinstalled() {
		$this->write_theme_dir( '2.0.0' );
		wp_clean_themes_cache();

		$this->install_zip( true );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'theme_reinstalled', $context['_message_key'] );
		$this->assertSame( '2.0.0', $context['theme_version'] );
		$this->assertSame( '2.0.0', $context['theme_prev_version'] );
	}

	public function test_installing_a_new_theme_from_a_zip_still_logs_theme_installed() {
		wp_clean_themes_cache();
		$this->assertFalse( wp_get_theme( self::SLUG )->exists() );

		$result = $this->install_zip( false );
		$this->assertTrue( $result, 'Fresh install should succeed' );

		$context = $this->context_to_assoc( get_latest_context() );
		$this->assertSame( 'theme_installed', $context['_message_key'] );
		$this->assertSame( '2.0.0', $context['theme_version'] );
		$this->assertArrayNotHasKey( 'theme_prev_version', $context );
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

	private function install_zip( bool $overwrite ) {
		$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $this->zip_file, array( 'overwrite_package' => $overwrite ) );

		if ( $result !== true ) {
			$this->fail( 'Install failed: ' . implode( ' | ', $upgrader->skin->get_upgrade_messages() ) . ' result=' . wp_json_encode( $result ) );
		}

		return $result;
	}

	private function theme_files( string $version ): array {
		return array(
			'style.css' => "/*\nTheme Name: SH Probe Theme\nVersion: {$version}\nAuthor: Simple History tests\n*/\n",
			'index.php' => "<?php\n// Probe theme for Simple History tests.\n",
		);
	}

	private function write_theme_dir( string $version ): void {
		wp_mkdir_p( $this->theme_dir );

		foreach ( $this->theme_files( $version ) as $name => $content ) {
			file_put_contents( $this->theme_dir . '/' . $name, $content );
		}
	}

	private function write_theme_zip( string $version ): void {
		$zip = new ZipArchive();
		$zip->open( $this->zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE );

		foreach ( $this->theme_files( $version ) as $name => $content ) {
			$zip->addFromString( self::SLUG . '/' . $name, $content );
		}

		$zip->close();
	}

	private function remove_theme_dir(): void {
		if ( ! is_dir( $this->theme_dir ) ) {
			return;
		}

		foreach ( glob( $this->theme_dir . '/*' ) as $file ) {
			unlink( $file );
		}

		rmdir( $this->theme_dir );
	}

	private function context_to_assoc( array $context ): array {
		$assoc = array();

		foreach ( $context as $row ) {
			$assoc[ $row['key'] ] = $row['value'];
		}

		return $assoc;
	}
}
