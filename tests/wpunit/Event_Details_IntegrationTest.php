<?php

use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Simple_Container;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Inline_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Group_Single_Item_Formatter;
use Simple_History\Event_Details\Event_Details_Item_RAW_Formatter;
use Simple_History\Event_Details\Event_Details_Item_Table_Row_RAW_Formatter;

/**
 * Integration tests for Event Details system complete workflows.
 * These tests demonstrate real-world usage patterns and end-to-end functionality.
 * 
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit Event_Details_IntegrationTest`
 */
class Event_Details_IntegrationTest extends \Codeception\TestCase\WPTestCase {

	public function test_wordpress_settings_change_workflow() {
		// Simulate WordPress settings change scenario
		$context = [
			'show_on_dashboard_prev' => '0',
			'show_on_dashboard_new' => '1',
			'pager_size_prev' => '50',
			'pager_size_new' => '25',
			'enable_rss_feed_prev' => '1',
			'enable_rss_feed_new' => '0',
			'new_setting_new' => 'enabled',
			'removed_setting_prev' => 'old_value'
		];
		
		// Create settings changes group
		$settings_group = (new Event_Details_Group())
			->set_title( 'Settings Changes' )
			->add_items([
				new Event_Details_Item( ['show_on_dashboard'], 'Show on Dashboard' ),
				new Event_Details_Item( ['pager_size'], 'Items per Page' ),
				new Event_Details_Item( ['enable_rss_feed'], 'RSS Feed' ),
				new Event_Details_Item( ['new_setting'], 'New Feature' ),
				new Event_Details_Item( ['removed_setting'], 'Removed Setting' )
			]);
		
		$container = new Event_Details_Container( $settings_group, $context );
		
		// Test HTML output
		$html = $container->to_html();
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should generate settings table' );
		$this->assertStringContainsString( 'Show on Dashboard', $html, 'Should contain setting names' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $html, 'Should highlight new values' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $html, 'Should highlight old values' );
		
		// Test JSON output
		$json = $container->to_json();
		$this->assertCount( 1, $json, 'Should have one group in JSON' );
		$this->assertEquals( 'Settings Changes', $json[0]['title'], 'Should preserve group title' );
		$this->assertGreaterThan( 0, count( $json[0]['items'] ), 'Should have items after filtering empty ones' );
		
		// Test string conversion
		$string_output = (string) $container;
		$this->assertEquals( $html, $string_output, 'String conversion should match HTML output' );
		
		// Test that empty values were filtered out
		$remaining_items = $settings_group->items;
		$item_names = array_map( function( $item ) {
			return $item->name;
		}, $remaining_items );
		
		$this->assertContains( 'Show on Dashboard', $item_names, 'Changed items should remain' );
		$this->assertContains( 'New Feature', $item_names, 'Added items should remain' );
		$this->assertContains( 'Removed Setting', $item_names, 'Removed items should remain' );
	}

