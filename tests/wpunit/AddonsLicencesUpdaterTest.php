<?php
// tests/wpunit/AddonsLicencesUpdaterTest.php

use Simple_History\Simple_History;
use Simple_History\Services\AddOns_Licences;
use Simple_History\Plugin_Updater;

/**
 * The Licenses tab refreshes the update-check on every visit, which needs
 * access to the Plugin_Updater instance created for each registered add-on.
 */
class AddonsLicencesUpdaterTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG = 'sh-test-addon';

	public function test_get_updater_returns_the_instance_for_a_registered_plugin() {
		$service = Simple_History::get_instance()->get_service( AddOns_Licences::class );

		$this->assertInstanceOf( AddOns_Licences::class, $service );

		$service->register_plugin_for_license( self::SLUG . '/index.php', self::SLUG, '1.0.0', 'Test add-on', 1 );

		// This is the hook AddOns_Licences::loaded() uses to create an
		// updater for every registered plugin; call the method it triggers
		// directly instead of firing the action so this test does not also
		// re-create updaters for every already-registered real add-on.
		$service->init_plugin_updater_for_registered_licence_plugins();

		$updater = $service->get_updater( self::SLUG );

		$this->assertInstanceOf( Plugin_Updater::class, $updater );
		$this->assertSame( self::SLUG, $updater->plugin_slug );

		$this->assertNull( $service->get_updater( 'nope' ) );
	}
}
