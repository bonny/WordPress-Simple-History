<?php

use Simple_History\Changelog_Parser;

/**
 * The parser behind the "What's new" box. Runs on the real readme.txt too,
 * so a change to the changelog format breaks this test instead of the box.
 */
class ChangelogParserTest extends \Codeception\TestCase\WPTestCase {
	private const SAMPLE = <<<'TXT'
# Plugin

## Description

### 9.9.9 (Not a version)

Text before the changelog is ignored.

## Changelog

✨ Intro text.

### Unreleased

**Added**

-   Not released yet.

### 2.1.0 (October 2026)

Short summary, see the [table view](https://simple-history.com/features/table-view/) page.
[Screenshots and details in the release post](https://simple-history.com/2026/simple-history-2-1-0-released/)

**Added**

-   **Table view** for the event log.
-   A feature without a name.
-   Experimental — Something most users never see.

**Changed**

-   A change.

**Fixed**

-   Fix one.
-   Fix two.

**Security**

-   A security fix.

### 2.0.1

**Fixed**

-   Fix three.

### 2.0.0 (August 2026)

[Read more about all changes in the release post →](https://simple-history.com/2025/simple-history-premium-2-0-0-released-splunk-forwarding/).

**Added**

-   **Splunk forwarding**: send events to Splunk.
TXT;

	public function test_parses_versions_with_and_without_dates_and_skips_unreleased() {
		$versions = Changelog_Parser::parse( self::SAMPLE );

		$this->assertSame( [ '2.1.0', '2.0.1', '2.0.0' ], array_keys( $versions ) );
	}

	public function test_counts_sections_and_leaves_out_experimental_entries() {
		$version = Changelog_Parser::parse( self::SAMPLE )['2.1.0'];

		$this->assertCount( 2, $version['added'] );
		$this->assertSame( 2, $version['fixed'] );
		// Changed + Security.
		$this->assertSame( 2, $version['other'] );
	}

	public function test_reads_bold_names_and_release_links() {
		$versions = Changelog_Parser::parse( self::SAMPLE );

		$this->assertSame( 'Table view', $versions['2.1.0']['added'][0]['name'] );
		$this->assertSame( '', $versions['2.1.0']['added'][1]['name'] );
		$this->assertSame( 'https://simple-history.com/2026/simple-history-2-1-0-released/', $versions['2.1.0']['release_url'] );
		$this->assertSame( 'https://simple-history.com/2025/simple-history-premium-2-0-0-released-splunk-forwarding/', $versions['2.0.0']['release_url'] );
		$this->assertSame( '', $versions['2.0.1']['release_url'] );
	}

	public function test_summarize_sums_skipped_versions_and_picks_newest_release_link() {
		$versions = Changelog_Parser::parse( self::SAMPLE );

		$summary = Changelog_Parser::summarize( $versions, '1.9.0', '2.1.0' );

		$this->assertSame( 3, $summary['features'] );
		$this->assertSame( 3, $summary['fixes'] );
		$this->assertSame( 2, $summary['other'] );
		$this->assertSame( [ '2.1.0', '2.0.1', '2.0.0' ], $summary['versions'] );
		$this->assertSame( 'https://simple-history.com/2026/simple-history-2-1-0-released/', $summary['release_url'] );
	}

	public function test_summarize_counts_only_the_range() {
		$versions = Changelog_Parser::parse( self::SAMPLE );

		$summary = Changelog_Parser::summarize( $versions, '2.0.0', '2.0.1' );

		$this->assertSame( [ '2.0.1' ], $summary['versions'] );
		$this->assertSame( 0, $summary['features'] );
		$this->assertSame( 1, $summary['fixes'] );
		$this->assertSame( '', $summary['release_url'] );
	}

	public function test_text_without_changelog_gives_nothing() {
		$this->assertSame( [], Changelog_Parser::parse( "# Plugin\n\nNo changelog here." ) );
		$this->assertSame( [], Changelog_Parser::parse( null ) );
	}

	public function test_reads_common_variations_of_the_format() {
		$text = "## Changelog\r\n\r\n### 3.0.0\r\n\r\n#### Added\r\n\r\n* One.\r\n- Two.\r\n\r\n**fixed:**\r\n\r\n-   Three.\r\n\r\nChanged:\r\n\r\n-   Four.\r\n";

		$version = Changelog_Parser::parse( $text )['3.0.0'];

		$this->assertCount( 2, $version['added'] );
		$this->assertSame( 'One.', $version['added'][0]['text'] );
		$this->assertSame( 1, $version['fixed'] );
		$this->assertSame( 1, $version['other'] );
	}

	/**
	 * A readme written some other way gives fewer or no versions, never an
	 * error: the box then shows nothing for that plugin.
	 */
	public function test_unexpected_formats_give_nothing_and_no_error() {
		$inputs = [
			'',
			'## Changelog',
			"== Changelog ==\n\n= 1.0.0 =\n* Added a thing.",
			"## Changelog\n\n### Version five\n\n**Added**\n\n-   Thing.",
			"## Changelog\n\n### 1.0.0\n\nJust prose, no sections.\n\n-   A bullet with no section above it.",
			"## Changelog\n\n### 1.0.0\n\n**Added**\n\n-",
			str_repeat( "## Changelog\n### 1.0.0\n**Added**\n-   x\n", 2000 ),
			"\x00\xff\xfe## Changelog\n### 1.0.0 \xc3\x28\n**Added**\n-   \xc3\x28",
		];

		foreach ( $inputs as $index => $input ) {
			$versions = Changelog_Parser::parse( $input );
			$summary  = Changelog_Parser::summarize( $versions, '0.1.0', '9.0.0' );

			$this->assertIsArray( $versions, "Input {$index}" );
			$this->assertIsInt( $summary['features'], "Input {$index}" );
		}

		$this->assertSame( [], Changelog_Parser::parse( $inputs[2] ), 'WordPress.org classic format is not read' );
		$this->assertSame( [], Changelog_Parser::parse( $inputs[3] ), 'Headings without a version number are not read' );
		$this->assertSame( 0, Changelog_Parser::summarize( Changelog_Parser::parse( $inputs[4] ), '0.1.0', '9.0.0' )['features'] );
	}

	/**
	 * The released versions in the real readme must parse, and the newest
	 * one must link its release post: the box links it.
	 */
	public function test_real_readme_parses_and_newest_version_links_release_post() {
		$versions = Changelog_Parser::parse( file_get_contents( SIMPLE_HISTORY_PATH . 'readme.txt' ) );

		$this->assertNotEmpty( $versions );

		$newest = array_key_first( $versions );

		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', (string) $newest );
		$this->assertStringStartsWith( 'https://simple-history.com/', $versions[ $newest ]['release_url'] );
		$this->assertGreaterThan( 0, count( $versions[ $newest ]['added'] ) + $versions[ $newest ]['fixed'] + $versions[ $newest ]['other'] );
	}

	/**
	 * Premium writes its changelog the same way, and the box reads it too.
	 */
	public function test_real_premium_readme_parses() {
		$readme = WP_PLUGIN_DIR . '/simple-history-premium/readme.txt';

		if ( ! is_readable( $readme ) ) {
			$this->markTestSkipped( 'Premium is not installed in the test site.' );
		}

		$versions = Changelog_Parser::parse( file_get_contents( $readme ) );
		$newest   = array_key_first( $versions );

		$this->assertNotEmpty( $versions );
		$this->assertGreaterThan( 0, count( $versions[ $newest ]['added'] ) + $versions[ $newest ]['fixed'] + $versions[ $newest ]['other'] );
	}
}
