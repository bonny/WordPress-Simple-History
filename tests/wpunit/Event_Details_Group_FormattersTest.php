<?php

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Inline_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Single_Item_Formatter;
use Simple_History\Event_Details\Event_Details_Item_Default_Formatter;

/**
 * Tests for all Event_Details Group Formatter classes.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_Group_FormattersTest`
 */
class Event_Details_Group_FormattersTest extends \Codeception\TestCase\WPTestCase {

	// Test Event_Details_Group_Table_Formatter

	public function test_table_formatter_empty_group() {
		$formatter = new Event_Details_Group_Table_Formatter();
		$group = new Event_Details_Group();
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertEquals( '', $html, 'Empty group should return empty string, not empty table' );
		
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertArrayHasKey( 'title', $json, 'JSON should have title key' );
		$this->assertArrayHasKey( 'items', $json, 'JSON should have items key' );
		$this->assertEmpty( $json['items'], 'JSON items should be empty for empty group' );
	}

	public function test_table_formatter_single_item() {
		$formatter = new Event_Details_Group_Table_Formatter();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Test Field' ))->set_new_value( 'Test Value' );
		$group->add_item( $item );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should contain table with correct class' );
		$this->assertStringContainsString( '<tr>', $html, 'Should contain table row' );
		$this->assertStringContainsString( 'Test Field', $html, 'Should contain item name' );
		$this->assertStringContainsString( 'Test Value', $html, 'Should contain item value' );
		
		$this->assertCount( 1, $json['items'], 'JSON should have one item' );
		$this->assertEquals( 'Test Field', $json['items'][0]['name'], 'JSON should contain item name' );
		$this->assertEquals( 'Test Value', $json['items'][0]['new_value'], 'JSON should contain item value' );
	}

	public function test_table_formatter_multiple_items_with_changes() {
		$formatter = new Event_Details_Group_Table_Formatter();
		$group = new Event_Details_Group();
		$group->set_title( 'Settings Changes' );
		
		$item1 = (new Event_Details_Item( null, 'Setting 1' ))->set_values( 'New Value 1', 'Old Value 1' );
		$item2 = (new Event_Details_Item( null, 'Setting 2' ))->set_new_value( 'Added Value' );
		$group->add_items( [ $item1, $item2 ] );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		// Check HTML structure
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should contain table' );
		$this->assertStringContainsString( 'Setting 1', $html, 'Should contain first item name' );
		$this->assertStringContainsString( 'Setting 2', $html, 'Should contain second item name' );
		$this->assertStringContainsString( 'New Value 1', $html, 'Should contain first item new value' );
		$this->assertStringContainsString( 'Added Value', $html, 'Should contain second item value' );
		
		// Check JSON structure
		$this->assertEquals( 'Settings Changes', $json['title'], 'JSON should contain group title' );
		$this->assertCount( 2, $json['items'], 'JSON should have two items' );
		$this->assertEquals( 'Setting 1', $json['items'][0]['name'], 'First JSON item should have correct name' );
		$this->assertEquals( 'New Value 1', $json['items'][0]['new_value'], 'First JSON item should have correct new value' );
		$this->assertEquals( 'Old Value 1', $json['items'][0]['prev_value'], 'First JSON item should have correct prev value' );
	}

	// Test Event_Details_Group_Inline_Formatter

	public function test_inline_formatter_empty_group() {
		$formatter = new Event_Details_Group_Inline_Formatter();
		$group = new Event_Details_Group();
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertEquals( '<p></p>', $html, 'Empty group should produce empty paragraph' );
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertEmpty( $json['items'], 'JSON items should be empty for empty group' );
	}

	public function test_inline_formatter_single_item() {
		$formatter = new Event_Details_Group_Inline_Formatter();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Status' ))->set_new_value( 'Active' );
		$group->add_item( $item );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertStringContainsString( '<p>', $html, 'Should contain paragraph tag' );
		$this->assertStringContainsString( 'class="SimpleHistoryLogitem__inlineDivided"', $html, 'Should contain inline divided class' );
		$this->assertStringContainsString( '<em>Status:</em>', $html, 'Should contain emphasized field name' );
		$this->assertStringContainsString( 'Active', $html, 'Should contain field value' );
		$this->assertStringContainsString( '</p>', $html, 'Should close paragraph tag' );
		
		$this->assertCount( 1, $json['items'], 'JSON should have one item' );
		$this->assertEquals( 'Status', $json['items'][0]['name'], 'JSON should contain item name' );
	}

