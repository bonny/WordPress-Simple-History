<?php

use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;

class MenuManagerTest extends \Codeception\TestCase\WPTestCase {
	private $menu_manager;

	public function setUp(): void {
		parent::setUp();
		$this->menu_manager = new Menu_Manager();
	}

	public function test_add_page() {
		$page = new Menu_Page();
		$page->set_menu_slug('test-slug');
		
		$this->menu_manager->add_page($page);
		
		$this->assertEquals(
			$page,
			$this->menu_manager->get_page_by_slug('test-slug')
		);
	}

	public function test_get_page_by_slug() {
		$page1 = new Menu_Page();
		$page1->set_menu_slug('test-slug-1');
		
		$page2 = new Menu_Page();
		$page2->set_menu_slug('test-slug-2');
		
		$this->menu_manager->add_page($page1);
		$this->menu_manager->add_page($page2);
		
		$this->assertEquals($page1, $this->menu_manager->get_page_by_slug('test-slug-1'));
		$this->assertEquals($page2, $this->menu_manager->get_page_by_slug('test-slug-2'));
		$this->assertNull($this->menu_manager->get_page_by_slug('non-existent-slug'));
	}

	public function test_get_pages() {
		$page1 = new Menu_Page();
		$page2 = new Menu_Page();
		
		$this->menu_manager->add_page($page1);
		$this->menu_manager->add_page($page2);
		
		$pages = $this->menu_manager->get_pages();
		
		$this->assertCount(2, $pages);
		$this->assertContains($page1, $pages);
		$this->assertContains($page2, $pages);
	}

	public function test_get_all_slugs() {
		$page1 = new Menu_Page();
		$page1->set_menu_slug('test-slug-1');
		
		$page2 = new Menu_Page();
		$page2->set_menu_slug('test-slug-2');
		
		$this->menu_manager->add_page($page1);
		$this->menu_manager->add_page($page2);
		
		$slugs = $this->menu_manager->get_all_slugs();
		
		$this->assertCount(2, $slugs);
		$this->assertContains('test-slug-1', $slugs);
		$this->assertContains('test-slug-2', $slugs);
	}
}
