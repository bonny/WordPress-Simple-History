<?php

use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Item_Default_Formatter;
use Simple_History\Event_Details\Event_Details_Item_RAW_Formatter;
use Simple_History\Event_Details\Event_Details_Item_Table_Row_Formatter;
use Simple_History\Event_Details\Event_Details_Item_Table_Row_RAW_Formatter;
use Simple_History\Event_Details\Event_Details_Item_Diff_Table_Row_Formatter;

/**
 * Tests for all Event_Details Item Formatter classes.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_Item_FormattersTest`
 */
class Event_Details_Item_FormattersTest extends \Codeception\TestCase\WPTestCase {

	// Test Event_Details_Item_Default_Formatter

	public function test_default_formatter_with_name_and_new_value() {
		$item = (new Event_Details_Item( null, 'Test Field' ))->set_new_value( 'Test Value' );
		$formatter = new Event_Details_Item_Default_Formatter( $item );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertStringContainsString( '<span class="SimpleHistoryLogitem__inlineDivided">', $html, 'Should have inline divided span' );
		$this->assertStringContainsString( '<em>Test Field:</em>', $html, 'Should have emphasized field name with colon' );
		$this->assertStringContainsString( 'Test Value', $html, 'Should contain the value' );
		$this->assertStringContainsString( '</span>', $html, 'Should close the span' );
		
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertEquals( 'Test Field', $json['name'], 'JSON should contain name' );
		$this->assertEquals( 'Test Value', $json['new_value'], 'JSON should contain new_value' );
	}

	public function test_default_formatter_without_name() {
		$item = (new Event_Details_Item())->set_new_value( 'Nameless Value' );
		$formatter = new Event_Details_Item_Default_Formatter( $item );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertStringContainsString( '<span class="SimpleHistoryLogitem__inlineDivided">', $html, 'Should have inline divided span' );
		$this->assertStringNotContainsString( '<em>', $html, 'Should not have emphasized name when no name provided' );
		$this->assertStringContainsString( 'Nameless Value', $html, 'Should contain the value' );
		
		$this->assertArrayNotHasKey( 'name', $json, 'JSON should not have name key when name is null' );
		$this->assertEquals( 'Nameless Value', $json['new_value'], 'JSON should contain new_value' );
	}

	public function test_default_formatter_with_changed_values() {
		$item = (new Event_Details_Item( null, 'Changed Field' ))
			->set_values( 'New Value', 'Old Value' );
		$item->is_changed = true;
		$formatter = new Event_Details_Item_Default_Formatter( $item );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertStringContainsString( '<em>Changed Field:</em>', $html, 'Should have field name' );
		$this->assertStringContainsString( 'class="SimpleHistoryLogitem__keyValueTable__addedThing"', $html, 'Should have added thing class for new value' );
		$this->assertStringContainsString( 'class="SimpleHistoryLogitem__keyValueTable__removedThing"', $html, 'Should have removed thing class for old value' );
		$this->assertStringContainsString( 'New Value', $html, 'Should contain new value' );
		$this->assertStringContainsString( 'Old Value', $html, 'Should contain old value' );
		
		$this->assertEquals( 'New Value', $json['new_value'], 'JSON should contain new_value' );
		$this->assertEquals( 'Old Value', $json['prev_value'], 'JSON should contain prev_value' );
	}

	public function test_default_formatter_json_includes_all_properties() {
		$item = new Event_Details_Item( ['field_new', 'field_prev'], 'Complete Field' );
		$item->set_values( 'Complete New', 'Complete Prev' );
		$formatter = new Event_Details_Item_Default_Formatter( $item );
		
		$json = $formatter->to_json();
		
		$this->assertEquals( 'Complete Field', $json['name'], 'Should include name' );
		$this->assertEquals( 'Complete New', $json['new_value'], 'Should include new_value' );
		$this->assertEquals( 'Complete Prev', $json['prev_value'], 'Should include prev_value' );
		$this->assertEquals( 'field_new', $json['slug_new'], 'Should include slug_new' );
		$this->assertEquals( 'field_prev', $json['slug_prev'], 'Should include slug_prev' );
	}

	// Test Event_Details_Item_RAW_Formatter

