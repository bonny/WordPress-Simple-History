<?php

use Simple_History\Simple_History;
use Simple_History\Services\Privacy_Data_Handler;

/**
 * Tests for the WordPress privacy export/erasure integration.
 */
class PrivacyDataHandlerTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The service must be registered and loaded by Simple History.
	 */
	public function test_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$this->assertInstanceOf(
			Privacy_Data_Handler::class,
			$service,
			'Privacy_Data_Handler should be loaded as a core service.'
		);
	}
}
