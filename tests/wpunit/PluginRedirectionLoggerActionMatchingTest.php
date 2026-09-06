<?php

use Simple_History\Loggers\Plugin_Redirection_Logger;

/**
 * Tests for Plugin_Redirection_Logger::get_redirection_action_for_callable().
 *
 * Redirection 5.10.0 moved its REST callbacks from legacy classes like
 * Redirection_Api_Redirect into namespaced ones like Redirection\Api\Route\Redirect.
 * This covers that both spellings still resolve to the right logging action, and
 * that unrelated or unknown callables are rejected.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginRedirectionLoggerActionMatchingTest
 */
class PluginRedirectionLoggerActionMatchingTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Every supported callable in both spellings, with the action it maps to.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function supported_callables_provider() {
		$cases = array();

		$class_pairs = array(
			'redirect' => array( 'Redirection_Api_Redirect', 'Redirection\\Api\\Route\\Redirect' ),
			'group'    => array( 'Redirection_Api_Group', 'Redirection\\Api\\Route\\Group' ),
			'settings' => array( 'Redirection_Api_Settings', 'Redirection\\Api\\Route\\Settings' ),
		);

		$expected = array(
			'redirect' => array( 'route_bulk' => 'redirect_bulk', 'route_create' => 'redirect_create', 'route_update' => 'redirect_update' ),
			'group'    => array( 'route_bulk' => 'group_bulk', 'route_create' => 'group_create', 'route_update' => 'group_update' ),
			'settings' => array( 'route_save_settings' => 'settings_save' ),
		);

		foreach ( $class_pairs as $entity => $class_names ) {
			foreach ( $class_names as $class_name ) {
				foreach ( $expected[ $entity ] as $method => $action ) {
					$cases[ "{$class_name}::{$method}" ] = array( "{$class_name}::{$method}", $action );
				}
			}
		}

		return $cases;
	}

	/**
	 * @dataProvider supported_callables_provider
	 */
	function test_supported_callables_match( $callable_name, $expected_action ) {
		$this->assertSame( $expected_action, Plugin_Redirection_Logger::get_redirection_action_for_callable( $callable_name ) );
	}

	function test_unrelated_callable_returns_null() {
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'Some_Other_Plugin::route_create' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'WP_REST_Posts_Controller::create_item' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'a_plain_function' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'closure' ) );
	}

	function test_namespaced_class_with_unknown_method_returns_null() {
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'Redirection\Api\Route\Redirect::route_list' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'Redirection\Api\Route\Settings::route_settings' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'Redirection\Api\Route\Group::route_dropdown' ) );
	}

	function test_namespaced_class_with_method_valid_for_different_entity_returns_null() {
		// route_bulk is a real method, but only for the Redirect and Group entities, not Settings.
		$this->assertNull( Plugin_Redirection_Logger::get_redirection_action_for_callable( 'Redirection\Api\Route\Settings::route_bulk' ) );
	}

	/**
	 * Redirection 5.10 sends `global: true` with no `items` when the user picks
	 * "select all" on the redirects or groups list, so those bulk actions need
	 * their own message keys.
	 */
	function test_global_bulk_message_keys_for_redirects() {
		$this->assertSame( 'redirection_redirection_enabled_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', 'enable' ) );
		$this->assertSame( 'redirection_redirection_disabled_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', 'disable' ) );
		$this->assertSame( 'redirection_redirection_deleted_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', 'delete' ) );
		$this->assertSame( 'redirection_redirection_reset_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', 'reset' ) );
	}

	function test_global_bulk_message_keys_for_groups() {
		$this->assertSame( 'redirection_group_enabled_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'group', 'enable' ) );
		$this->assertSame( 'redirection_group_disabled_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'group', 'disable' ) );
		$this->assertSame( 'redirection_group_deleted_all', Plugin_Redirection_Logger::get_global_bulk_message_key( 'group', 'delete' ) );
	}

	/**
	 * Groups have no `reset` bulk action in Redirection's route regex
	 * (`/bulk/group/(?P<bulk>delete|enable|disable)`), and an unknown or
	 * missing bulk action must never pick a key.
	 */
	function test_unknown_global_bulk_combinations_return_null() {
		$this->assertNull( Plugin_Redirection_Logger::get_global_bulk_message_key( 'group', 'reset' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', 'something-else' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_global_bulk_message_key( 'not-an-entity', 'enable' ) );
		$this->assertNull( Plugin_Redirection_Logger::get_global_bulk_message_key( 'redirect', null ) );
	}

	/**
	 * Every message key the global bulk actions can pick must exist in the
	 * logger's own messages, or the event would render as a raw key.
	 */
	function test_every_global_bulk_message_key_is_registered() {
		$logger = \Simple_History\Simple_History::get_instance()->get_instantiated_logger_by_slug( 'Plugin_Redirection' );

		$messages = $logger->get_info()['messages'];

		foreach ( Plugin_Redirection_Logger::GLOBAL_BULK_MESSAGE_KEYS as $entity => $actions ) {
			foreach ( $actions as $message_key ) {
				$this->assertArrayHasKey( $message_key, $messages, "Message key {$message_key} for {$entity} is not registered" );
			}
		}
	}
}
