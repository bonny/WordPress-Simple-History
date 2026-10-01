<?php

use Simple_History\Loggers\Core_Files_Logger;

/**
 * Test that the core files check accepts official files from other locales.
 *
 * Checksum requests to api.wordpress.org are answered by a pre_http_request
 * filter, so the tests control which locale has which hash.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit CoreFilesLoggerLocaleTest
 */
class CoreFilesLoggerLocaleTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Fake checksums per locale, as file => hash.
	 *
	 * @var array<string, array<string, string>>
	 */
	private $checksums_by_locale = [];

	/**
	 * Locales that checksums were requested for, in order.
	 *
	 * @var string[]
	 */
	private $requested_locales = [];

	/**
	 * Site locale returned by the locale filter.
	 *
	 * @var string
	 */
	private $site_locale = 'en_US';

	/**
	 * Hash of a real core file, used as the "official" hash where it should match.
	 *
	 * @var string
	 */
	private $real_hash;

	public function setUp(): void {
		parent::setUp();

		$this->real_hash = md5_file( ABSPATH . 'index.php' );

		add_filter( 'pre_http_request', [ $this, 'fake_checksums_response' ], 10, 3 );
		add_filter( 'locale', [ $this, 'get_site_locale' ] );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'fake_checksums_response' ], 10 );
		remove_filter( 'locale', [ $this, 'get_site_locale' ] );
		unset( $GLOBALS['wp_local_package'] );

		parent::tearDown();
	}

	/**
	 * Answer checksum API requests from $checksums_by_locale.
	 *
	 * @param false|array $response Response.
	 * @param array       $args     Request args.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public function fake_checksums_response( $response, $args, $url ) {
		if ( strpos( $url, 'api.wordpress.org/core/checksums/' ) === false ) {
			return $response;
		}

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$locale                    = $query['locale'] ?? '';
		$this->requested_locales[] = $locale;

		if ( ! isset( $this->checksums_by_locale[ $locale ] ) ) {
			return [
				'headers'  => [],
				'body'     => '{"checksums":false}',
				'response' => [ 'code' => 200 ],
				'cookies'  => [],
			];
		}

		return [
			'headers'  => [],
			'body'     => wp_json_encode( [ 'checksums' => $this->checksums_by_locale[ $locale ] ] ),
			'response' => [ 'code' => 200 ],
			'cookies'  => [],
		];
	}

	/**
	 * @return string
	 */
	public function get_site_locale() {
		return $this->site_locale;
	}

	public function test_locales_start_with_package_then_site_then_en_us() {
		$GLOBALS['wp_local_package'] = 'de_AT';
		$this->site_locale           = 'de_DE';

		$this->assertSame( [ 'de_AT', 'de_DE', 'en_US' ], Core_Files_Logger::get_checksum_locales() );
	}

	public function test_locales_without_package_fall_back_to_en_us_once() {
		$this->site_locale = 'en_US';

		$this->assertSame( [ 'en_US' ], Core_Files_Logger::get_checksum_locales() );
	}

	public function test_matching_file_in_package_locale_passes_without_extra_requests() {
		$this->site_locale                    = 'de_DE';
		$this->checksums_by_locale['en_US'] = [ 'index.php' => $this->real_hash ];

		$result = Core_Files_Logger::run_integrity_check();

		$this->assertSame( [], $result['modified_files'] );
		$this->assertSame( 1, $result['files_checked'] );
		$this->assertSame( [ 'en_US' ], $this->requested_locales );
	}

	public function test_file_from_site_locale_package_passes() {
		// The reported case: English package, German site, German sample file.
		$this->site_locale                    = 'de_DE';
		$this->checksums_by_locale['en_US'] = [ 'index.php' => 'd53c3d00000000000000000000000000' ];
		$this->checksums_by_locale['de_DE'] = [ 'index.php' => $this->real_hash ];

		$result = Core_Files_Logger::run_integrity_check();

		$this->assertSame( [], $result['modified_files'] );
		$this->assertSame( [ 'en_US', 'de_DE' ], $result['checksum_locales'] );
	}

	public function test_file_from_en_us_passes_on_localized_package() {
		$GLOBALS['wp_local_package']          = 'de_DE';
		$this->site_locale                    = 'de_DE';
		$this->checksums_by_locale['de_DE'] = [ 'index.php' => '28268c00000000000000000000000000' ];
		$this->checksums_by_locale['en_US'] = [ 'index.php' => $this->real_hash ];

		$result = Core_Files_Logger::run_integrity_check();

		$this->assertSame( [], $result['modified_files'] );
	}

	public function test_file_matching_no_locale_is_modified() {
		$this->site_locale                    = 'de_DE';
		$this->checksums_by_locale['en_US'] = [ 'index.php' => 'aaaa0000000000000000000000000000' ];
		$this->checksums_by_locale['de_DE'] = [ 'index.php' => 'bbbb0000000000000000000000000000' ];

		$result = Core_Files_Logger::run_integrity_check();

		$this->assertCount( 1, $result['modified_files'] );
		$this->assertSame( 'index.php', $result['modified_files'][0]['file'] );
		$this->assertSame( 'modified', $result['modified_files'][0]['issue'] );
		$this->assertSame( 'aaaa0000000000000000000000000000', $result['modified_files'][0]['expected_hash'] );
		$this->assertSame( $this->real_hash, $result['modified_files'][0]['actual_hash'] );
	}

	public function test_other_locale_checksums_are_fetched_once() {
		$this->site_locale                    = 'de_DE';
		$this->checksums_by_locale['en_US'] = [
			'index.php'       => 'aaaa0000000000000000000000000000',
			'wp-settings.php' => 'aaaa0000000000000000000000000000',
		];
		$this->checksums_by_locale['de_DE'] = [];

		Core_Files_Logger::run_integrity_check();

		$this->assertSame( [ 'en_US', 'de_DE' ], $this->requested_locales );
	}

	public function test_locale_whose_checksums_fail_is_not_listed() {
		$this->site_locale                  = 'de_DE';
		$this->checksums_by_locale['en_US'] = [ 'index.php' => 'aaaa0000000000000000000000000000' ];

		$result = Core_Files_Logger::run_integrity_check();

		$this->assertCount( 1, $result['modified_files'] );
		$this->assertSame( [ 'en_US' ], $result['checksum_locales'] );
		$this->assertSame( [ 'en_US', 'de_DE' ], $this->requested_locales );
	}

	public function test_failed_package_checksums_is_an_error() {
		$result = Core_Files_Logger::run_integrity_check();

		$this->assertWPError( $result );
	}
}
