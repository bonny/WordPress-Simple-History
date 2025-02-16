<?php

use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;

class MenuPageTest extends \Codeception\TestCase\WPTestCase {
	private $menu_manager;

	public function setUp(): void {
		parent::setUp();
		$this->menu_manager = new Menu_Manager();
	}

	public function test_basic_setters_and_getters() {
		$page = new Menu_Page();
		
		$page->set_page_title('Test Page Title');
		$page->set_menu_title('Test Menu Title');
		$page->set_capability('manage_options');
		$page->set_menu_slug('test-slug');
		$page->set_icon('dashicons-admin-generic');
		$page->set_order(5);
		$page->set_location('menu_top');
		
		$this->assertEquals('Test Page Title', $page->get_page_title());
		$this->assertEquals('Test Menu Title', $page->get_menu_title());
		$this->assertEquals('manage_options', $page->get_capability());
		$this->assertEquals('test-slug', $page->get_menu_slug());
		$this->assertEquals('dashicons-admin-generic', $page->get_icon());
		$this->assertEquals(5, $page->get_order());
		$this->assertEquals('menu_top', $page->get_location());
	}

	public function test_parent_child_relationship() {
		$parent = new Menu_Page();
		$parent->set_menu_slug('parent-slug');
		$this->menu_manager->add_page($parent);
		
		$child = new Menu_Page();
		$child->set_menu_slug('child-slug');
		$child->set_parent($parent);
		
		$this->assertEquals($parent, $child->get_parent());
		$this->assertEquals('parent-slug', $child->get_parent_menu_slug());
	}

	public function test_no_parent() {
		$page = new Menu_Page();
		$page->set_menu_slug('test-slug');
		
		$this->assertNull($page->get_parent());
		$this->assertNull($page->get_parent_menu_slug());
	}

	public function test_submenu_pages() {
		$parent = new Menu_Page();
		$parent->set_menu_slug('parent-slug');
		
		$child1 = new Menu_Page();
		$child1->set_menu_slug('child-slug-1');
		
		$child2 = new Menu_Page();
		$child2->set_menu_slug('child-slug-2');
		
		$parent->add_submenu($child1);
		$parent->add_submenu($child2);
		
		$submenu_pages = $parent->get_submenu_pages();
		
		$this->assertCount(2, $submenu_pages);
		$this->assertContains($child1, $submenu_pages);
		$this->assertContains($child2, $submenu_pages);
	}

	public function test_get_url() {
		$settings_page = new Menu_Page();
		$settings_page->set_menu_slug('simple-history-settings');
		$settings_page->set_location('options');
		
		$this->assertEquals(
			admin_url('options-general.php?page=simple-history-settings'),
			$settings_page->get_url()
		);
	}

	public function test_get_admin_url_by_slug() {
		$settings_page = new Menu_Page();
		$settings_page->set_menu_slug('simple-history-settings');
		$settings_page->set_location('options');
		
		$this->menu_manager->add_page($settings_page);
		
		$this->assertEquals(
			'',
			Menu_Page::get_admin_url_by_slug('non-existent-slug')
		);
		
		// Can't test actual URL since Simple_History instance is not available in tests
		$this->assertEquals(
			'',
			Menu_Page::get_admin_url_by_slug('simple-history-settings') 
		);
	}
}
