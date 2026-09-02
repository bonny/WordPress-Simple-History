<?php

use Simple_History\Simple_History;
use Simple_History\Services\Licences_Settings_Page;

/**
 * Issue 298: the license activation error was a raw pass-through of the
 * Lemon Squeezy API message. For the activation-limit case, which users
 * hit after restoring or migrating a site, it must explain what happened
 * and point to a way forward.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit LicencesSettingsPageActivationMessageTest
 */
class LicencesSettingsPageActivationMessageTest extends \Codeception\TestCase\WPTestCase {
	/** @var Licences_Settings_Page */
	private $page;

	public function setUp(): void {
		parent::setUp();

		$this->page = Simple_History::get_instance()->get_service( Licences_Settings_Page::class );
		$this->assertInstanceOf( Licences_Settings_Page::class, $this->page );
	}

	public function test_activation_limit_error_explains_and_links_to_help() {
		$html = $this->page->get_activation_error_message( 'This license key has reached the activation limit.' );

		$this->assertStringContainsString( 'already active on another site', $html );
		$this->assertStringContainsString( 'href="https://simple-history.com/contact/', $html );
		$this->assertStringContainsString( 'Settings → Licences', $html, 'Spelling must match the tab name' );

		// The notice already explains this error, so the raw API line and the
		// read-more link would only repeat it.
		$this->assertStringNotContainsString( '<code>', $html );
		$this->assertStringNotContainsString( 'Error info', $html );
		$this->assertStringNotContainsString( 'support/add-ons', $html );
	}

	public function test_other_errors_keep_the_generic_message() {
		$html = $this->page->get_activation_error_message( 'license_key not found.' );

		$this->assertStringContainsString( 'Could not activate license', $html );
		$this->assertStringContainsString( '<code>license_key not found.</code>', $html );
		$this->assertStringNotContainsString( 'already active on another site', $html );
	}

	public function test_api_message_is_escaped() {
		$html = $this->page->get_activation_error_message( '<script>alert(1)</script> not found' );

		$this->assertStringContainsString( 'alert(1)', $html, 'The raw message is still shown for unknown errors' );
		$this->assertStringNotContainsString( '<script>', $html );
	}
}