	public function test_inline_formatter_multiple_items() {
		$formatter = new Event_Details_Group_Inline_Formatter();
		$group = new Event_Details_Group();
		$group->set_title( 'Quick Summary' );
		
		$item1 = (new Event_Details_Item( null, 'Size' ))->set_new_value( '1.2MB' );
		$item2 = (new Event_Details_Item( null, 'Format' ))->set_new_value( 'PNG' );
		$group->add_items( [ $item1, $item2 ] );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertStringContainsString( '<p>', $html, 'Should contain paragraph tag' );
		$this->assertStringContainsString( '<em>Size:</em>', $html, 'Should contain first item name' );
		$this->assertStringContainsString( '1.2MB', $html, 'Should contain first item value' );
		$this->assertStringContainsString( '<em>Format:</em>', $html, 'Should contain second item name' );
		$this->assertStringContainsString( 'PNG', $html, 'Should contain second item value' );
		
		// Count occurrences of inline divided class (should be 2)
		$class_count = substr_count( $html, 'SimpleHistoryLogitem__inlineDivided' );
		$this->assertEquals( 2, $class_count, 'Should have inline divided class for each item' );
		
		$this->assertEquals( 'Quick Summary', $json['title'], 'JSON should contain group title' );
		$this->assertCount( 2, $json['items'], 'JSON should have two items' );
	}

	public function test_inline_formatter_item_without_name() {
		$formatter = new Event_Details_Group_Inline_Formatter();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item())->set_new_value( 'Value Without Name' );
		$group->add_item( $item );
		
		$html = $formatter->to_html( $group );
		
