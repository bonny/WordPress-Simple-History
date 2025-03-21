<?php

use Simple_History\Simple_History;
use Simple_History\Helpers;

class FunctionsTest extends \Codeception\TestCase\WPTestCase {
	private $simple_history;

	/**
	 * Get Simple History instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();
	}

	function test_class_functions() {
		$this->assertEquals(
			'http://localhost:8080/wp-admin/admin.php?page=simple_history_admin_menu_page', 
			Helpers::get_history_admin_url()
		);
	}
}
