<?php

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Services\Service;

class ServicesTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $simple_history;
	
	/**
	 * Get Simple History instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();
	}

	function test_services_loaded() {
		$services = $this->simple_history->get_instantiated_services();
		$actual_slugs = array_map(fn (Service $service) => $service->get_slug(), $services);

		$expected_slugs = [
			'Language_Loader',
			'Setup_Database',
			'Scripts_And_Templates',
			'Admin_Pages',
			'Setup_Settings_Page',
			'Loggers_Loader',
			'Dropins_Loader',
			'Setup_Log_Filters',
			'Setup_Pause_Resume_Actions',
			'Setup_Purge_DB_Cron',
			'API',
			'Dashboard_Widget',
			'Network_Menu_Items',
			'Plugin_List_Link',
			'Licences_Settings_Page',
			'Plus_Licences',
		];

		$this->assertEqualsCanonicalizing($expected_slugs, $actual_slugs);
	}
}
