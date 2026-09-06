<?php

use Simple_History\Details_Text;
use Simple_History\Event;
use Simple_History\Simple_History;

/**
 * Details HTML → plain text, as used by Copy as text, alerts and the abilities API.
 *
 * Every fixture runs through both converters: WP_HTML_Processor for current
 * WordPress and the regex fallback for installs where the processor is missing
 * or refuses the markup. They must agree.
 */
class DetailsTextTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @return array<string, array{string, string}>
	 */
	public function fixtures() {
		$diff = '<div class="SimpleHistory__diff__contents" tabindex="0"><div class="SimpleHistory__diff__contentsInner">'
			. '<table class="diff SimpleHistory__diff"><col class="content diffsplit left" /><col class="content diffsplit middle" /><col class="content diffsplit right" /><tbody>'
			. '<tr><td class="diff-deletedline"><span aria-hidden="true" class="dashicons dashicons-minus"></span><span class="screen-reader-text">Deleted: </span>Hello world</td><td></td><td class="diff-addedline"><span class="screen-reader-text">Added: </span>Hello world, again</td></tr>'
			. '<tr><td class="diff-context">Same line</td><td></td><td class="diff-context">Same line</td></tr>'
			. '<tr><td class="diff-deletedline"></td><td></td><td class="diff-addedline">Third paragraph added</td></tr>'
			. '</tbody></table></div></div>';

		return [
			'key-value pairs with ins/del in either order' => [
				'<dl class="SimpleHistoryLogitem__keyValueTable"><dt>First name</dt><dd><ins class="a">Pär</ins> <del class="r">Par</del></dd>'
				. '<dt>Last name</dt><dd><del>T</del> <ins>Thernström</ins></dd><dt>Nickname</dt><dd><span class="a">bonny</span></dd></dl>',
				"First name: Par → Pär\nLast name: T → Thernström\nNickname: bonny",
			],
			'a value of "0" is a value'                    => [
				'<dl><dt>Number of decimals</dt><dd><ins>0</ins> <del>2</del></dd><dt>Count</dt><dd>0</dd></dl>',
				"Number of decimals: 2 → 0\nCount: 0",
			],
			'empty key or empty value'                     => [
				'<dl><dt></dt><dd>only value</dd><dt>Only key</dt><dd></dd><dt> </dt><dd> </dd></dl>',
				"only value\nOnly key",
			],
			'screen reader title does not run into keys'   => [
				'<h4 class="screen-reader-text">Changed items</h4><dl><dt>Show on dashboard</dt><dd><ins>On</ins> <del>Off</del></dd></dl>',
				'Show on dashboard: Off → On',
			],
			'diff table inside a value'                    => [
				'<dl><dt>Content</dt><dd>' . $diff . '</dd><dt>Status</dt><dd><ins>publish</ins> <del></del></dd></dl>',
				"Content: Hello world → Hello world, again\n→ Third paragraph added\nStatus: → publish",
			],
			'legacy table rows from a third-party logger'  => [
				'<table class="SimpleHistoryLogitem__keyValueTable"><tbody><tr><td>Role added</td><td>editor</td></tr><tr><td>Website</td><td><a href="https://x.se">https://x.se</a></td></tr></tbody></table>',
				"Role added: editor\nWebsite: https://x.se",
			],
			'plain html'                                   => [
				'<p>Some <strong>bold</strong> text.<br>Second line</p><ul><li>one</li><li>two</li></ul>',
				"Some bold text.\nSecond line\none\ntwo",
			],
			'lone ins or del'                              => [
				'<dl><dt>Role added</dt><dd><span class="addedThing">Editor</span></dd><dt>Note</dt><dd><ins>Added only</ins></dd><dt>Bio</dt><dd><del>Gone</del></dd></dl>',
				"Role added: Editor\nNote: Added only\nBio: Gone",
			],
			'entities are decoded'                         => [
				'<dl><dt>Title</dt><dd>Fish &amp; Chips &lt;3</dd></dl>',
				'Title: Fish & Chips <3',
			],
			'empty input'                                  => [ '', '' ],
		];
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_from_html( $html, $expected ) {
		$this->assertSame( $expected, Details_Text::from_html( $html ) );
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_html_api_converter( $html, $expected ) {
		if ( ! class_exists( 'WP_HTML_Processor' ) ) {
			$this->markTestSkipped( 'WP_HTML_Processor not available in this WordPress.' );
		}

		$text = Details_Text::convert_with_html_api( $html );

		if ( $html === '' ) {
			$this->assertSame( '', (string) $text );
			return;
		}

		$this->assertNotNull( $text, 'The processor should handle the markup the loggers produce.' );
		$this->assertSame( $expected, $this->tidy( $text ) );
	}

	/**
	 * @dataProvider fixtures
	 */
	public function test_regex_converter( $html, $expected ) {
		$this->assertSame( $expected, $this->tidy( Details_Text::convert_with_regex( $html ) ) );
	}

	public function test_event_get_details_text_uses_logger_details() {
		// Events are only readable for users allowed to see the logger.
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		$html = '<dl class="SimpleHistoryLogitem__keyValueTable"><dt>Role added</dt><dd>editor</dd><dt>Posts per page</dt><dd><ins>0</ins> <del>10</del></dd></dl>';

		$filter = function ( $output, $row ) use ( $html ) {
			return $html;
		};

		add_filter( 'simple_history/row_details_output', $filter, 10, 2 );

		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleLogger' );
		$logger->info( 'Details text test' );

		$event = Event::get( (int) $logger->last_insert_id );
		$text  = $event->get_details_text();

		remove_filter( 'simple_history/row_details_output', $filter, 10 );

		$this->assertSame( "Role added: editor\nPosts per page: 10 → 0", $text );
	}

	/**
	 * Same whitespace clean-up as Details_Text::from_html(), so the two
	 * converters can be compared line by line.
	 */
	private function tidy( $text ) {
		$lines = array_map( 'trim', explode( "\n", preg_replace( '/[ \t]+/', ' ', (string) $text ) ) );

		return implode( "\n", array_filter( $lines, fn( $line ) => $line !== '' ) );
	}
}
