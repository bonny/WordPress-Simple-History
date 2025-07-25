<?php

use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Item_Default_Formatter;
use Simple_History\Event_Details\Event_Details_Item_RAW_Formatter;

/**
 * Tests for Event_Details_Item class.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_ItemTest`
 */
class Event_Details_ItemTest extends \Codeception\TestCase\WPTestCase {

	public function test_item_constructor_with_null_values() {
		$item = new Event_Details_Item();
		
		$this->assertNull( $item->name, 'Name should be null when not provided' );
		// Note: slug_new and slug_prev are uninitialized when constructor gets null, so we can't access them directly
		$this->assertNull( $item->new_value, 'new_value should be null by default' );
		$this->assertNull( $item->prev_value, 'prev_value should be null by default' );
		$this->assertNull( $item->is_changed, 'is_changed should be null by default' );
		$this->assertNull( $item->is_added, 'is_added should be null by default' );
		$this->assertNull( $item->is_removed, 'is_removed should be null by default' );
	}

	public function test_item_constructor_with_string_slug() {
		$item = new Event_Details_Item( 'plugin_version', 'Plugin Version' );
		
		$this->assertEquals( 'Plugin Version', $item->name, 'Name should be set correctly' );
		$this->assertEquals( 'plugin_version', $item->slug_new, 'slug_new should be set from string parameter' );
		// slug_prev is uninitialized for single string slug, so we can't access it directly
	}

	public function test_item_constructor_with_array_two_elements() {
		$item = new Event_Details_Item( ['title_new', 'title_prev'], 'Page Title' );
		
		$this->assertEquals( 'Page Title', $item->name, 'Name should be set correctly' );
		$this->assertEquals( 'title_new', $item->slug_new, 'slug_new should be first array element' );
		$this->assertEquals( 'title_prev', $item->slug_prev, 'slug_prev should be second array element' );
	}

	public function test_item_constructor_with_array_single_element() {
		$item = new Event_Details_Item( ['setting_name'], 'Setting Name' );
		
		$this->assertEquals( 'Setting Name', $item->name, 'Name should be set correctly' );
		$this->assertEquals( 'setting_name_new', $item->slug_new, 'slug_new should have _new suffix' );
		$this->assertEquals( 'setting_name_prev', $item->slug_prev, 'slug_prev should have _prev suffix' );
	}

	public function test_set_new_value() {
		$item = new Event_Details_Item();
		
		$result = $item->set_new_value( 'test value' );
		
		$this->assertEquals( 'test value', $item->new_value, 'new_value should be set correctly' );
		$this->assertSame( $item, $result, 'set_new_value should return the item for fluent interface' );
	}

	public function test_set_prev_value() {
		$item = new Event_Details_Item();
		
		$result = $item->set_prev_value( 'previous value' );
		
		$this->assertEquals( 'previous value', $item->prev_value, 'prev_value should be set correctly' );
		$this->assertSame( $item, $result, 'set_prev_value should return the item for fluent interface' );
	}

	public function test_set_values() {
		$item = new Event_Details_Item();
		
		$result = $item->set_values( 'new value', 'old value' );
		
		$this->assertEquals( 'new value', $item->new_value, 'new_value should be set correctly' );
		$this->assertEquals( 'old value', $item->prev_value, 'prev_value should be set correctly' );
		$this->assertSame( $item, $result, 'set_values should return the item for fluent interface' );
	}

	public function test_fluent_interface_chaining() {
		$item = (new Event_Details_Item( null, 'Status' ))
			->set_new_value( 'Active' )
			->set_prev_value( 'Inactive' );
		
		$this->assertEquals( 'Status', $item->name, 'Name should be set correctly' );
		$this->assertEquals( 'Active', $item->new_value, 'new_value should be set correctly' );
		$this->assertEquals( 'Inactive', $item->prev_value, 'prev_value should be set correctly' );
	}

	public function test_set_formatter_with_instance() {
		$item = new Event_Details_Item();
		$formatter = new Event_Details_Item_RAW_Formatter();
		
		$result = $item->set_formatter( $formatter );
		
		$this->assertSame( $item, $result, 'set_formatter should return the item for fluent interface' );
		$this->assertSame( $formatter, $item->get_formatter(), 'Formatter should be set correctly' );
	}

	public function test_set_formatter_with_class_string() {
		$item = new Event_Details_Item();
		
		$result = $item->set_formatter( Event_Details_Item_RAW_Formatter::class );
		
		$this->assertSame( $item, $result, 'set_formatter should return the item for fluent interface' );
		$this->assertInstanceOf( Event_Details_Item_RAW_Formatter::class, $item->get_formatter(), 'Formatter should be instantiated from class string' );
	}

