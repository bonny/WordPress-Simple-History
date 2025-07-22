<?php

use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Inline_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Single_Item_Formatter;
use Simple_History\Event_Details\Event_Details_Item_RAW_Formatter;

/**
 * Tests for Event_Details_Container class.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_ContainerTest`
 */
class Event_Details_ContainerTest extends \Codeception\TestCase\WPTestCase {

	public function test_container_implements_interface() {
		$container = new Event_Details_Container();
		
		$this->assertInstanceOf( Event_Details_Container_Interface::class, $container, 'Container should implement interface' );
	}

	public function test_container_constructor_defaults() {
		$container = new Event_Details_Container();
		
		$this->assertIsArray( $container->groups, 'Groups should be an array' );
		$this->assertEmpty( $container->groups, 'Groups should be empty by default' );
	}

	public function test_container_constructor_with_single_group() {
		$group = new Event_Details_Group();
		$container = new Event_Details_Container( $group );
		
		$this->assertCount( 1, $container->groups, 'Should have one group' );
		$this->assertSame( $group, $container->groups[0], 'Group should be the same instance' );
	}

	public function test_container_constructor_with_group_array() {
		$group1 = new Event_Details_Group();
		$group2 = new Event_Details_Group();
		$groups = [ $group1, $group2 ];
		$container = new Event_Details_Container( $groups );
		
		$this->assertCount( 2, $container->groups, 'Should have two groups' );
		$this->assertSame( $group1, $container->groups[0], 'First group should be the same instance' );
		$this->assertSame( $group2, $container->groups[1], 'Second group should be the same instance' );
	}

	public function test_container_constructor_with_context() {
		$context = [
			'plugin_version' => '1.0.0',
			'setting_new' => 'enabled',
			'setting_prev' => 'disabled'
		];
		$container = new Event_Details_Container( [], $context );
		
		// Context is protected, so we test it indirectly by adding items
		$item = new Event_Details_Item( 'plugin_version', 'Plugin Version' );
		$container->add_item( $item );
		
		// After adding item, context should populate the value
		$this->assertEquals( '1.0.0', $item->new_value, 'Context should populate item value' );
	}

	public function test_add_group() {
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();
		
		$result = $container->add_group( $group );
		
		$this->assertCount( 1, $container->groups, 'Should have one group after adding' );
		$this->assertSame( $group, $container->groups[0], 'Added group should be the same instance' );
		$this->assertSame( $container, $result, 'add_group should return the container for fluent interface' );
	}

	public function test_add_groups() {
		$container = new Event_Details_Container();
		$group1 = new Event_Details_Group();
		$group2 = new Event_Details_Group();
		$groups = [ $group1, $group2 ];
		
		$result = $container->add_groups( $groups );
		
		$this->assertCount( 2, $container->groups, 'Should have two groups after adding' );
		$this->assertSame( $group1, $container->groups[0], 'First group should be the same instance' );
		$this->assertSame( $group2, $container->groups[1], 'Second group should be the same instance' );
		$this->assertSame( $container, $result, 'add_groups should return the container for fluent interface' );
	}

	public function test_add_item() {
		$container = new Event_Details_Container();
		$item = (new Event_Details_Item( 'test_field', 'Test Field' ))->set_new_value( 'Test Value' );
		
		$result = $container->add_item( $item );
		
		$this->assertCount( 1, $container->groups, 'Should have one auto-created group' );
		$this->assertCount( 1, $container->groups[0]->items, 'Auto-created group should have one item' );
		$remaining_items = array_values( $container->groups[0]->items ); // Re-index after filtering
		$this->assertSame( $item, $remaining_items[0], 'Item should be the same instance' );
		$this->assertInstanceOf( Event_Details_Group_Single_Item_Formatter::class, $container->groups[0]->formatter, 'Auto-created group should use single item formatter' );
		$this->assertSame( $container, $result, 'add_item should return the container for fluent interface' );
	}

	public function test_add_item_with_group_title() {
		$container = new Event_Details_Container();
		$item = (new Event_Details_Item( 'test_field', 'Test Field' ))->set_new_value( 'Test Value' );
		$group_title = 'Test Group';
		
		$container->add_item( $item, $group_title );
		
		$this->assertEquals( $group_title, $container->groups[0]->get_title(), 'Auto-created group should have the specified title' );
	}

