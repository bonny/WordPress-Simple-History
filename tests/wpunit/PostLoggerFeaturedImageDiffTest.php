<?php

require_once 'functions.php';

use Simple_History\Simple_History;

/**
 * The featured image row on post_updated events: previous image in the red
 * deleted cell, new image in the green added cell, "None" when a side is
 * empty, and no leaked raw thumb_id / thumb_title rows above it.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerFeaturedImageDiffTest
 */
class PostLoggerFeaturedImageDiffTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var int */
	private $old_attachment_id;

	/** @var int */
	private $new_attachment_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh = Simple_History::get_instance();

		$this->old_attachment_id = $this->factory->attachment->create_upload_object( dirname( __DIR__ ) . '/_data/Image 1.jpg' );
		$this->new_attachment_id = $this->factory->attachment->create_upload_object( dirname( __DIR__ ) . '/_data/Image 2.jpg' );
	}

	public function tearDown(): void {
		wp_delete_attachment( $this->old_attachment_id, true );
		wp_delete_attachment( $this->new_attachment_id, true );
		parent::tearDown();
	}

	public function test_changed_featured_image_shows_previous_in_deleted_cell_and_new_in_added_cell() {
		$html = $this->render(
			array(
				'post_prev_thumb_id'    => (string) $this->old_attachment_id,
				'post_prev_thumb_title' => 'Old image',
				'post_new_thumb_id'     => (string) $this->new_attachment_id,
				'post_new_thumb_title'  => 'New image',
			)
		);

		$this->assertMatchesRegularExpression(
			'#Featured image.*?class="diff-deletedline".*?Old image.*?<img[^>]*Image-1.*?class="diff-addedline".*?New image.*?<img[^>]*Image-2#s',
			$html
		);
	}

	public function test_thumb_id_and_thumb_title_are_not_rendered_as_separate_rows() {
		$html = $this->render(
			array(
				'post_prev_thumb_id'    => (string) $this->old_attachment_id,
				'post_prev_thumb_title' => 'Old image',
				'post_new_thumb_id'     => (string) $this->new_attachment_id,
				'post_new_thumb_title'  => 'New image',
			)
		);

		$this->assertStringNotContainsString( '<td>thumb_id</td>', $html );
		$this->assertStringNotContainsString( '<td>thumb_title</td>', $html );
		// Outer rows start with a bare <td>; the inner diff table's cells carry a class.
		$this->assertSame( 1, preg_match_all( '#<tr>\s*<td>#', $html ), 'Only the featured image row should be rendered' );
	}

	public function test_added_featured_image_shows_none_in_deleted_cell() {
		$html = $this->render(
			array(
				'post_new_thumb_id'    => (string) $this->new_attachment_id,
				'post_new_thumb_title' => 'New image',
			)
		);

		$this->assertMatchesRegularExpression( '#class="diff-deletedline">\s*None\s*</td>#s', $html );
		$this->assertStringContainsString( 'New image', $html );
	}

	public function test_removed_featured_image_shows_none_in_added_cell() {
		$html = $this->render(
			array(
				'post_prev_thumb_id'    => (string) $this->old_attachment_id,
				'post_prev_thumb_title' => 'Old image',
			)
		);

		$this->assertMatchesRegularExpression( '#class="diff-addedline">\s*None\s*</td>#s', $html );
		$this->assertStringContainsString( 'Old image', $html );
	}

	public function test_deleted_attachment_falls_back_to_its_title() {
		$html = $this->render(
			array(
				'post_prev_thumb_id'    => '999999',
				'post_prev_thumb_title' => 'Gone image',
				'post_new_thumb_id'     => (string) $this->new_attachment_id,
				'post_new_thumb_title'  => 'New image',
			)
		);

		$this->assertStringContainsString( 'Gone image', $html );
		$this->assertSame( 1, substr_count( $html, '<img' ) );
	}

	private function render( array $context ): string {
		$row          = new stdClass();
		$row->logger  = 'SimplePostLogger';
		$row->context = array_merge( array( '_message_key' => 'post_updated' ), $context );

		return (string) $this->sh->get_log_row_details_output( $row );
	}
}
