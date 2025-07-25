<?php

use Simple_History\Event_Details\Event_Details_Simple_Container;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Item_Default_Formatter;

/**
 * Tests for Event_Details_Simple_Container class.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_Simple_ContainerTest`
 */
class Event_Details_Simple_ContainerTest extends \Codeception\TestCase\WPTestCase {

	public function test_simple_container_implements_interface() {
		$container = new Event_Details_Simple_Container();
		
		$this->assertInstanceOf( Event_Details_Container_Interface::class, $container, 'Simple container should implement interface' );
	}

	public function test_constructor_with_no_parameters() {
		$container = new Event_Details_Simple_Container();
		
		$this->assertEquals( '', $container->to_html(), 'Default constructor should result in empty HTML' );
		$this->assertEquals( [], $container->to_json(), 'Default constructor should result in empty JSON array' );
		$this->assertEquals( '', (string) $container, 'Default constructor should result in empty string' );
	}

	public function test_constructor_with_string_html() {
		$html = '<p>Test HTML content</p>';
		$container = new Event_Details_Simple_Container( $html );
		
		$this->assertEquals( $html, $container->to_html(), 'Should return the HTML string provided' );
		$this->assertEquals( $html, (string) $container, '__toString should return the same HTML' );
	}

	public function test_constructor_with_empty_string() {
		$container = new Event_Details_Simple_Container( '' );
		
		$this->assertEquals( '', $container->to_html(), 'Empty string should return empty HTML' );
		$this->assertEquals( '', (string) $container, '__toString should return empty string' );
	}

	public function test_constructor_with_null() {
		$container = new Event_Details_Simple_Container( null );
		
		$this->assertEquals( null, $container->to_html(), 'Null input should return null' );
		$this->assertEquals( '', (string) $container, '__toString should convert null to empty string' );
	}

	public function test_constructor_with_complex_html() {
		$html = '<div class="event-details">' .
		        '<h3>Event Information</h3>' .
		        '<table><tr><td>Field</td><td>Value</td></tr></table>' .
		        '</div>';
		$container = new Event_Details_Simple_Container( $html );
		
		$this->assertEquals( $html, $container->to_html(), 'Should handle complex HTML correctly' );
		$this->assertEquals( $html, (string) $container, '__toString should handle complex HTML correctly' );
	}

	public function test_constructor_with_container_interface() {
		// Create a proper Event_Details_Container with some content
		$item = new Event_Details_Item( null, 'Test Field' );
		$item->set_new_value( 'Test Value' );
		$group = new Event_Details_Group();
		$group->add_item( $item );
		$full_container = new Event_Details_Container( $group );
		
		// Wrap it in a simple container
		$simple_container = new Event_Details_Simple_Container( $full_container );
		
		$this->assertEquals( $full_container->to_html(), $simple_container->to_html(), 'Should delegate to_html to the wrapped container' );
		$this->assertEquals( $full_container->to_html(), (string) $simple_container, '__toString should delegate to wrapped container' );
	}

	public function test_to_json_always_returns_empty_array() {
		// Test with string HTML
		$string_container = new Event_Details_Simple_Container( '<p>Test</p>' );
		$this->assertEquals( [], $string_container->to_json(), 'to_json should return empty array for string HTML' );
		
		// Test with empty string
		$empty_container = new Event_Details_Simple_Container( '' );
		$this->assertEquals( [], $empty_container->to_json(), 'to_json should return empty array for empty string' );
		
		// Test with wrapped container (that would normally have JSON output)
		$item = (new Event_Details_Item( null, 'Test' ))->set_new_value( 'value' );
		$group = new Event_Details_Group();
		$group->add_item( $item );
		$full_container = new Event_Details_Container( $group );
		$wrapped_container = new Event_Details_Simple_Container( $full_container );
		$this->assertEquals( [], $wrapped_container->to_json(), 'to_json should return empty array even for wrapped containers' );
		
		// Test with null
		$null_container = new Event_Details_Simple_Container( null );
		$this->assertEquals( [], $null_container->to_json(), 'to_json should return empty array for null' );
	}

