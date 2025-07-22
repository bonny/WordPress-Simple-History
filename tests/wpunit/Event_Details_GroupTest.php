<?php

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Inline_Formatter;

/**
 * Tests for Event_Details_Group class.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_GroupTest`
 */
class Event_Details_GroupTest extends \Codeception\TestCase\WPTestCase {

	public function test_group_constructor_defaults() {
		$group = new Event_Details_Group();
		
		$this->assertIsArray( $group->items, 'Items should be an array' );
		$this->assertEmpty( $group->items, 'Items should be empty by default' );
		$this->assertInstanceOf( Event_Details_Group_Table_Formatter::class, $group->formatter, 'Default formatter should be Table formatter' );
		$this->assertNull( $group->title, 'Title should be null by default' );
	}

	public function test_add_item() {
		$group = new Event_Details_Group();
		$item = new Event_Details_Item( 'test_field', 'Test Field' );
		
		$result = $group->add_item( $item );
		
		$this->assertCount( 1, $group->items, 'Should have one item after adding' );
		$this->assertSame( $item, $group->items[0], 'Added item should be the same instance' );
		$this->assertSame( $group, $result, 'add_item should return the group for fluent interface' );
	}

	public function test_add_multiple_items_individually() {
		$group = new Event_Details_Group();
		$item1 = new Event_Details_Item( 'field1', 'Field 1' );
		$item2 = new Event_Details_Item( 'field2', 'Field 2' );
		
		$group->add_item( $item1 )->add_item( $item2 );
		
		$this->assertCount( 2, $group->items, 'Should have two items after adding both' );
		$this->assertSame( $item1, $group->items[0], 'First item should be at index 0' );
		$this->assertSame( $item2, $group->items[1], 'Second item should be at index 1' );
	}

	public function test_add_items_array() {
		$group = new Event_Details_Group();
		$item1 = new Event_Details_Item( 'field1', 'Field 1' );
		$item2 = new Event_Details_Item( 'field2', 'Field 2' );
		$items_array = [ $item1, $item2 ];
		
		$result = $group->add_items( $items_array );
		
		$this->assertCount( 2, $group->items, 'Should have two items after adding array' );
		$this->assertSame( $item1, $group->items[0], 'First item should be at index 0' );
		$this->assertSame( $item2, $group->items[1], 'Second item should be at index 1' );
		$this->assertSame( $group, $result, 'add_items should return the group for fluent interface' );
	}

	public function test_add_items_merges_with_existing() {
		$group = new Event_Details_Group();
		$existing_item = new Event_Details_Item( 'existing', 'Existing' );
		$new_item1 = new Event_Details_Item( 'new1', 'New 1' );
		$new_item2 = new Event_Details_Item( 'new2', 'New 2' );
		
		$group->add_item( $existing_item );
		$group->add_items( [ $new_item1, $new_item2 ] );
		
		$this->assertCount( 3, $group->items, 'Should have three items total' );
		$this->assertSame( $existing_item, $group->items[0], 'Existing item should remain at index 0' );
		$this->assertSame( $new_item1, $group->items[1], 'New item 1 should be at index 1' );
		$this->assertSame( $new_item2, $group->items[2], 'New item 2 should be at index 2' );
	}

	public function test_add_items_empty_array() {
		$group = new Event_Details_Group();
		$existing_item = new Event_Details_Item( 'existing', 'Existing' );
		
		$group->add_item( $existing_item );
		$result = $group->add_items( [] );
		
		$this->assertCount( 1, $group->items, 'Should still have one item after adding empty array' );
		$this->assertSame( $group, $result, 'add_items should return the group even with empty array' );
	}

	public function test_set_formatter() {
		$group = new Event_Details_Group();
		$inline_formatter = new Event_Details_Group_Inline_Formatter();
		
		$result = $group->set_formatter( $inline_formatter );
		
		$this->assertSame( $inline_formatter, $group->formatter, 'Formatter should be set correctly' );
		$this->assertSame( $group, $result, 'set_formatter should return the group for fluent interface' );
	}

	public function test_set_title() {
		$group = new Event_Details_Group();
		$title = 'Test Group Title';
		
		$result = $group->set_title( $title );
		
		$this->assertEquals( $title, $group->title, 'Title should be set correctly' );
		$this->assertSame( $group, $result, 'set_title should return the group for fluent interface' );
	}

