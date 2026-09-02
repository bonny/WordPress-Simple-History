<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_context;

/**
 * Issue 295: a site icon change is logged as a bare attachment ID. The event
 * details should show the icon itself, and survive the attachment being
 * deleted later.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit OptionsLoggerSiteIconTest
 */
class OptionsLoggerSiteIconTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var int */
	private $attachment_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh = Simple_History::get_instance();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->attachment_id = $this->factory->attachment->create_upload_object( dirname( __DIR__ ) . '/_data/Image 1.jpg' );
		$this->assertGreaterThan( 0, $this->attachment_id );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		delete_option( 'site_icon' );
		wp_delete_attachment( $this->attachment_id, true );
		parent::tearDown();
	}

	public function test_site_icon_change_captures_icon_url_and_filename() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		update_option( 'site_icon', $this->attachment_id );

		$context = get_latest_context();

		$this->assert_context_has( $context, 'option', 'site_icon' );
		$this->assert_context_has( $context, 'new_value', (string) $this->attachment_id );
		$this->assert_context_has( $context, 'new_site_icon_url', wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ) );
		$this->assert_context_has( $context, 'new_site_icon_filename', wp_basename( get_attached_file( $this->attachment_id ) ) );
	}

	public function test_details_render_new_icon_as_image_and_no_previous_icon_as_none() {
		$row = $this->make_row(
			array(
				'_message_key'      => 'option_updated',
				'option'            => 'site_icon',
				'option_page'       => 'general',
				'new_value'         => (string) $this->attachment_id,
				'old_value'         => '0',
				'new_site_icon_url' => wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ),
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringContainsString( '<img', $html );
		$this->assertStringContainsString( esc_url( wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ) ), $html );
		$this->assertMatchesRegularExpression(
			'#class="diff-deletedline">\s*None\s*</td>#s',
			$html,
			'"No previous icon" must be spelled out as "None" in the deleted cell'
		);
		$this->assertStringNotContainsString( '>0<', $html, 'The raw "0" must not be shown for "no icon"' );
	}

	public function test_details_show_previous_icon_in_deleted_cell_and_new_icon_in_added_cell() {
		$old_attachment_id = $this->factory->attachment->create_upload_object( dirname( __DIR__ ) . '/_data/Image 2.jpg' );

		$row = $this->make_row(
			array(
				'_message_key'      => 'option_updated',
				'option'            => 'site_icon',
				'option_page'       => 'general',
				'new_value'         => (string) $this->attachment_id,
				'old_value'         => (string) $old_attachment_id,
				'new_site_icon_url' => wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ),
				'old_site_icon_url' => wp_get_attachment_image_url( $old_attachment_id, 'thumbnail' ),
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		wp_delete_attachment( $old_attachment_id, true );

		$this->assertMatchesRegularExpression(
			'#class="diff-deletedline".*?<img[^>]*Image-2.*?class="diff-addedline".*?<img[^>]*Image-1#s',
			$html,
			'Previous icon must be in the red deleted cell, new icon in the green added cell'
		);
	}

	public function test_details_show_icons_at_the_small_thumbnail_size() {
		$row = $this->make_row(
			array(
				'_message_key'      => 'option_updated',
				'option'            => 'site_icon',
				'option_page'       => 'general',
				'new_value'         => (string) $this->attachment_id,
				'old_value'         => '0',
				'new_site_icon_url' => wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ),
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringContainsString( 'SimpleHistoryLogitemThumbnail--small', $html, 'A favicon is seen at 16-64px, so the preview should be small too' );
	}

	public function test_details_show_none_in_added_cell_when_icon_is_removed() {
		$row = $this->make_row(
			array(
				'_message_key'      => 'option_updated',
				'option'            => 'site_icon',
				'option_page'       => 'general',
				'new_value'         => '0',
				'old_value'         => (string) $this->attachment_id,
				'old_site_icon_url' => wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ),
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertMatchesRegularExpression(
			'#class="diff-deletedline".*?<img.*?class="diff-addedline">\s*None\s*</td>#s',
			$html
		);
	}

	public function test_details_fall_back_to_filename_when_attachment_is_gone() {
		$row = $this->make_row(
			array(
				'_message_key'           => 'option_updated',
				'option'                 => 'site_icon',
				'option_page'            => 'general',
				'new_value'              => '999999',
				'old_value'              => '0',
				'new_site_icon_url'      => 'https://example.com/wp-content/uploads/deleted-icon.png',
				'new_site_icon_filename' => 'deleted-icon.png',
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'deleted-icon.png', $html );
	}

	public function test_details_fall_back_to_id_for_old_events_without_captured_context() {
		$row = $this->make_row(
			array(
				'_message_key' => 'option_updated',
				'option'      => 'site_icon',
				'option_page' => 'general',
				'new_value'   => '999999',
				'old_value'   => '0',
			)
		);

		$html = (string) $this->sh->get_log_row_details_output( $row );

		$this->assertStringContainsString( '999999', $html );
	}

	public function test_details_json_output_names_the_icon() {
		$row = $this->make_row(
			array(
				'_message_key'      => 'option_updated',
				'option'            => 'site_icon',
				'option_page'       => 'general',
				'new_value'         => (string) $this->attachment_id,
				'old_value'         => '0',
				'new_site_icon_url' => wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ),
			)
		);

		$output = $this->sh->get_log_row_details_output( $row );
		$json   = $output->to_json();

		$this->assertNotEmpty( $json );

		$encoded = wp_json_encode( $json );
		$this->assertStringContainsString( 'Site Icon', $encoded );
		$this->assertStringContainsString( wp_get_attachment_image_url( $this->attachment_id, 'thumbnail' ), stripslashes( $encoded ) );
	}

	private function make_row( array $context ): object {
		$row          = new stdClass();
		$row->logger  = 'SimpleOptionsLogger';
		$row->context = $context;

		return $row;
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}
}
