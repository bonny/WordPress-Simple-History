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
			'Admin_Page_Premium_Promo',
			'Review_Reminder_Service',
			'AddOns_Licences',
			'Setup_Database',
			'Scripts_And_Templates',
			'Admin_Pages',
			'Setup_Settings_Page',
			'Loggers_Loader',
			'Dropins_Loader',
			'Setup_Log_Filters',
			'Setup_Pause_Resume_Actions',
			'WP_CLI_Commands',
			'Setup_Purge_DB_Cron',
			'API',
			'Dashboard_Widget',
			'Network_Menu_Items',
			'Plugin_List_Link',
			'Licences_Settings_Page',
			'Plugin_List_Info',
			'REST_API',
			'Stealth_Mode',
			'Menu_Service',
		];

		$this->assertEqualsCanonicalizing($expected_slugs, $actual_slugs);
	}
}