	public function test_raw_formatter_with_set_outputs() {
		$item = new Event_Details_Item( null, 'Raw Item' );
		$formatter = new Event_Details_Item_RAW_Formatter( $item );
		
		$custom_html = '<div class="custom">Custom <strong>HTML</strong> content</div>';
		$custom_json = [ 'custom_key' => 'custom_value', 'number' => 123 ];
		
		$formatter->set_html_output( $custom_html );
		$formatter->set_json_output( $custom_json );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertEquals( $custom_html, $html, 'Should return exactly the custom HTML' );
		$this->assertEquals( $custom_json, $json, 'Should return exactly the custom JSON' );
	}

	public function test_raw_formatter_without_set_outputs() {
		$item = (new Event_Details_Item( null, 'Unused Item' ))->set_new_value( 'Ignored Value' );
		$formatter = new Event_Details_Item_RAW_Formatter( $item );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertEmpty( $html, 'Should return empty string when no HTML output set' );
		$this->assertEquals( [], $json, 'Should return empty array when no JSON output set' );
		$this->assertStringNotContainsString( 'Ignored Value', $html, 'Should not use item values when using raw formatter' );
	}

	public function test_raw_formatter_with_complex_data() {
		$item = new Event_Details_Item();
		$formatter = new Event_Details_Item_RAW_Formatter( $item );
		
		$complex_html = '<table><tr><td>Complex</td><td>Data &amp; "Quotes"</td></tr></table>';
		$complex_json = [
			'type' => 'complex',
			'data' => [
				'nested' => [ 'array' => 'structure' ],
				'boolean' => true,
				'null_value' => null
			]
		];
		
		$formatter->set_html_output( $complex_html );
		$formatter->set_json_output( $complex_json );
		
		$this->assertEquals( $complex_html, $formatter->to_html(), 'Should handle complex HTML' );
		$this->assertEquals( $complex_json, $formatter->to_json(), 'Should handle complex JSON structures' );
	}

	// Test Event_Details_Item_Table_Row_Formatter

	public function test_table_row_formatter_basic_structure() {
		$item = (new Event_Details_Item( null, 'Table Field' ))->set_new_value( 'Table Value' );
		$formatter = new Event_Details_Item_Table_Row_Formatter( $item );
		
		$html = $formatter->to_html();
		$json = $formatter->to_json();
		
		$this->assertStringContainsString( '<tr>', $html, 'Should start with table row' );
		$this->assertStringContainsString( '<td>Table Field</td>', $html, 'Should have field name in first cell' );
		$this->assertStringContainsString( '<td>', $html, 'Should have second cell for value' );
		$this->assertStringContainsString( 'Table Value', $html, 'Should contain the value in second cell' );
		$this->assertStringContainsString( '</tr>', $html, 'Should end with closing table row' );
		
		// JSON should delegate to default formatter
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertEquals( 'Table Field', $json['name'], 'JSON should contain name' );
		$this->assertEquals( 'Table Value', $json['new_value'], 'JSON should contain new_value' );
	}