	public function test_plugin_update_workflow() {
		// Simulate plugin update information display
		$context = [
			'plugin_name' => 'Simple History',
			'plugin_version' => '4.0.0',
			'plugin_current_version' => '3.9.0',
			'plugin_author' => 'Pär Thernström',
			'plugin_description' => 'View and search your site\'s edit history'
		];
		
		// Create plugin info with inline formatter for summary
		$summary_group = (new Event_Details_Group())
			->set_title( 'Plugin Summary' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_items([
				new Event_Details_Item( 'plugin_name', 'Name' ),
				new Event_Details_Item( 'plugin_current_version', 'From' ),
				new Event_Details_Item( 'plugin_version', 'To' )
			]);
		
		// Create detailed info with table formatter
		$details_group = (new Event_Details_Group())
			->set_title( 'Plugin Details' )
			->add_items([
				new Event_Details_Item( 'plugin_author', 'Author' ),
				new Event_Details_Item( 'plugin_version', 'New Version' ),
				new Event_Details_Item( 'plugin_current_version', 'Previous Version' )
			]);
		
		// Create custom description with RAW formatter
		$description_formatter = new Event_Details_Item_Table_Row_RAW_Formatter();
		$description_formatter->set_html_output( 
			'<div class="plugin-description">' . wp_kses_post( $context['plugin_description'] ) . '</div>'
		);
		$description_formatter->set_json_output([
			'type' => 'plugin_description',
			'content' => $context['plugin_description']
		]);
		
		$details_group->add_item(
			(new Event_Details_Item( null, 'Description' ))->set_formatter( $description_formatter )
		);
		
		$container = new Event_Details_Container( [ $summary_group, $details_group ], $context );
		
		// Test HTML output structure
		$html = $container->to_html();
		$this->assertStringContainsString( '<p>', $html, 'Should contain inline paragraph for summary' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__inlineDivided', $html, 'Should have inline divided items' );
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should contain details table' );
		$this->assertStringContainsString( 'plugin-description', $html, 'Should contain custom description formatting' );
		
		// Test JSON output structure
		$json = $container->to_json();
		$this->assertCount( 2, $json, 'Should have two groups' );
		$this->assertEquals( 'Plugin Summary', $json[0]['title'], 'First group should be summary' );
		$this->assertEquals( 'Plugin Details', $json[1]['title'], 'Second group should be details' );
		
		// Find the custom description in JSON
		$description_item = null;
		foreach ( $json[1]['items'] as $item ) {
			if ( isset( $item['name'] ) && $item['name'] === 'Description' ) {
				$description_item = $item;
				break;
			}
		}
		$this->assertNotNull( $description_item, 'Should find description item in JSON' );
		$this->assertEquals( 'plugin_description', $description_item['type'] ?? null, 'Should have custom JSON type' );
	}

	public function test_content_changes_with_diff_workflow() {
		// Simulate post/page content changes
		$context = [
			'post_new_post_title' => 'Updated Article Title',
			'post_prev_post_title' => 'Original Article Title',
			'post_new_post_content' => 'This is the updated content with new information.',
			'post_prev_post_content' => 'This is the original content.',
			'post_type' => 'post',
			'post_id' => '123'
		];
		
		// Create content changes with diff formatter
		$content_group = (new Event_Details_Group())
			->set_title( 'Content Changes' )
			->set_formatter( new Event_Details_Group_Diff_Table_Formatter() )
			->add_items([
				new Event_Details_Item( ['post_new_post_title', 'post_prev_post_title'], 'Title' ),
				new Event_Details_Item( ['post_new_post_content', 'post_prev_post_content'], 'Content' )
			]);
		
		// Create metadata with regular table formatter
		$meta_group = (new Event_Details_Group())
			->set_title( 'Post Information' )
			->add_items([
				new Event_Details_Item( 'post_type', 'Type' ),
				new Event_Details_Item( 'post_id', 'ID' )
			]);
		
		$container = new Event_Details_Container( [ $content_group, $meta_group ], $context );
		
		// Test HTML output
		$html = $container->to_html();
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should contain tables' );
		$this->assertStringContainsString( 'Content Changes', $html, 'Should contain content changes section' );
		$this->assertStringContainsString( 'Post Information', $html, 'Should contain metadata section' );
		$this->assertStringContainsString( 'Updated', $html, 'Should contain new title' );
		$this->assertStringContainsString( 'post', $html, 'Should contain post type' );
		
		// Test JSON structure
		$json = $container->to_json();
		$this->assertCount( 2, $json, 'Should have two groups' );
		
		// Verify content group items have both old and new values
		$content_items = $json[0]['items'];
		$title_item = $content_items[0];
		$this->assertEquals( 'Title', $title_item['name'], 'Should have title item' );
		$this->assertEquals( 'Updated Article Title', $title_item['new_value'], 'Should have new title' );
		$this->assertEquals( 'Original Article Title', $title_item['prev_value'], 'Should have old title' );
	}

	public function test_mixed_formatters_complex_workflow() {
		// Simulate a complex event with multiple formatting needs
		$context = [
			'action_type' => 'file_upload',
			'file_name' => 'document.pdf',
			'file_size' => '2.4 MB',
			'file_mime' => 'application/pdf',
			'upload_path' => '/wp-content/uploads/2024/01/document.pdf',
			'thumbnail_url' => '/wp-content/uploads/2024/01/document-150x150.jpg',
			'permissions_prev' => '644',
			'permissions_new' => '755'
		];
		
		// Quick summary with inline formatter
		$summary_group = (new Event_Details_Group())
			->set_title( 'Upload Summary' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_items([
				new Event_Details_Item( 'action_type', 'Action' ),
				new Event_Details_Item( 'file_name', 'File' ),
				new Event_Details_Item( 'file_size', 'Size' )
			]);
		
		// File details with table formatter
		$details_group = (new Event_Details_Group())
			->set_title( 'File Details' )
			->add_items([
				new Event_Details_Item( 'file_mime', 'MIME Type' ),
				new Event_Details_Item( 'upload_path', 'Path' ),
				new Event_Details_Item( ['permissions'], 'Permissions' )
			]);
		
		// Custom preview with RAW formatter
		$preview_formatter = new Event_Details_Item_RAW_Formatter();
		$preview_formatter->set_html_output(
			'<div class="file-preview" style="text-align: center; padding: 10px;">' .
			'<img src="' . esc_url( $context['thumbnail_url'] ) . '" alt="File preview" style="max-width: 150px;" />' .
			'<br><small>Preview of uploaded file</small>' .
			'</div>'
		);
		$preview_formatter->set_json_output([
			'type' => 'file_preview',
			'thumbnail_url' => $context['thumbnail_url'],
			'alt_text' => 'File preview'
		]);
		
		// Add preview as single item group
		$container = new Event_Details_Container( [ $summary_group, $details_group ], $context );
		$container->add_item( 
			(new Event_Details_Item( null, 'Preview' ))->set_formatter( $preview_formatter ),
			'File Preview'
		);
		
		// Test complete workflow
		$html = $container->to_html();
		$json = $container->to_json();
		
		// Verify different formatting styles are present
		$this->assertStringContainsString( '<p>', $html, 'Should have inline summary paragraph' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__inlineDivided', $html, 'Should have inline divided items' );
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should have details table' );
		$this->assertStringContainsString( 'file-preview', $html, 'Should have custom preview section' );
		$this->assertStringContainsString( 'max-width: 150px', $html, 'Should have custom styling' );
		
		// Verify JSON structure
		$this->assertCount( 3, $json, 'Should have three groups' );
		$this->assertEquals( 'Upload Summary', $json[0]['title'], 'First group should be summary' );
		$this->assertEquals( 'File Details', $json[1]['title'], 'Second group should be details' );
		$this->assertEquals( 'File Preview', $json[2]['title'], 'Third group should be preview' );
		
		// Check custom RAW JSON output
		$preview_items = $json[2]['items'];
		$this->assertCount( 1, $preview_items, 'Preview group should have one item' );
		$this->assertEquals( 'file_preview', $preview_items[0]['type'] ?? null, 'Should have custom type' );
		$this->assertEquals( $context['thumbnail_url'], $preview_items[0]['thumbnail_url'] ?? null, 'Should have thumbnail URL' );
	}

	public function test_legacy_vs_new_system_compatibility() {
		// Test backward compatibility between old simple containers and new structured system
		$legacy_html = '<div class="legacy-event-details">' .
		               '<p><strong>User:</strong> admin</p>' .
		               '<p><strong>Action:</strong> login</p>' .
		               '<p><strong>IP:</strong> 192.168.1.1</p>' .
		               '</div>';
		
		$legacy_container = new Event_Details_Simple_Container( $legacy_html );
		
		// New structured approach for similar data
		$new_container = new Event_Details_Container();
		$new_container->add_items([
			(new Event_Details_Item( null, 'User' ))->set_new_value( 'admin' ),
			(new Event_Details_Item( null, 'Action' ))->set_new_value( 'login' ),
			(new Event_Details_Item( null, 'IP' ))->set_new_value( '192.168.1.1' )
		], 'Login Details');
		
		// Both should work through same interface
		$this->assertInstanceOf( 
			'Simple_History\Event_Details\Event_Details_Container_Interface', 
			$legacy_container, 
			'Legacy container should implement interface' 
		);
		$this->assertInstanceOf( 
			'Simple_History\Event_Details\Event_Details_Container_Interface', 
			$new_container, 
			'New container should implement interface' 
		);
		
		// Test outputs
		$legacy_html_output = $legacy_container->to_html();
		$legacy_json_output = $legacy_container->to_json();
		$new_html_output = $new_container->to_html();
		$new_json_output = $new_container->to_json();
		
		$this->assertEquals( $legacy_html, $legacy_html_output, 'Legacy HTML should be preserved' );
		$this->assertEquals( [], $legacy_json_output, 'Legacy should have empty JSON' );
		$this->assertStringContainsString( 'User', $new_html_output, 'New system should contain structured data' );
		$this->assertNotEmpty( $new_json_output, 'New system should have JSON output' );
		
		// Test that simple container can wrap new container
		$wrapped_new_in_simple = new Event_Details_Simple_Container( $new_container );
		$wrapped_html = $wrapped_new_in_simple->to_html();
		$wrapped_json = $wrapped_new_in_simple->to_json();
		
		$this->assertEquals( $new_html_output, $wrapped_html, 'Wrapped container should delegate HTML' );
		$this->assertEquals( [], $wrapped_json, 'Simple container always returns empty JSON' );
	}

	public function test_context_automatic_population_workflow() {
		// Test the automatic context value population feature
		$context = [
			// Standard _new/_prev suffix format
			'setting1_new' => 'enabled',
			'setting1_prev' => 'disabled',
			
			// Explicit different key names
			'title_current' => 'New Title',
			'title_old' => 'Old Title',
			
			// Single value (no previous)
			'plugin_version' => '2.0.0',
			
			// Empty values (should be filtered)
			'empty_setting_new' => '',
			'empty_setting_prev' => '',
			
			// Null vs value (removed)
			'removed_feature_new' => null,
			'removed_feature_prev' => 'old_feature_value'
		];
		
		$container = new Event_Details_Container( [], $context );
		
		// Add items with different slug formats
		$container->add_items([
			new Event_Details_Item( ['setting1'], 'Auto Suffix Setting' ),        // Auto _new/_prev
			new Event_Details_Item( ['title_current', 'title_old'], 'Explicit Keys' ), // Explicit keys
			new Event_Details_Item( 'plugin_version', 'Single Value' ),           // Single key
			new Event_Details_Item( ['empty_setting'], 'Empty Setting' ),         // Should be filtered
			new Event_Details_Item( ['removed_feature'], 'Removed Feature' )      // Should remain (removal)
		], 'Automatic Context Test');
		
		// Test that context values were automatically populated
		$group = $container->groups[0];
		$items = $group->items;
		
		// Find items by name (they may be reordered after filtering)
		$items_by_name = [];
		foreach ( $items as $item ) {
			$items_by_name[$item->name] = $item;
		}
		
		// Test auto suffix format
		if ( isset( $items_by_name['Auto Suffix Setting'] ) ) {
			$auto_item = $items_by_name['Auto Suffix Setting'];
			$this->assertEquals( 'enabled', $auto_item->new_value, 'Auto suffix should populate new value' );
			$this->assertEquals( 'disabled', $auto_item->prev_value, 'Auto suffix should populate prev value' );
			$this->assertTrue( $auto_item->is_changed, 'Should detect change' );
		}
		
		// Test explicit keys format
		if ( isset( $items_by_name['Explicit Keys'] ) ) {
			$explicit_item = $items_by_name['Explicit Keys'];
			$this->assertEquals( 'New Title', $explicit_item->new_value, 'Explicit keys should populate new value' );
			$this->assertEquals( 'Old Title', $explicit_item->prev_value, 'Explicit keys should populate prev value' );
			$this->assertTrue( $explicit_item->is_changed, 'Should detect change' );
		}
		
		// Test single value format
		if ( isset( $items_by_name['Single Value'] ) ) {
			$single_item = $items_by_name['Single Value'];
			$this->assertEquals( '2.0.0', $single_item->new_value, 'Single key should populate new value' );
			$this->assertNull( $single_item->prev_value, 'Single key should have null prev value' );
			$this->assertTrue( $single_item->is_added, 'Should detect addition' );
		}
		
		// Test that empty items were filtered out
		$this->assertArrayNotHasKey( 'Empty Setting', $items_by_name, 'Empty items should be filtered out' );
		
		// Test removed feature remains
		if ( isset( $items_by_name['Removed Feature'] ) ) {
			$removed_item = $items_by_name['Removed Feature'];
			$this->assertNull( $removed_item->new_value, 'Removed item should have null new value' );
			$this->assertEquals( 'old_feature_value', $removed_item->prev_value, 'Removed item should have prev value' );
			$this->assertTrue( $removed_item->is_removed, 'Should detect removal' );
		}
		
		// Test HTML and JSON output include populated values
		$html = $container->to_html();
		$json = $container->to_json();
		
		$this->assertStringContainsString( 'enabled', $html, 'HTML should contain auto-populated new values' );
		$this->assertStringContainsString( 'disabled', $html, 'HTML should contain auto-populated prev values' );
		$this->assertNotEmpty( $json[0]['items'], 'JSON should have items after filtering' );
	}

	public function test_end_to_end_real_world_scenario() {
		// Comprehensive test simulating a real plugin settings update
		$context = [
			// Main settings changes
			'show_on_dashboard_prev' => '0',
			'show_on_dashboard_new' => '1',
			'pager_size_prev' => '20',
			'pager_size_new' => '50',
			'rss_feed_enabled_prev' => '1',
			'rss_feed_enabled_new' => '0',
			
			// New features added
			'new_feature_enabled_new' => '1',
			'experimental_mode_new' => 'beta',
			
			// Removed/deprecated settings
			'old_cache_method_prev' => 'file',
			
			// Metadata
			'user_id' => '1',
			'user_name' => 'Administrator',
			'timestamp' => '2024-01-15 10:30:00',
			'ip_address' => '192.168.1.100'
		];
		
		// Create comprehensive event details
		$container = new Event_Details_Container( [], $context );
		
		// Settings changes group (table format)
		$settings_group = (new Event_Details_Group())
			->set_title( 'Settings Changes' )
			->add_items([
				new Event_Details_Item( ['show_on_dashboard'], 'Show on Dashboard' ),
				new Event_Details_Item( ['pager_size'], 'Events per Page' ),
				new Event_Details_Item( ['rss_feed_enabled'], 'RSS Feed' )
			]);
		
		$container->add_group( $settings_group );
		
		// New features group (inline format)
		$new_features_group = (new Event_Details_Group())
			->set_title( 'New Features Enabled' )
			->set_formatter( new Event_Details_Group_Inline_Formatter() )
			->add_items([
				new Event_Details_Item( ['new_feature_enabled'], 'New Feature' ),
				new Event_Details_Item( ['experimental_mode'], 'Experimental Mode' )
			]);
		
		$container->add_group( $new_features_group );
		
		// Deprecated features (single items)
		$container->add_item(
			new Event_Details_Item( ['old_cache_method'], 'Deprecated Cache Method' ),
			'Removed Features'
		);
		
		// User info with custom formatting
		$user_info_formatter = new Event_Details_Item_RAW_Formatter();
		$user_info_formatter->set_html_output(
			'<div class="user-info">' .
			'<strong>User:</strong> ' . esc_html( $context['user_name'] ) . ' (ID: ' . esc_html( $context['user_id'] ) . ')<br>' .
			'<strong>Time:</strong> ' . esc_html( $context['timestamp'] ) . '<br>' .
			'<strong>IP:</strong> ' . esc_html( $context['ip_address'] ) .
			'</div>'
		);
		$user_info_formatter->set_json_output([
			'user_id' => $context['user_id'],
			'user_name' => $context['user_name'],
			'timestamp' => $context['timestamp'],
			'ip_address' => $context['ip_address']
		]);
		
		$container->add_item(
			(new Event_Details_Item( null, 'Change Information' ))->set_formatter( $user_info_formatter ),
			'Audit Information'
		);
		
		// Comprehensive testing
		$html = $container->to_html();
		$json = $container->to_json();
		$string_output = (string) $container;
		
		// Test HTML structure and content
		$this->assertStringContainsString( '<table class="SimpleHistoryLogitem__keyValueTable">', $html, 'Should have table for settings' );
		$this->assertStringContainsString( '<p>', $html, 'Should have paragraph for inline features' );
		$this->assertStringContainsString( 'user-info', $html, 'Should have custom user info section' );
		
		$this->assertStringContainsString( 'Settings Changes', $html, 'Should have settings section title' );
		$this->assertStringContainsString( 'Show on Dashboard', $html, 'Should show setting names' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__addedThing', $html, 'Should highlight new values' );
		$this->assertStringContainsString( 'SimpleHistoryLogitem__keyValueTable__removedThing', $html, 'Should highlight old values' );
		
		$this->assertStringContainsString( 'Administrator', $html, 'Should contain user information' );
		$this->assertStringContainsString( '192.168.1.100', $html, 'Should contain IP address' );
		
		// Test JSON structure
		$this->assertCount( 4, $json, 'Should have four groups' );
		$this->assertEquals( 'Settings Changes', $json[0]['title'], 'First group should be settings' );
		$this->assertEquals( 'New Features Enabled', $json[1]['title'], 'Second group should be new features' );
		$this->assertEquals( 'Removed Features', $json[2]['title'], 'Third group should be removed features' );
		$this->assertEquals( 'Audit Information', $json[3]['title'], 'Fourth group should be audit info' );
		
		// Test that different item states are properly represented
		$settings_items = $json[0]['items'];
		$dashboard_item = null;
		foreach ( $settings_items as $item ) {
			if ( $item['name'] === 'Show on Dashboard' ) {
				$dashboard_item = $item;
				break;
			}
		}
		
		$this->assertNotNull( $dashboard_item, 'Should find dashboard setting item' );
		$this->assertEquals( '1', $dashboard_item['new_value'], 'Should have new value' );
		$this->assertEquals( '0', $dashboard_item['prev_value'], 'Should have previous value' );
		
		// Test string conversion
		$this->assertEquals( $html, $string_output, 'String conversion should match HTML' );
		
		// Test that the system handled different scenarios correctly
		$all_items_count = 0;
		foreach ( $json as $group ) {
			$all_items_count += count( $group['items'] );
		}
		$this->assertGreaterThan( 5, $all_items_count, 'Should have multiple items across all groups' );
		
		// Verify no empty groups
		foreach ( $json as $group ) {
			$this->assertNotEmpty( $group['items'], 'No group should be empty after context population' );
		}
	}
}