	public function test_set_title_with_null() {
		$group = new Event_Details_Group();
		$group->set_title( 'Initial Title' );
		
		$result = $group->set_title( null );
		
		$this->assertNull( $group->title, 'Title should be null after setting to null' );
		$this->assertSame( $group, $result, 'set_title should return the group for fluent interface' );
	}

	public function test_get_title() {
		$group = new Event_Details_Group();
		$title = 'My Group Title';
		
		$group->set_title( $title );
		
		$this->assertEquals( $title, $group->get_title(), 'get_title should return the set title' );
	}

	public function test_get_title_when_null() {
		$group = new Event_Details_Group();
		
		$this->assertNull( $group->get_title(), 'get_title should return null when title is not set' );
	}

	public function test_fluent_interface_chaining() {
		$group = (new Event_Details_Group())
			->set_title( 'Chained Group' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_item( new Event_Details_Item( 'field1', 'Field 1' ) )
			->add_item( new Event_Details_Item( 'field2', 'Field 2' ) );
		
		$this->assertEquals( 'Chained Group', $group->title, 'Title should be set via chaining' );
		$this->assertInstanceOf( Event_Details_Group_Inline_Formatter::class, $group->formatter, 'Formatter should be set via chaining' );
		$this->assertCount( 2, $group->items, 'Items should be added via chaining' );
	}

	public function test_mixed_add_methods_fluent_chaining() {
		$item1 = new Event_Details_Item( 'field1', 'Field 1' );
		$item2 = new Event_Details_Item( 'field2', 'Field 2' );
		$item3 = new Event_Details_Item( 'field3', 'Field 3' );
		$items_array = [ $item2, $item3 ];
		
		$group = (new Event_Details_Group())
			->add_item( $item1 )
			->add_items( $items_array );
		
		$this->assertCount( 3, $group->items, 'Should have three items from mixed add methods' );
		$this->assertSame( $item1, $group->items[0], 'Single added item should be first' );
		$this->assertSame( $item2, $group->items[1], 'Array item 1 should be second' );
		$this->assertSame( $item3, $group->items[2], 'Array item 2 should be third' );
	}

	public function test_comprehensive_group_creation() {
		$items = [
			new Event_Details_Item( ['show_on_dashboard'], 'Show on dashboard' ),
			new Event_Details_Item( ['pager_size'], 'Items per page' ),
			new Event_Details_Item( 'plugin_version', 'Plugin version' ),
		];
		
		$group = (new Event_Details_Group())
			->set_title( 'Settings Changes' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_items( $items );
		
		$this->assertEquals( 'Settings Changes', $group->get_title(), 'Title should be set correctly' );
		$this->assertInstanceOf( Event_Details_Group_Inline_Formatter::class, $group->formatter, 'Formatter should be set correctly' );
		$this->assertCount( 3, $group->items, 'Should have all three items' );
		
		// Verify item details
		$this->assertEquals( 'Show on dashboard', $group->items[0]->name, 'First item name should be correct' );
		$this->assertEquals( 'show_on_dashboard_new', $group->items[0]->slug_new, 'First item should have auto-generated new slug' );
		$this->assertEquals( 'show_on_dashboard_prev', $group->items[0]->slug_prev, 'First item should have auto-generated prev slug' );
		
		$this->assertEquals( 'Plugin version', $group->items[2]->name, 'Third item name should be correct' );
		$this->assertEquals( 'plugin_version', $group->items[2]->slug_new, 'Third item should have string slug' );
		// Note: slug_prev is uninitialized for string slug, so we can't test it directly
	}

	public function test_group_properties_are_public() {
		$group = new Event_Details_Group();
		$item = new Event_Details_Item( 'test', 'Test' );
		
		// Test that we can directly access public properties
		$group->items[] = $item;
		$group->title = 'Direct Title';
		
		$this->assertCount( 1, $group->items, 'Should be able to directly modify items array' );
		$this->assertEquals( 'Direct Title', $group->title, 'Should be able to directly set title' );
	}

	public function test_formatter_replacement() {
		$group = new Event_Details_Group();
		$original_formatter = $group->formatter;
		$new_formatter = new Event_Details_Group_Inline_Formatter();
		
		$this->assertNotSame( $new_formatter, $original_formatter, 'New formatter should be different from original' );
		
		$group->set_formatter( $new_formatter );
		
		$this->assertSame( $new_formatter, $group->formatter, 'Formatter should be replaced' );
		$this->assertNotSame( $original_formatter, $group->formatter, 'Original formatter should no longer be set' );
	}
}