	public function test_table_row_formatter_with_html_in_name() {
		$item = (new Event_Details_Item( null, 'Field with <script>alert("xss")</script>' ))->set_new_value( 'Safe Value' );
		$formatter = new Event_Details_Item_Table_Row_Formatter( $item );
		
		$html = $formatter->to_html();
		
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'Field name should be HTML escaped' );
		$this->assertStringNotContainsString( '<script>', $html, 'Should not contain unescaped HTML in name' );
		$this->assertStringContainsString( 'Safe Value', $html, 'Should still contain the value' );
	}

	public function test_table_row_formatter_with_changed_values() {
		$item = (new Event_Details_Item( null, 'Changed Table Field' ))
			->set_values( 'New Table Value', 'Old Table Value' );
		$item->is_changed = true;
		$formatter = new Event_Details_Item_Table_Row_Formatter( $item );
		
		$html = $formatter->to_html();
		
		$this->assertStringContainsString( '<td>Changed Table Field</td>', $html, 'Should have field name' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $html, 'Should have added styling for new value' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $html, 'Should have removed styling for old value' );
		$this->assertStringContainsString( 'New Table Value', $html, 'Should contain new value' );
		$this->assertStringContainsString( 'Old Table Value', $html, 'Should contain old value' );
	}

	public function test_table_row_formatter_json_delegation() {
		$item = new Event_Details_Item( ['slug_new', 'slug_prev'], 'Delegated Field' );
		$item->set_values( 'Delegated New', 'Delegated Prev' );
		$formatter = new Event_Details_Item_Table_Row_Formatter( $item );
		
		$json = $formatter->to_json();
		
		// Should match what Event_Details_Item_Default_Formatter would return
		$default_formatter = new Event_Details_Item_Default_Formatter( $item );
		$expected_json = $default_formatter->to_json();
		
		$this->assertEquals( $expected_json, $json, 'Table row formatter should delegate JSON to default formatter' );
	}

	// Test Event_Details_Item_Table_Row_RAW_Formatter

	public function test_table_row_raw_formatter_with_custom_html() {
		$item = new Event_Details_Item( null, 'RAW Table Field' );
		$formatter = new Event_Details_Item_Table_Row_RAW_Formatter( $item );
		
		$custom_html = '<strong>Custom</strong> <em>formatted</em> content with <a href="#">link</a>';
		$formatter->set_html_output( $custom_html );
		
		$html = $formatter->to_html();
		
		$this->assertStringContainsString( '<tr>', $html, 'Should start with table row' );
		$this->assertStringContainsString( '<td>RAW Table Field</td>', $html, 'Should have escaped field name in first cell' );
		$this->assertStringContainsString( '<td>' . $custom_html . '</td>', $html, 'Should have raw HTML in second cell' );
		$this->assertStringContainsString( '</tr>', $html, 'Should end with closing table row' );
	}

	public function test_table_row_raw_formatter_empty_html_output() {
		$item = new Event_Details_Item( null, 'Empty RAW Field' );
		$formatter = new Event_Details_Item_Table_Row_RAW_Formatter( $item );
		
		// Don't set any HTML output (or set empty)
		$formatter->set_html_output( '' );
		
		$html = $formatter->to_html();
		
		$this->assertEquals( '', $html, 'Should return empty string when html_output is empty' );
	}

	public function test_table_row_raw_formatter_inherits_raw_json() {
		$item = new Event_Details_Item( null, 'RAW JSON Field' );
		$formatter = new Event_Details_Item_Table_Row_RAW_Formatter( $item );
		
		$custom_json = [ 'raw_type' => 'table_row', 'custom_data' => 'test' ];
		$formatter->set_json_output( $custom_json );
		
		$json = $formatter->to_json();
		
		$this->assertEquals( $custom_json, $json, 'Should return custom JSON set via parent RAW formatter methods' );
	}

	// Test Event_Details_Item_Diff_Table_Row_Formatter

	public function test_diff_table_row_formatter_basic_structure() {
		$item = (new Event_Details_Item( null, 'Diff Field' ))
			->set_values( 'New content for diff', 'Old content for diff' );
		$item->is_changed = true;
		$formatter = new Event_Details_Item_Diff_Table_Row_Formatter( $item );
		
		$html = $formatter->to_html();
		
		$this->assertStringContainsString( '<tr>', $html, 'Should start with table row' );
		$this->assertStringContainsString( '<td>Diff Field</td>', $html, 'Should have field name in first cell' );
		$this->assertStringContainsString( '<td>', $html, 'Should have second cell for diff' );
		$this->assertStringContainsString( '</tr>', $html, 'Should end with closing table row' );
		
		// The diff content will depend on the Helpers::text_diff implementation
		// but we can check that it's not just basic diff highlighting
		$this->assertStringNotContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $html, 'Should not use basic diff classes' );
	}

	public function test_diff_table_row_formatter_json_delegation() {
		$item = new Event_Details_Item( ['diff_new', 'diff_prev'], 'Diff JSON Field' );
		$item->set_values( 'JSON New', 'JSON Prev' );
		$formatter = new Event_Details_Item_Diff_Table_Row_Formatter( $item );
		
		$json = $formatter->to_json();
		
		// Should delegate to default formatter like regular table row formatter
		$default_formatter = new Event_Details_Item_Default_Formatter( $item );
		$expected_json = $default_formatter->to_json();
		
		$this->assertEquals( $expected_json, $json, 'Diff table row formatter should delegate JSON to default formatter' );
	}

	// Test base formatter inheritance and common behaviors

	public function test_all_formatters_extend_base_class() {
		$item = new Event_Details_Item( null, 'Test' );
		
		$formatters = [
			new Event_Details_Item_Default_Formatter( $item ),
			new Event_Details_Item_RAW_Formatter( $item ),
			new Event_Details_Item_Table_Row_Formatter( $item ),
			new Event_Details_Item_Table_Row_RAW_Formatter( $item ),
			new Event_Details_Item_Diff_Table_Row_Formatter( $item )
		];
		
		foreach ( $formatters as $formatter ) {
			$this->assertInstanceOf( 
				'Simple_History\Event_Details\Event_Details_Item_Formatter', 
				$formatter, 
				'All item formatters should extend base Event_Details_Item_Formatter class' 
			);
		}
	}

	public function test_all_formatters_return_proper_types() {
		$item = (new Event_Details_Item( null, 'Type Test' ))->set_new_value( 'Test Value' );
		
		$formatters = [
			new Event_Details_Item_Default_Formatter( $item ),
			new Event_Details_Item_RAW_Formatter( $item ),
			new Event_Details_Item_Table_Row_Formatter( $item ),
			new Event_Details_Item_Table_Row_RAW_Formatter( $item ),
			new Event_Details_Item_Diff_Table_Row_Formatter( $item )
		];
		
		foreach ( $formatters as $formatter ) {
			$html = $formatter->to_html();
			$json = $formatter->to_json();
			
			$this->assertIsString( $html, 'to_html should return string' );
			$this->assertIsArray( $json, 'to_json should return array' );
		}
	}

	public function test_formatter_item_assignment() {
		$item = new Event_Details_Item( null, 'Assignment Test' );
		$formatter = new Event_Details_Item_Default_Formatter( $item );
		
		// Test that item was assigned correctly
		$this->assertSame( $item, $this->getFormatterItem( $formatter ), 'Item should be assigned to formatter' );
	}

	public function test_common_diff_highlighting_behavior() {
		$item = (new Event_Details_Item( null, 'Diff Test' ))
			->set_values( 'New Value', 'Old Value' );
		$item->is_changed = true;
		
		// Test formatters that use the common diff highlighting (not RAW or Diff Table Row)
		$formatters = [
			new Event_Details_Item_Default_Formatter( $item ),
			new Event_Details_Item_Table_Row_Formatter( $item )
		];
		
		foreach ( $formatters as $formatter ) {
			$html = $formatter->to_html();
			
			$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $html, 'Should have added thing styling' );
			$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $html, 'Should have removed thing styling' );
			$this->assertStringContainsString( '<ins', $html, 'Should have ins tag for new value' );
			$this->assertStringContainsString( '<del', $html, 'Should have del tag for old value' );
		}
	}

	public function test_item_states_added_and_removed() {
		// Test added item (only new value)
		$added_item = (new Event_Details_Item( null, 'Added Item' ))->set_new_value( 'Added Value' );
		$added_item->is_added = true;
		$added_formatter = new Event_Details_Item_Default_Formatter( $added_item );
		
		$added_html = $added_formatter->to_html();
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $added_html, 'Added item should have added styling' );
		$this->assertStringNotContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $added_html, 'Added item should not have removed styling' );
		
		// Test removed item (only prev value)
		$removed_item = (new Event_Details_Item( null, 'Removed Item' ))->set_prev_value( 'Removed Value' );
		$removed_item->is_removed = true;
		$removed_formatter = new Event_Details_Item_Default_Formatter( $removed_item );
		
		$removed_html = $removed_formatter->to_html();
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $removed_html, 'Removed item should have removed styling' );
		$this->assertStringNotContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $removed_html, 'Removed item should not have added styling' );
	}

	/**
	 * Helper method to access the protected item property of a formatter.
	 */
	private function getFormatterItem( $formatter ) {
		$reflection = new ReflectionClass( $formatter );
		$property = $reflection->getProperty( 'item' );
		$property->setAccessible( true );
		return $property->getValue( $formatter );
	}
}