	public function test_add_items() {
		$container = new Event_Details_Container();
		$item1 = (new Event_Details_Item( 'field1', 'Field 1' ))->set_new_value( 'Value 1' );
		$item2 = (new Event_Details_Item( 'field2', 'Field 2' ))->set_new_value( 'Value 2' );
		$items = [ $item1, $item2 ];
		
		$result = $container->add_items( $items );
		
		$this->assertCount( 1, $container->groups, 'Should have one auto-created group' );
		$this->assertCount( 2, $container->groups[0]->items, 'Auto-created group should have two items' );
		$remaining_items = array_values( $container->groups[0]->items ); // Re-index after filtering
		$this->assertSame( $item1, $remaining_items[0], 'First item should be the same instance' );
		$this->assertSame( $item2, $remaining_items[1], 'Second item should be the same instance' );
		$this->assertInstanceOf( Event_Details_Group_Table_Formatter::class, $container->groups[0]->formatter, 'Auto-created group should use default table formatter' );
		$this->assertSame( $container, $result, 'add_items should return the container for fluent interface' );
	}

	public function test_add_items_with_group_title() {
		$container = new Event_Details_Container();
		$items = [ (new Event_Details_Item( 'field1', 'Field 1' ))->set_new_value( 'Test Value' ) ];
		$group_title = 'Items Group';
		
		$container->add_items( $items, $group_title );
		
		$this->assertEquals( $group_title, $container->groups[0]->get_title(), 'Auto-created group should have the specified title' );
	}

	public function test_set_context() {
		$container = new Event_Details_Container();
		$context = [
			'plugin_version' => '2.0.0',
			'setting_new' => 'on',
			'setting_prev' => 'off'
		];
		
		$result = $container->set_context( $context );
		
		$this->assertSame( $container, $result, 'set_context should return the container for fluent interface' );
		
		// Test that context is applied to items
		$item = new Event_Details_Item( 'plugin_version', 'Plugin Version' );
		$container->add_item( $item );
		
		$this->assertEquals( '2.0.0', $item->new_value, 'Context should populate item value after set_context' );
	}

	public function test_context_value_population() {
		$context = [
			'plugin_name' => 'Test Plugin',
			'version_new' => '2.0.0',
			'version_prev' => '1.0.0',
			'status_new' => 'active',
			'status_prev' => null,
			'removed_feature_new' => null,
			'removed_feature_prev' => 'old feature'
		];
		
		$container = new Event_Details_Container( [], $context );
		
		$items = [
			new Event_Details_Item( 'plugin_name', 'Plugin Name' ),
			new Event_Details_Item( ['version_new', 'version_prev'], 'Version' ),
			new Event_Details_Item( ['status'], 'Status' ), // Auto _new/_prev format
			new Event_Details_Item( ['removed_feature_new', 'removed_feature_prev'], 'Removed Feature' )
		];
		
		$container->add_items( $items );
		
		// Test single value population
		$this->assertEquals( 'Test Plugin', $items[0]->new_value, 'Single context key should populate new_value' );
		$this->assertNull( $items[0]->prev_value, 'Single context key should leave prev_value null' );
		
		// Test explicit new/prev keys
		$this->assertEquals( '2.0.0', $items[1]->new_value, 'Explicit new key should populate new_value' );
		$this->assertEquals( '1.0.0', $items[1]->prev_value, 'Explicit prev key should populate prev_value' );
		
		// Test auto _new/_prev format
		$this->assertEquals( 'active', $items[2]->new_value, 'Auto _new suffix should populate new_value' );
		$this->assertNull( $items[2]->prev_value, 'Auto _prev suffix should populate prev_value' );
		
		// Test change detection
		$this->assertTrue( $items[1]->is_changed, 'Item with both old and new values should be marked as changed' );
		$this->assertTrue( $items[2]->is_added, 'Item with only new value should be marked as added' );
		$this->assertTrue( $items[3]->is_removed, 'Item with only prev value should be marked as removed' );
	}

	public function test_empty_items_removal() {
		$context = [
			'has_value' => 'present',
			'empty_new' => '',
			'empty_prev' => '',
			'only_new' => 'new_value',
			'only_prev_new' => null,
			'only_prev_prev' => 'old_value'
		];
		
		$container = new Event_Details_Container( [], $context );
		
		$items = [
			new Event_Details_Item( 'has_value', 'Has Value' ),
			new Event_Details_Item( ['empty_new', 'empty_prev'], 'Both Empty' ), // Should be removed
			new Event_Details_Item( 'only_new', 'Only New' ),
			new Event_Details_Item( ['only_prev_new', 'only_prev_prev'], 'Only Prev' ), // Should remain
		];
		
		$container->add_items( $items );
		
		// Filter out removed items by checking if they still exist in the group
		$remaining_items = $container->groups[0]->items;
		$remaining_names = array_map( function( $item ) {
			return $item->name;
		}, $remaining_items );
		
		$this->assertContains( 'Has Value', $remaining_names, 'Item with value should remain' );
		$this->assertNotContains( 'Both Empty', $remaining_names, 'Item with both empty values should be removed' );
		$this->assertContains( 'Only New', $remaining_names, 'Item with only new value should remain' );
		$this->assertContains( 'Only Prev', $remaining_names, 'Item with only prev value should remain (change from something to nothing)' );
	}

