<?php

use Simple_History\Simple_History;
use Simple_History\Services\Status_Box_Service;

/**
 * The header feature-discovery bar.
 *
 * @coversDefaultClass Simple_History\Services\Status_Box_Service
 */
class StatusBoxServiceTest extends \Codeception\TestCase\WPTestCase {
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Render the bar and return its markup.
	 *
	 * @return string
	 */
	private function render() {
		$service = new Status_Box_Service( Simple_History::get_instance() );

		ob_start();
		$service->output_status_box();

		return ob_get_clean();
	}

	/**
	 * Items that render nothing must not leave an empty list item behind.
	 *
	 * The emptiness check lives in render_item(), which runs after the <li>
	 * wrapper has already been printed — so on its own it produced an empty but
	 * still styled bullet. Consumers of the filter do return such items: the
	 * premium add-on replaces the alerts entry wholesale.
	 */
	function test_item_without_text_does_not_render_an_empty_list_item() {
		$add_textless_item = function ( $items ) {
			$items[] = [
				'id'   => 'textless',
				'icon' => 'dashicons-bell',
			];

			return $items;
		};

		add_filter( 'simple_history/header_status/items', $add_textless_item );
		$html = $this->render();
		remove_filter( 'simple_history/header_status/items', $add_textless_item );

		$this->assertNotEmpty( $html, 'Sanity check: the bar should render, otherwise this proves nothing' );
		$this->assertStringNotContainsString(
			'<li class="sh-HeaderStatus-item">' . "\n\t\t\t\t\t\t\n\t\t\t\t\t</li>",
			$html,
			'An item with no text should not leave an empty list item'
		);

		// Counting is the robust form: one wrapper per item that actually rendered.
		$list_items = substr_count( $html, '<li class="sh-HeaderStatus-item">' );
		$inner      = substr_count( $html, 'sh-HeaderStatus-item-inner' );

		$this->assertSame( $inner, $list_items, 'Every list item should contain a rendered item' );
	}

	/**
	 * A filter that removes every item hides the bar rather than rendering an
	 * empty list.
	 */
	function test_bar_is_not_rendered_when_no_item_has_text() {
		$strip_all_text = function () {
			return [
				[
					'id'   => 'textless',
					'icon' => 'dashicons-bell',
				],
			];
		};

		add_filter( 'simple_history/header_status/items', $strip_all_text );
		$html = $this->render();
		remove_filter( 'simple_history/header_status/items', $strip_all_text );

		$this->assertSame( '', trim( $html ), 'Nothing to show means nothing rendered, not an empty list' );
	}

	/**
	 * Ordinary items are unaffected.
	 */
	function test_items_with_text_still_render() {
		$html = $this->render();

		$this->assertStringContainsString( 'sh-HeaderStatus-zone', $html );
		$this->assertGreaterThan(
			0,
			substr_count( $html, 'sh-HeaderStatus-item-inner' ),
			'The default items should still render'
		);
	}

	/**
	 * Viewing the log and viewing the settings are separate capabilities, so
	 * the gear must not be offered to someone the settings page will reject.
	 */
	function test_header_settings_link_hidden_from_users_without_settings_capability() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$this->assertTrue(
			current_user_can( \Simple_History\Helpers::get_view_history_capability() ),
			'Precondition: editors can view the log.'
		);
		$this->assertFalse(
			current_user_can( \Simple_History\Helpers::get_view_settings_capability() ),
			'Precondition: editors cannot view the settings.'
		);

		$this->assertSame(
			'',
			\Simple_History\Helpers::get_header_settings_link(),
			'Editors must not be offered a link to a page that rejects them.'
		);
	}

	/**
	 * The gate scopes the link rather than removing it: administrators keep it.
	 */
	function test_header_settings_link_still_shown_to_administrators() {
		$html = \Simple_History\Helpers::get_header_settings_link();

		$this->assertStringContainsString( 'sh-PageHeader-settingsIcon', $html );
	}
}
