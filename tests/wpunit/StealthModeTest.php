<?php

use Simple_History\Simple_History;
use Simple_History\Services;

class StealthModeTest extends \Codeception\TestCase\WPTestCase {
	protected ?Simple_History $simple_history;
	protected ?Services\Stealth_Mode $stealth_mode_service;

	/** 
	 * Load Stealth mode service for all tests.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();
		$this->stealth_mode_service = $this->simple_history->get_service( Services\Stealth_Mode::class );
	}

	function test_constants_not_set() {
		$this->assertFalse( defined( 'SIMPLE_HISTORY_STEALTH_MODE_ENABLE' ) );
		$this->assertFalse( defined( 'SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS' ) );
	}

	function test_class_exists() {
		$this->assertTrue( class_exists( 'Simple_History\Services\Stealth_Mode' ) );
	}

	function test_stealth_mode_class_methods() {
		$stealth_mode_service = $this->stealth_mode_service;
		
		$this->assertTrue( method_exists( $stealth_mode_service, 'is_gui_visible_to_user' ) );
		$this->assertTrue( method_exists( $stealth_mode_service, 'is_full_stealth_mode_enabled' ) );
		$this->assertTrue( method_exists( $stealth_mode_service, 'is_stealth_mode_enabled' ) );
		$this->assertTrue( method_exists( $stealth_mode_service, 'is_user_email_allowed_in_stealth_mode' ) );
	}

	function test_is_gui_visible_to_non_logged_in_user() {
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );
	}		

	function test_is_gui_visible_to_logged_in_user() {
		// Non-admin user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );

		// Admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );
	}

	function test_full_stealh_mode_default() {
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );

		// Non-admin user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );

		// Admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user() );
	}

	function test_full_stealh_mode_enable() {
		add_filter( 'simple_history/full_stealth_mode_enabled', '__return_true' );
		
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'No user should not see GUI when Full Stealth Mode is enabled' );

		// Non-admin user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'Non-admin user should not see GUI when Full Stealth Mode is enabled' );

		// Admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'Admin user should not see GUI when Full Stealth Mode is enabled' );
	}

	function test_stealth_mode_allowed_emails() {
		add_filter(
			'simple_history/stealth_mode_allowed_emails',
			function() {
				return [
					'@example.com',
					'@anotherdomain.org',
					'jane@organization.org',
					'john@organization.org',
				];
			}
		);

		// No user logged in.
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'When no one logged in, GUI should not be visible' );

		// Non-admin user.
		$user_id = $this->factory->user->create(  [ 'user_email' => 'someone@testdomain.com' ] );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'Non-admin user with email not in allowed list should not see GUI' );

		// Admin user with domain ending with @example.com should see log.
		$user_id = $this->factory->user->create(  [ 'user_email' => 'testuser@example.com' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user(), 'Admin user with email ending with @example.com should see GUI' );

		// Admin user with domain ending with @anotherdomain.org should see log.
		$user_id = $this->factory->user->create(  [ 'user_email' => 'anothertestuser@anotherdomain.org' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user(), 'Admin user with email ending with @anotherdomain.org should see GUI' );

		// Admin user with email ending with @organization should not see log.
		$user_id = $this->factory->user->create(  [ 'user_email' => 'user@organization.org' ] );
		wp_set_current_user( $user_id );
		$this->assertFalse( $this->stealth_mode_service->is_gui_visible_to_user(), 'Admin user with email ending with @organization.org should not see GUI' );

		// Admin user jane@organization should see log.
		$user_id = $this->factory->user->create(  [ 'user_email' => 'jane@organization.org' ] );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->stealth_mode_service->is_gui_visible_to_user(), 'Admin user with email jane@organization.org should see log' );
	}

	/**
	 * Dummy function to set const so Inteliphense does not show undefined constant error.
	 * 
	 * Does not run because it does not begin with test_.
	 */
	function set_const() {
		define( 'SIMPLE_HISTORY_STEALTH_MODE_ENABLE', true);
		define( 'SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS', '@example.com');
	}
}