	public function test_to_html_method() {
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Test' ))->set_new_value( 'value' );
		$group->add_item( $item );
		$container->add_group( $group );
		
		$html = $container->to_html();
		
		$this->assertIsString( $html, 'to_html should return a string' );
		$this->assertNotEmpty( $html, 'HTML output should not be empty for container with items' );
	}

	public function test_to_json_method() {
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Test' ))->set_new_value( 'value' );
		$group->add_item( $item );
		$container->add_group( $group );
		
		$json = $container->to_json();
		
		$this->assertIsArray( $json, 'to_json should return an array' );
		$this->assertNotEmpty( $json, 'JSON output should not be empty for container with items' );
	}

	public function test_to_string_magic_method() {
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();
		$item = (new Event_Details_Item( null, 'Test' ))->set_new_value( 'value' );
		$group->add_item( $item );
		$container->add_group( $group );
		
		$string_output = (string) $container;
		$html_output = $container->to_html();
		
		$this->assertEquals( $html_output, $string_output, '__toString should return the same as to_html' );
	}

	public function test_empty_container_output() {
		$container = new Event_Details_Container();
		
		$this->assertEquals( '', $container->to_html(), 'Empty container should return empty HTML' );
		$this->assertEquals( [], $container->to_json(), 'Empty container should return empty array for JSON' );
		$this->assertEquals( '', (string) $container, 'Empty container should return empty string' );
	}

	public function test_fluent_interface_comprehensive() {
		$item1 = new Event_Details_Item( 'field1', 'Field 1' );
		$item2 = new Event_Details_Item( 'field2', 'Field 2' );
		$group = new Event_Details_Group();
		$context = [ 'field1' => 'value1', 'field2' => 'value2' ];
		
		$container = (new Event_Details_Container())
			->set_context( $context )
			->add_group( $group )
			->add_item( $item1 )
			->add_items( [ $item2 ], 'Items Group' );
		
		$this->assertCount( 3, $container->groups, 'Should have three groups from fluent chaining' );
		$this->assertEquals( 'value1', $item1->new_value, 'Context should be applied to items added via fluent interface' );
		$this->assertEquals( 'value2', $item2->new_value, 'Context should be applied to items added via fluent interface' );
	}

	public function test_context_updates_existing_groups() {
		$item = new Event_Details_Item( 'dynamic_field', 'Dynamic Field' );
		$group = new Event_Details_Group();
		$group->add_item( $item );
		
		// Set context before creating container so items don't get filtered out
		$container = new Event_Details_Container( $group, [ 'dynamic_field' => 'dynamic_value' ] );
		
		// Item should have value populated from context during container creation
		$remaining_items = array_values( $container->groups[0]->items );
		$this->assertNotEmpty( $remaining_items, 'Item should not be filtered out when it gets value from context' );
		$this->assertEquals( 'dynamic_value', $remaining_items[0]->new_value, 'Item should be updated with context value during container creation' );
	}

	public function test_manual_values_not_overwritten_by_context() {
		$item = new Event_Details_Item( 'test_field', 'Test Field' );
		$item->set_new_value( 'manual_value' );
		$container = new Event_Details_Container( [], [ 'test_field' => 'context_value' ] );
		$container->add_item( $item );
		
		$this->assertEquals( 'manual_value', $item->new_value, 'Manual values should not be overwritten by context' );
	}

