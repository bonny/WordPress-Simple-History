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
	 * Gating the user card alone achieves nothing: the events response carries
	 * the initiator's login and email too, the event list prints the email
	 * beside every row, and the card falls back to it when the card omits it.
	 * Both had to be gated together.
	 */
	public function test_events_initiator_data_hides_pii_from_users_without_list_users() {
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

		$this->assertNotEmpty( $events, 'Editor should still be able to read events.' );

		foreach ( $events as $event ) {
			$this->assertNull( $event['initiator_data']['user_login'] ?? null, 'Initiator login must not leak.' );
			$this->assertNull( $event['initiator_data']['user_email'] ?? null, 'Initiator email must not leak.' );
		}
	}

	/**
	 * Administrators hold list_users and must keep seeing the initiator, so the
	 * gate scopes the data rather than removing the feature.
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
