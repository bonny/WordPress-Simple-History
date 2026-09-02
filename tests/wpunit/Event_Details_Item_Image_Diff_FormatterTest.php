<?php

use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Item_Image_Diff_Table_Row_Formatter;

/**
 * The image diff formatter renders a previous and a new image side by side
 * in the same red/green diff table WordPress uses for revisions, so the
 * reader can tell which image is which without a strikethrough that images
 * cannot show.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_Item_Image_Diff_FormatterTest
 */
class Event_Details_Item_Image_Diff_FormatterTest extends \Codeception\TestCase\WPTestCase {
	public function test_previous_image_is_in_the_deleted_cell_and_new_image_in_the_added_cell() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_values( 'new.png', 'old.png' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_prev_image( 'https://example.com/old.png', 'old.png' )
			->set_new_image( 'https://example.com/new.png', 'new.png' );

		$html = $formatter->to_html();

		$this->assertStringStartsWith( '<tr>', trim( $html ) );
		$this->assertStringContainsString( '<td>Featured image</td>', $html );

		$this->assertMatchesRegularExpression(
			'#class="diff-deletedline".*?old\.png.*?src="https://example\.com/old\.png".*?class="diff-addedline".*?new\.png.*?src="https://example\.com/new\.png"#s',
			$html,
			'Previous image must come first in the deleted cell, new image after in the added cell'
		);
	}

	public function test_missing_side_renders_none_instead_of_an_empty_cell() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_new_value( 'new.png' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_new_image( 'https://example.com/new.png', 'new.png' );

		$html = $formatter->to_html();

		$this->assertMatchesRegularExpression(
			'#class="diff-deletedline">\s*None\s*</td>#s',
			$html,
			'The empty previous side must say "None"'
		);
		$this->assertStringContainsString( 'src="https://example.com/new.png"', $html );
	}

	public function test_side_without_image_but_with_caption_shows_the_caption() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_values( 'new.png', 'deleted-image.png (ID 42)' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_prev_image( '', 'deleted-image.png (ID 42)' )
			->set_new_image( 'https://example.com/new.png', 'new.png' );

		$html = $formatter->to_html();

		$this->assertStringContainsString( 'deleted-image.png (ID 42)', $html );
		$this->assertSame( 1, substr_count( $html, '<img' ), 'Only the new side has an image' );
	}

	public function test_captions_and_urls_are_escaped() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_new_value( 'x' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_new_image( 'javascript:alert(1)', '<b>bold</b>' );

		$html = $formatter->to_html();

		$this->assertStringNotContainsString( '<b>bold</b>', $html );
		$this->assertStringContainsString( '&lt;b&gt;bold&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_small_size_adds_the_small_thumbnail_modifier_to_both_sides() {
		$item = ( new Event_Details_Item( null, 'Site Icon' ) )
			->set_values( 'new.png', 'old.png' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_prev_image( 'https://example.com/old.png', 'old.png' )
			->set_new_image( 'https://example.com/new.png', 'new.png' )
			->set_size( 'small' );

		$html = $formatter->to_html();

		$this->assertSame( 2, substr_count( $html, 'SimpleHistoryLogitemThumbnail SimpleHistoryLogitemThumbnail--small' ) );
	}

	public function test_default_size_has_no_modifier() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_new_value( 'new.png' );

		$html = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_new_image( 'https://example.com/new.png', 'new.png' )
			->to_html();

		$this->assertStringContainsString( 'class="SimpleHistoryLogitemThumbnail"', $html );
		$this->assertStringNotContainsString( '--small', $html );
	}

	public function test_json_uses_the_item_values() {
		$item = ( new Event_Details_Item( null, 'Featured image' ) )
			->set_values( 'https://example.com/new.png', 'https://example.com/old.png' );

		$formatter = ( new Event_Details_Item_Image_Diff_Table_Row_Formatter( $item ) )
			->set_prev_image( 'https://example.com/old.png', 'old.png' )
			->set_new_image( 'https://example.com/new.png', 'new.png' );

		$json = $formatter->to_json();

		$this->assertSame( 'Featured image', $json['name'] );
		$this->assertSame( 'https://example.com/new.png', $json['new_value'] );
		$this->assertSame( 'https://example.com/old.png', $json['prev_value'] );
	}
}