	public function test_get_formatter_with_fallback_instance() {
		$item = new Event_Details_Item();
		$fallback_formatter = new Event_Details_Item_Default_Formatter();
		
		$formatter = $item->get_formatter( $fallback_formatter );
		
		$this->assertSame( $fallback_formatter, $formatter, 'Should return fallback formatter instance when no formatter set' );
	}

	public function test_get_formatter_with_fallback_class_string() {
		$item = new Event_Details_Item();
		
		$formatter = $item->get_formatter( Event_Details_Item_Default_Formatter::class );
		
		$this->assertInstanceOf( Event_Details_Item_Default_Formatter::class, $formatter, 'Should instantiate fallback formatter class when no formatter set' );
	}

	public function test_get_formatter_uses_set_formatter_over_fallback() {
		$item = new Event_Details_Item();
		$set_formatter = new Event_Details_Item_RAW_Formatter();
		$fallback_formatter = new Event_Details_Item_Default_Formatter();
		
		$item->set_formatter( $set_formatter );
		$formatter = $item->get_formatter( $fallback_formatter );
		
		$this->assertSame( $set_formatter, $formatter, 'Should return set formatter instead of fallback' );
	}

	public function test_has_formatter() {
		$item = new Event_Details_Item();
		
		$this->assertTrue( $item->has_formatter(), 'has_formatter should always return true' );
		
		// Even with a custom formatter set
		$item->set_formatter( new Event_Details_Item_Default_Formatter() );
		$this->assertTrue( $item->has_formatter(), 'has_formatter should still return true' );
	}

	public function test_has_custom_formatter() {
		$item = new Event_Details_Item();
		
		$this->assertFalse( $item->has_custom_formatter(), 'has_custom_formatter should return false when no formatter is set' );
		
		// Set a custom formatter
		$item->set_formatter( new Event_Details_Item_Default_Formatter() );
		$this->assertTrue( $item->has_custom_formatter(), 'has_custom_formatter should return true when formatter is set' );
	}

	public function test_constructor_edge_cases() {
		// Test with empty array (slug properties will be uninitialized, can't access directly)
		$item = new Event_Details_Item( [], 'Empty Array' );
		$this->assertEquals( 'Empty Array', $item->name, 'Name should be set correctly for empty array' );

		// Test with array containing more than 2 elements (slug properties will be uninitialized)
		$item = new Event_Details_Item( ['one', 'two', 'three'], 'Three Elements' );
		$this->assertEquals( 'Three Elements', $item->name, 'Name should be set correctly for oversized array' );

		// Test with numeric values
		$item = new Event_Details_Item( 'numeric_field', 'Numeric Field' );
		$item->set_values( '123', '456' );
		$this->assertEquals( '123', $item->new_value, 'Should handle numeric string values' );
		$this->assertEquals( '456', $item->prev_value, 'Should handle numeric string values' );
	}

	public function test_formatter_item_assignment() {
		$item = new Event_Details_Item( 'test_field', 'Test Field' );
		$formatter = new Event_Details_Item_Default_Formatter();
		
		$item->set_formatter( $formatter );
		
		// Verify that the formatter has the item assigned
		$retrieved_formatter = $item->get_formatter();
		$this->assertSame( $item, $this->get_formatter_item( $retrieved_formatter ), 'Formatter should have item assigned' );
	}

	public function test_multiple_slug_formats_comprehensive() {
		// Test all supported constructor formats that initialize slug properties
		$test_cases = [
			[
				'input' => ['field_name'],
				'expected_new' => 'field_name_new',
				'expected_prev' => 'field_name_prev',
				'description' => 'Single element array should auto-generate _new/_prev suffixes'
			],
			[
				'input' => ['custom_new_key', 'custom_prev_key'],
				'expected_new' => 'custom_new_key',
				'expected_prev' => 'custom_prev_key',
				'description' => 'Two element array should use exact keys'
			],
			[
				'input' => 'direct_key',
				'expected_new' => 'direct_key',
				'description' => 'String should set only new key'
			]
		];

		foreach ( $test_cases as $case ) {
			$item = new Event_Details_Item( $case['input'], 'Test Name' );
			
			$this->assertEquals( $case['expected_new'], $item->slug_new, $case['description'] . ' - slug_new' );
			if ( isset( $case['expected_prev'] ) ) {
				$this->assertEquals( $case['expected_prev'], $item->slug_prev, $case['description'] . ' - slug_prev' );
			}
		}
		
		// Test null case separately since it leaves properties uninitialized
		$null_item = new Event_Details_Item( null, 'Null Test' );
		$this->assertEquals( 'Null Test', $null_item->name, 'Null input should still set name' );
	}

	/**
	 * Helper method to access the protected item property of a formatter.
	 * This uses reflection to test the internal state.
	 */
	private function get_formatter_item( $formatter ) {
		$reflection = new ReflectionClass( $formatter );
		$property = $reflection->getProperty( 'item' );
		$property->setAccessible( true );
		return $property->getValue( $formatter );
	}
}