	public function test_complex_real_world_scenario() {
		// Simulating a WordPress settings update scenario
		$context = [
			'show_on_dashboard_prev' => '0',
			'show_on_dashboard_new' => '1',
			'pager_size_prev' => '50',
			'pager_size_new' => '25',
			'plugin_version' => '1.2.3',
			'feature_removed_prev' => 'old_feature',
			'feature_removed_new' => null
		];
		
		$settings_group = (new Event_Details_Group())
			->set_title( 'Settings Changes' )
			->add_items([
				new Event_Details_Item( ['show_on_dashboard'], 'Show on Dashboard' ),
				new Event_Details_Item( ['pager_size'], 'Items per Page' ),
			]);
		
		$info_group = (new Event_Details_Group())
			->set_title( 'Plugin Information' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_items([
				new Event_Details_Item( 'plugin_version', 'Version' )
			]);
		
		$container = new Event_Details_Container( [ $settings_group, $info_group ], $context );
		$container->add_item( 
			new Event_Details_Item( ['feature_removed'], 'Removed Feature' ),
			'Deprecated Features'
		);
		
		$this->assertCount( 3, $container->groups, 'Should have three groups: settings, info, and deprecated' );
		
		// Check settings group
		$settings_items = $settings_group->items;
		$this->assertEquals( '1', $settings_items[0]->new_value, 'Dashboard setting should be enabled' );
		$this->assertEquals( '0', $settings_items[0]->prev_value, 'Dashboard setting should have been disabled' );
		$this->assertTrue( $settings_items[0]->is_changed, 'Dashboard setting should be marked as changed' );
		
		$this->assertEquals( '25', $settings_items[1]->new_value, 'Pager size should be updated' );
		$this->assertEquals( '50', $settings_items[1]->prev_value, 'Pager size should have previous value' );
		$this->assertTrue( $settings_items[1]->is_changed, 'Pager size should be marked as changed' );
		
		// Check info group
		$info_items = $info_group->items;
		$this->assertEquals( '1.2.3', $info_items[0]->new_value, 'Plugin version should be set' );
		$this->assertTrue( $info_items[0]->is_added, 'Plugin version should be marked as added' );
		
		// Check deprecated group
		$deprecated_group = $container->groups[2];
		$this->assertEquals( 'Deprecated Features', $deprecated_group->get_title(), 'Deprecated group should have correct title' );
		$deprecated_items = $deprecated_group->items;
		$this->assertNull( $deprecated_items[0]->new_value, 'Removed feature should have null new value' );
		$this->assertEquals( 'old_feature', $deprecated_items[0]->prev_value, 'Removed feature should have previous value' );
		$this->assertTrue( $deprecated_items[0]->is_removed, 'Removed feature should be marked as removed' );
		
		// Test outputs
		$html_output = $container->to_html();
		$json_output = $container->to_json();
		$this->assertIsString( $html_output, 'HTML output should be a string' );
		$this->assertNotEmpty( $html_output, 'HTML output should not be empty' );
		$this->assertIsArray( $json_output, 'JSON output should be an array' );
		$this->assertCount( 3, $json_output, 'JSON output should have three group entries' );
	}

	public function test_raw_formatter_items_always_included() {
		// Test that items with RAW formatters are always included, regardless of context
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();

		// Create item with context key that won't exist in context
		$item = new Event_Details_Item('nonexistent_key', 'My Field');
		
		// Set RAW formatter with custom output
		$raw_formatter = new Event_Details_Item_RAW_Formatter();
		$raw_formatter->set_html_output('<div>Custom RAW content</div>');
		$raw_formatter->set_json_output(['type' => 'custom', 'content' => 'RAW content']);
		
		$item->set_formatter($raw_formatter);
		$group->add_item($item);
		$container->add_group($group);

		// Verify item is present before setting context
		$this->assertCount(1, $group->items, 'Group should have one item initially');
		$this->assertTrue($item->has_custom_formatter(), 'Item should have custom formatter');

		// Set context that doesn't contain the item's key
		$context = ['some_other_key' => 'some value'];
		$container->set_context($context);

		// Item with RAW formatter should still be present
		$this->assertCount(1, $group->items, 'Item with RAW formatter should not be removed');
		
		// Output should contain the RAW formatter content
		$html = $container->to_html();
		$json = $container->to_json();
		
		$this->assertStringContainsString('Custom RAW content', $html, 'HTML should contain RAW formatter content');
		$this->assertNotEmpty($json, 'JSON output should not be empty');
		$this->assertEquals('custom', $json[0]['items'][0]['type'], 'JSON should contain RAW formatter output');
	}

	public function test_raw_formatter_with_existing_context_key() {
		// Test that RAW formatter works even when context key exists
		$container = new Event_Details_Container();
		$group = new Event_Details_Group();

		$item = new Event_Details_Item('existing_key', 'Field With Context');
		
		$raw_formatter = new Event_Details_Item_RAW_Formatter();
		$raw_formatter->set_html_output('<span>RAW output ignores context</span>');
		
		$item->set_formatter($raw_formatter);
		$group->add_item($item);
		$container->add_group($group);

		// Set context that contains the item's key
		$context = ['existing_key' => 'context value'];
		$container->set_context($context);

		// Item should still be present
		$this->assertCount(1, $group->items, 'Item should be present');
		
		$html = $container->to_html();
		$this->assertStringContainsString('RAW output ignores context', $html, 'Should use RAW formatter output, not context value');
	}
}