		$this->assertStringContainsString( 'Value Without Name', $html, 'Should contain item value even without name' );
		$this->assertStringNotContainsString( '<em>:</em>', $html, 'Should not contain empty emphasized name' );
	}

	// Test Event_Details_Group_Diff_Table_Formatter

	public function test_diff_table_formatter_structure() {
		$formatter = new Event_Details_Group_Diff_Table_Formatter();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Content' ))->set_values( 'New content', 'Old content' );
		$group->add_item( $item );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		// Should have same table structure as regular table formatter
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should contain table with correct class' );
		$this->assertStringContainsString( '<tbody>', $html, 'Should contain tbody' );
		$this->assertStringContainsString( '<tr>', $html, 'Should contain table row' );
		$this->assertStringContainsString( 'Content', $html, 'Should contain item name' );
		
		// JSON should follow standard format
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertArrayHasKey( 'items', $json, 'JSON should have items key' );
		$this->assertCount( 1, $json['items'], 'JSON should have one item' );
		$this->assertEquals( 'Content', $json['items'][0]['name'], 'JSON should contain item name' );
	}

	public function test_diff_table_formatter_empty_group() {
		$formatter = new Event_Details_Group_Diff_Table_Formatter();
		$group = new Event_Details_Group();
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertEquals( '', $html, 'Empty group should return empty string, not empty table' );
		$this->assertEmpty( $json['items'], 'JSON items should be empty for empty group' );
	}

	// Test Event_Details_Group_Single_Item_Formatter

	public function test_single_item_formatter_empty_group() {
		$formatter = new Event_Details_Group_Single_Item_Formatter();
		$group = new Event_Details_Group();
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		$this->assertEquals( '', $html, 'Empty group should produce empty string' );
		$this->assertIsArray( $json, 'JSON should be an array' );
		$this->assertEmpty( $json['items'], 'JSON items should be empty for empty group' );
	}

	public function test_single_item_formatter_one_item() {
		$formatter = new Event_Details_Group_Single_Item_Formatter();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Single Field' ))
			->set_new_value( 'Single Value' )
			->set_formatter( new Event_Details_Item_Default_Formatter() );
		$group->add_item( $item );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		// HTML should contain the item content without wrapper elements
		$this->assertStringNotContainsString( '<table>', $html, 'Should not contain table wrapper' );
		$this->assertStringNotContainsString( '<p>', $html, 'Should not contain paragraph wrapper' );
		$this->assertStringContainsString( 'Single Field', $html, 'Should contain item name' );
		$this->assertStringContainsString( 'Single Value', $html, 'Should contain item value' );
		
		$this->assertCount( 1, $json['items'], 'JSON should have one item' );
		$this->assertEquals( 'Single Field', $json['items'][0]['name'], 'JSON should contain item name' );
	}

	public function test_single_item_formatter_multiple_items() {
		$formatter = new Event_Details_Group_Single_Item_Formatter();
		$group = new Event_Details_Group();
		$group->set_title( 'Multiple Singles' );
		
		$item1 = (new Event_Details_Item( null, 'First' ))
			->set_new_value( 'First Value' )
			->set_formatter( new Event_Details_Item_Default_Formatter() );
		$item2 = (new Event_Details_Item( null, 'Second' ))
			->set_new_value( 'Second Value' )
			->set_formatter( new Event_Details_Item_Default_Formatter() );
		$group->add_items( [ $item1, $item2 ] );
		
		$html = $formatter->to_html( $group );
		$json = $formatter->to_json( $group );
		
		// HTML should concatenate items without wrappers
		$this->assertStringContainsString( 'First', $html, 'Should contain first item' );
		$this->assertStringContainsString( 'Second', $html, 'Should contain second item' );
		$this->assertStringNotContainsString( '<table>', $html, 'Should not have table wrapper' );
		
		$this->assertEquals( 'Multiple Singles', $json['title'], 'JSON should contain group title' );
		$this->assertCount( 2, $json['items'], 'JSON should have two items' );
	}

	// Common JSON structure tests

	public function test_all_formatters_have_consistent_json_structure() {
		$formatters = [
			'table' => new Event_Details_Group_Table_Formatter(),
			'inline' => new Event_Details_Group_Inline_Formatter(),
			'diff_table' => new Event_Details_Group_Diff_Table_Formatter(),
			'single_item' => new Event_Details_Group_Single_Item_Formatter()
		];
		
		foreach ( $formatters as $name => $formatter ) {
			$group = new Event_Details_Group();
			$group->set_title( "Test Group for {$name}" );
			$item = (new Event_Details_Item( null, 'Test Field' ))
				->set_new_value( 'Test Value' )
				->set_formatter( new Event_Details_Item_Default_Formatter() );
			$group->add_item( $item );
			
			$json = $formatter->to_json( $group );
			
			$this->assertIsArray( $json, "{$name} formatter should return array for JSON" );
			$this->assertArrayHasKey( 'title', $json, "{$name} formatter should have title key" );
			$this->assertArrayHasKey( 'items', $json, "{$name} formatter should have items key" );
			$this->assertIsArray( $json['items'], "{$name} formatter items should be array" );
			
			if ( ! empty( $json['items'] ) ) {
				$first_item = $json['items'][0];
				$this->assertArrayHasKey( 'name', $first_item, "{$name} formatter item should have name" );
				$this->assertArrayHasKey( 'new_value', $first_item, "{$name} formatter item should have new_value" );
			}
		}
	}

	public function test_formatter_inheritance() {
		$formatters = [
			new Event_Details_Group_Table_Formatter(),
			new Event_Details_Group_Inline_Formatter(),
			new Event_Details_Group_Diff_Table_Formatter(),
			new Event_Details_Group_Single_Item_Formatter()
		];
		
		foreach ( $formatters as $formatter ) {
			$this->assertInstanceOf( 
				'Simple_History\Event_Details\Event_Details_Group_Formatter', 
				$formatter, 
				'All formatters should extend base Event_Details_Group_Formatter class' 
			);
		}
	}

	public function test_html_output_is_string() {
		$formatters = [
			new Event_Details_Group_Table_Formatter(),
			new Event_Details_Group_Inline_Formatter(),
			new Event_Details_Group_Diff_Table_Formatter(),
			new Event_Details_Group_Single_Item_Formatter()
		];
		
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Test' ))
			->set_new_value( 'Value' )
			->set_formatter( new Event_Details_Item_Default_Formatter() );
		$group->add_item( $item );
		
		foreach ( $formatters as $formatter ) {
			$html = $formatter->to_html( $group );
			$this->assertIsString( $html, 'All formatters should return string for HTML' );
		}
	}

	public function test_json_output_structure() {
		$formatters = [
			new Event_Details_Group_Table_Formatter(),
			new Event_Details_Group_Inline_Formatter(),
			new Event_Details_Group_Diff_Table_Formatter(),
			new Event_Details_Group_Single_Item_Formatter()
		];
		
		$group = new Event_Details_Group();
		$group->set_title( 'JSON Test Group' );
		
		foreach ( $formatters as $formatter ) {
			$json = $formatter->to_json( $group );
			$this->assertIsArray( $json, 'All formatters should return array for JSON' );
			$this->assertEquals( 'JSON Test Group', $json['title'], 'All formatters should preserve group title in JSON' );
		}
	}
}