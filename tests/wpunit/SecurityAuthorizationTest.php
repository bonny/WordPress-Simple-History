<?php

use Simple_History\Helpers;

/**
 * Authorization and data-exposure regressions found in the August 2026
 * security review.
 *
 * Each test here maps to a finding that was live in the plugin. They are
 * capability boundaries rather than feature behaviour, so a change that
 * loosens one should fail loudly rather than pass quietly.
 */
class SecurityAuthorizationTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Create a user of the given role and make them the current user.
	 *
	 * @param string $role Role name.
	 * @return int User ID.
	 */
	private function login_as( $role ) {
		$user_id = $this->factory->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Dispatch a REST request and return the response.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route.
	 * @param array  $params Query params.
	 * @return \WP_REST_Response
	 */
	private function dispatch_request( $method, $route, $params = [] ) {
		$request = new WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Reading the log over REST requires the same capability as viewing the
	 * history page, not merely being logged in.
	 *
	 * Without this, any logged-in user reached /events and received events from
	 * every logger whose own capability they held — and some ship below the
	 * view floor (NotesLogger is edit_posts).
	 */
	public function test_events_endpoint_requires_view_history_capability() {
		$this->login_as( 'subscriber' );
		$this->assertSame( 403, $this->dispatch_request( 'GET', '/simple-history/v1/events' )->get_status(), 'Subscriber must not read events.' );

		$this->login_as( 'author' );
		$this->assertSame( 403, $this->dispatch_request( 'GET', '/simple-history/v1/events' )->get_status(), 'Author must not read events.' );

		$this->login_as( 'editor' );
		$this->assertSame( 200, $this->dispatch_request( 'GET', '/simple-history/v1/events' )->get_status(), 'Editor holds edit_pages and must still read events.' );

		$this->login_as( 'administrator' );
		$this->assertSame( 200, $this->dispatch_request( 'GET', '/simple-history/v1/events' )->get_status(), 'Administrator must still read events.' );
	}

	/**
	 * The has-updates route shares the collection permission callback, so it
	 * must inherit the same floor.
	 */
	public function test_has_updates_endpoint_requires_view_history_capability() {
		$this->login_as( 'subscriber' );

		$response = $this->dispatch_request( 'GET', '/simple-history/v1/events/has-updates', [ 'since_id' => 1 ] );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Search options carry site-wide data that is not filtered per logger —
	 * event counts, the add-on inventory, feature flags and the premium maps
	 * API key — so being logged in is not a sufficient gate.
	 */
	public function test_search_options_endpoint_requires_view_history_capability() {
		$this->login_as( 'subscriber' );

		$response = $this->dispatch_request( 'GET', '/simple-history/v1/search-options' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertArrayNotHasKey(
			'maps_api_key',
			(array) $response->get_data(),
			'A forbidden response must not carry the settings payload.'
		);
	}

	/**
	 * The user card is viewable at the history capability, but login, email and
	 * roles are PII that core only exposes with list_users. An editor holds
	 * edit_pages but not list_users, so they get the card without the identity
	 * fields.
	 */
	public function test_user_card_hides_pii_from_users_without_list_users() {
		$target = $this->factory->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'card_target_user',
				'user_email' => 'card_target@example.com',
			]
		);

		$this->login_as( 'editor' );
		$this->assertFalse( current_user_can( 'list_users' ), 'Precondition: editors lack list_users.' );

		$response = $this->dispatch_request( 'GET', "/simple-history/v1/users/{$target}/card" );
		$data     = (array) $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The card itself stays available to editors.' );
		$this->assertSame( '', $data['user_login'], 'Login must not leak to a caller without list_users.' );
		$this->assertSame( '', $data['user_email'], 'Email must not leak to a caller without list_users.' );
		$this->assertSame( [], $data['roles'], 'Roles must not leak to a caller without list_users.' );
		$this->assertNotEmpty( $data['display_name'], 'Display name stays — it is already exposed at this level.' );
	}

	/**
	 * Administrators hold list_users and must still get the full card,
	 * otherwise the fix would have broken the feature instead of scoping it.
	 */
	public function test_user_card_still_returns_pii_to_users_with_list_users() {
		$target = $this->factory->user->create(
			[
				'role'       => 'subscriber',
				'user_login' => 'card_target_admin_view',
				'user_email' => 'card_admin_view@example.com',
			]
		);

		$this->login_as( 'administrator' );

		$data = (array) $this->dispatch_request( 'GET', "/simple-history/v1/users/{$target}/card" )->get_data();

		$this->assertSame( 'card_target_admin_view', $data['user_login'] );
		$this->assertSame( 'card_admin_view@example.com', $data['user_email'] );
		$this->assertContains( 'subscriber', $data['roles'] );
	}

	/**
	 * Clearing truncates the events and contexts tables. It defaulted to open,
	 * leaving nonce possession as the only gate.
	 */
	public function test_user_can_clear_log_defaults_to_settings_capability() {
		$this->login_as( 'subscriber' );
		$this->assertFalse( Helpers::user_can_clear_log(), 'Subscriber must not clear the log.' );

		$this->login_as( 'editor' );
		$this->assertFalse( Helpers::user_can_clear_log(), 'Editor must not clear the log.' );

		$this->login_as( 'administrator' );
		$this->assertTrue( Helpers::user_can_clear_log(), 'Administrator must still be able to clear the log.' );
	}

	/**
	 * The initiator is the person who performed an event the reader may already
	 * see, so it stays visible: it is context for visible activity, not a
	 * directory, and it is what tells two people sharing a display name apart.
	 * The card is gated instead, because that answers arbitrary user ids.
	 */
	public function test_events_initiator_data_stays_visible_to_users_without_list_users() {
		$actor = $this->factory->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'initiator_actor',
				'user_email' => 'initiator_actor@example.com',
			]
		);

		wp_set_current_user( $actor );
		SimpleLogger()->info( 'Event used by the initiator_data test.' );

		$this->login_as( 'editor' );
		$this->assertFalse( current_user_can( 'list_users' ), 'Precondition: editors lack list_users.' );

		$events = (array) $this->dispatch_request( 'GET', '/simple-history/v1/events', [ 'per_page' => 20 ] )->get_data();

		$logins = array_column( array_column( $events, 'initiator_data' ), 'user_login' );

		$this->assertContains( 'initiator_actor', $logins, 'Editors must still see who performed an event they can read.' );
	}

	/**
	 * Administrators are unaffected.
	 */
	public function test_events_initiator_data_still_visible_to_users_with_list_users() {
		$actor = $this->factory->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'initiator_actor_admin_view',
				'user_email' => 'initiator_admin_view@example.com',
			]
		);

		wp_set_current_user( $actor );
		SimpleLogger()->info( 'Event used by the admin initiator_data test.' );

		$events = (array) $this->dispatch_request( 'GET', '/simple-history/v1/events', [ 'per_page' => 20 ] )->get_data();

		$logins = array_column( array_column( $events, 'initiator_data' ), 'user_login' );

		$this->assertContains( 'initiator_actor_admin_view', $logins, 'Administrator must still see initiator logins.' );
	}

	/**
	 * The bound on initiator PII is the log itself: an editor may see who
	 * performed an event only when they may read that event.
	 *
	 * Log_Query filters per logger, so an event from a logger the reader lacks
	 * the capability for never reaches prepare_item_for_response() and its
	 * initiator is never built. This asserts that end to end, because it is the
	 * property that makes leaving initiator_data ungated defensible — remove it
	 * and the ungated field becomes a way to read the address of anyone who
	 * ever acted on the site.
	 */
	public function test_initiator_pii_is_bounded_by_the_events_the_reader_may_see() {
		$actor = $this->factory->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'unreadable_event_actor',
				'user_email' => 'unreadable_actor@example.com',
			]
		);

		wp_set_current_user( $actor );
		SimpleLogger()->info( 'Event the editor is not allowed to read.' );

		$this->login_as( 'editor' );

		// Deny the editor every logger, standing in for any logger whose own
		// capability they do not hold.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_false', 20 );

		$response = $this->dispatch_request( 'GET', '/simple-history/v1/events', [ 'per_page' => 50 ] );
		$events   = (array) $response->get_data();

		remove_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_false', 20 );

		$serialised = wp_json_encode( $events );

		$this->assertStringNotContainsString(
			'unreadable_actor@example.com',
			$serialised,
			'The initiator of an unreadable event must not be reachable.'
		);
		$this->assertStringNotContainsString(
			'unreadable_event_actor',
			$serialised,
			'Nor their login.'
		);
	}

	/**
	 * The filter must still be able to override the new default, since that is
	 * its documented purpose.
	 */
	public function test_user_can_clear_log_filter_still_overrides_default() {
		$this->login_as( 'administrator' );

		add_filter( 'simple_history/user_can_clear_log', '__return_false' );
		$this->assertFalse( Helpers::user_can_clear_log(), 'Filter must be able to lock clearing down.' );
		remove_filter( 'simple_history/user_can_clear_log', '__return_false' );

		$this->assertTrue( Helpers::user_can_clear_log() );
	}
}