	public function test_legacy_html_wrapping_use_case() {
		// Simulate legacy event details HTML that might come from old logger functions
		$legacy_html = '<div class="legacy-details">' .
		               '<strong>Changed setting:</strong> from "disabled" to "enabled"<br>' .
		               '<strong>User:</strong> admin<br>' .
		               '<strong>IP:</strong> 192.168.1.1' .
		               '</div>';
		
		$container = new Event_Details_Simple_Container( $legacy_html );
		
		$this->assertEquals( $legacy_html, $container->to_html(), 'Should preserve legacy HTML exactly' );
		$this->assertEquals( [], $container->to_json(), 'Legacy HTML should not have JSON representation' );
		$this->assertStringContainsString( 'Changed setting', (string) $container, '__toString should contain legacy content' );
	}

	public function test_html_with_special_characters() {
		$html = '<p>Content with &lt;special&gt; &amp; "quoted" characters &#39;apostrophe&#39;</p>';
		$container = new Event_Details_Simple_Container( $html );
		
		$this->assertEquals( $html, $container->to_html(), 'Should handle HTML entities correctly' );
		$this->assertEquals( $html, (string) $container, '__toString should handle HTML entities correctly' );
	}

	public function test_interface_compliance() {
		$container = new Event_Details_Simple_Container( '<p>Test</p>' );
		
		// Verify all interface methods are implemented and return correct types
		$this->assertIsString( $container->to_html(), 'to_html should return string' );
		$this->assertIsArray( $container->to_json(), 'to_json should return array' );
		$this->assertIsString( $container->__toString(), '__toString should return string' );
	}

	public function test_wrapping_different_container_types() {
		// Test wrapping an empty container
		$empty_container = new Event_Details_Container();
		$simple_wrapper = new Event_Details_Simple_Container( $empty_container );
		$this->assertEquals( '', $simple_wrapper->to_html(), 'Wrapped empty container should return empty HTML' );
		
		// Test wrapping a container with content
		$item = (new Event_Details_Item( null, 'Wrapped Test' ))
			->set_new_value( 'Wrapped Value' )
			->set_formatter( new Event_Details_Item_Default_Formatter() );
		$content_container = new Event_Details_Container();
		$content_container->add_item( $item );
		
		$simple_wrapper = new Event_Details_Simple_Container( $content_container );
		$direct_html = $content_container->to_html();
		$wrapped_html = $simple_wrapper->to_html();
		
		$this->assertEquals( $direct_html, $wrapped_html, 'Wrapped container HTML should match original' );
	}

	public function test_string_casting_behavior() {
		$html_content = '<div>String casting test</div>';
		$container = new Event_Details_Simple_Container( $html_content );
		
		// Test explicit string casting
		$explicit_cast = (string) $container;
		$this->assertEquals( $html_content, $explicit_cast, 'Explicit string cast should work' );
		
		// Test implicit string usage (concatenation)
		$concatenated = 'Before: ' . $container . ' :After';
		$expected = 'Before: ' . $html_content . ' :After';
		$this->assertEquals( $expected, $concatenated, 'Implicit string usage should work' );
	}

	public function test_backward_compatibility_scenario() {
		// Simulate a scenario where old and new event details might be mixed
		
		// Old-style HTML string
		$old_style = '<p><strong>Old Style:</strong> Direct HTML string</p>';
		$old_container = new Event_Details_Simple_Container( $old_style );
		
		// New-style structured data wrapped in simple container
		$new_item = new Event_Details_Item( null, 'New Style' );
		$new_item->set_new_value( 'Structured data' );
		$new_group = new Event_Details_Group();
		$new_group->add_item( $new_item );
		$new_structured = new Event_Details_Container( $new_group );
		$wrapped_new = new Event_Details_Simple_Container( $new_structured );
		
		// Both should work through the same interface
		$this->assertInstanceOf( Event_Details_Container_Interface::class, $old_container, 'Old style should implement interface' );
		$this->assertInstanceOf( Event_Details_Container_Interface::class, $wrapped_new, 'Wrapped new style should implement interface' );
		
		$this->assertEquals( $old_style, $old_container->to_html(), 'Old style HTML should be preserved' );
		$this->assertNotEmpty( $wrapped_new->to_html(), 'New style should generate HTML' );
		
		$this->assertEquals( [], $old_container->to_json(), 'Old style should have empty JSON' );
		$this->assertEquals( [], $wrapped_new->to_json(), 'Wrapped new style should still have empty JSON in simple container' );
	}
}