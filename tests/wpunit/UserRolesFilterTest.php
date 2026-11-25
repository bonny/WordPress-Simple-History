<?php

use Simple_History\Existing_Data_Importer;
use Simple_History\Log_Query;

/**
 * Tests for the role filter: the user_roles and exclude_user_roles
 * arguments in Log_Query and the REST API, and the _user_role context
 * they filter on.
 */
class UserRolesFilterTest extends \Codeception\TestCase\WPTestCase {
	private $events_endpoint = '/simple-history/v1/events';

	/** @var int */
	private $admin_id;

	/** @var int */
	private $editor_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id  = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_set_current_user( $this->admin_id );
		\SimpleLogger()->info( 'Role test: by admin' );

		wp_set_current_user( $this->editor_id );
		\SimpleLogger()->info( 'Role test: by editor' );

		// No user: an anonymous visitor, cron and the like.
		wp_set_current_user( 0 );
		\SimpleLogger()->info( 'Role test: by nobody' );

		wp_set_current_user( $this->admin_id );
	}

	public function tearDown(): void {
		remove_role( 'VIP_Member' );
		remove_role( 'vip, member' );
		remove_role( 'zz_first_registered' );
		remove_role( 'aa_second_registered' );
		remove_role( 'VIP Member' );

		parent::tearDown();
	}

	/**
	 * Messages of the "Role test:" events a query returns, sorted.
	 *
	 * @param array $args Log_Query arguments.
	 * @return array<string>
	 */
	private function get_test_messages( $args ) {
		$results = ( new Log_Query() )->query(
			array_merge(
				[
					'posts_per_page' => 100,
					'ungrouped'      => true,
					'search'         => 'Role test',
				],
				$args
			)
		);

		$messages = wp_list_pluck( $results['log_rows'], 'message' );
		sort( $messages );

		return $messages;
	}

	public function test_user_roles_returns_only_events_by_that_role() {
		$this->assertSame(
			[ 'Role test: by editor' ],
			$this->get_test_messages( [ 'user_roles' => [ 'editor' ] ] )
		);
	}

	public function test_user_roles_with_several_roles_leaves_out_events_without_a_user() {
		$this->assertSame(
			[ 'Role test: by admin', 'Role test: by editor' ],
			$this->get_test_messages( [ 'user_roles' => [ 'administrator', 'editor' ] ] )
		);
	}

	public function test_user_roles_accepts_a_comma_separated_string() {
		$this->assertSame(
			[ 'Role test: by admin', 'Role test: by editor' ],
			$this->get_test_messages( [ 'user_roles' => 'administrator, editor' ] )
		);
	}

	/**
	 * A filter with nothing usable in it matches nothing, rather than
	 * quietly turning into no filter and listing every event.
	 */
	public function test_user_roles_with_only_empty_values_matches_nothing() {
		$this->assertSame( [], $this->get_test_messages( [ 'user_roles' => [ '', ' ' ] ] ) );
	}

	public function test_exclude_user_roles_with_only_empty_values_excludes_nothing() {
		$this->assertCount( 3, $this->get_test_messages( [ 'exclude_user_roles' => ',' ] ) );
	}

	/**
	 * WordPress stores role slugs as the plugin gave them, capitals and all.
	 */
	public function test_user_roles_matches_a_slug_with_capitals() {
		add_role( 'VIP_Member', 'VIP Member' );
		$vip_id = $this->factory->user->create( [ 'role' => 'VIP_Member' ] );

		wp_set_current_user( $vip_id );
		\SimpleLogger()->info( 'Role test: by vip' );
		wp_set_current_user( $this->admin_id );

		$this->assertSame(
			[ 'Role test: by vip' ],
			$this->get_test_messages( [ 'user_roles' => [ 'VIP_Member' ] ] )
		);
	}

	/**
	 * WordPress keeps "Editor" and "editor" apart, so the filter must too,
	 * also on MySQL where the contexts table compares case-insensitively and
	 * ignores trailing spaces.
	 */
	public function test_user_roles_compares_case_and_spaces_exactly() {
		$this->assertSame( [], $this->get_test_messages( [ 'user_roles' => [ 'Editor' ] ] ) );
		$this->assertSame( [], $this->get_test_messages( [ 'user_roles' => [ 'editor ' ] ] ) );

		$this->assertCount( 3, $this->get_test_messages( [ 'exclude_user_roles' => [ 'EDITOR' ] ] ) );
	}

	/**
	 * Slugs may contain spaces and commas. Given as an array they are used
	 * as they are; only a comma separated string is split.
	 */
	public function test_user_roles_array_items_are_not_split_or_trimmed() {
		add_role( 'vip, member', 'VIP, member' );
		$odd_id = $this->factory->user->create( [ 'role' => 'vip, member' ] );

		wp_set_current_user( $odd_id );
		\SimpleLogger()->info( 'Role test: by odd slug' );
		wp_set_current_user( $this->admin_id );

		$this->assertSame(
			[ 'Role test: by odd slug' ],
			$this->get_test_messages( [ 'user_roles' => [ 'vip, member' ] ] )
		);
	}

	public function test_user_roles_of_a_role_nobody_has_returns_nothing() {
		$this->assertSame( [], $this->get_test_messages( [ 'user_roles' => [ 'author' ] ] ) );
	}

	public function test_exclude_user_roles_keeps_events_without_a_user() {
		$this->assertSame(
			[ 'Role test: by admin', 'Role test: by nobody' ],
			$this->get_test_messages( [ 'exclude_user_roles' => [ 'editor' ] ] )
		);
	}

	public function test_invalid_user_roles_type_throws() {
		$this->expectException( \InvalidArgumentException::class );

		( new Log_Query() )->query( [ 'user_roles' => 42 ] );
	}

	/**
	 * The filter matches the role at the time of the event, not today's.
	 */
	public function test_user_roles_matches_the_role_the_user_had_at_the_time() {
		( new WP_User( $this->editor_id ) )->set_role( 'author' );

		$this->assertSame(
			[ 'Role test: by editor' ],
			$this->get_test_messages( [ 'user_roles' => [ 'editor' ] ] )
		);

		$this->assertSame( [], $this->get_test_messages( [ 'user_roles' => [ 'author' ] ] ) );
	}

	/**
	 * Login, logout and password reset are logged with no current user, and
	 * the user logger sets the user itself. The role must still be stored,
	 * or those events drop out of a role filter.
	 */
	public function test_role_is_stored_when_the_logger_sets_the_user_itself() {
		wp_set_current_user( 0 );

		\SimpleLogger()->info(
			'Role test: user set by logger',
			[
				'_initiator' => 'wp_user',
				'_user_id'   => $this->editor_id,
			]
		);

		// Back to a user who may read the log.
		wp_set_current_user( $this->admin_id );

		$this->assertContains(
			'Role test: user set by logger',
			$this->get_test_messages( [ 'user_roles' => [ 'editor' ] ] )
		);
	}

	public function test_role_set_by_the_logger_is_kept() {
		\SimpleLogger()->info(
			'Role test: role set by logger',
			[
				'_initiator' => 'wp_user',
				'_user_id'   => $this->editor_id,
				'_user_role' => 'author',
			]
		);

		$this->assertContains(
			'Role test: role set by logger',
			$this->get_test_messages( [ 'user_roles' => [ 'author' ] ] )
		);
	}

	/**
	 * Backfilled events are rebuilt from old posts. The author's role today
	 * says nothing about their role back then, so none is stored.
	 */
	public function test_backfilled_events_get_no_role() {
		\SimpleLogger()->info(
			'Role test: backfilled',
			[
				'_initiator'                                 => 'wp_user',
				'_user_id'                                   => $this->editor_id,
				Existing_Data_Importer::BACKFILLED_CONTEXT_KEY => '1',
			]
		);

		$this->assertNotContains(
			'Role test: backfilled',
			$this->get_test_messages( [ 'user_roles' => [ 'editor' ] ] )
		);
	}

	/**
	 * A user with two roles, like a donor who is subscriber and
	 * give_subscriber, matches a filter on either role.
	 */
	public function test_user_with_several_roles_matches_each_role() {
		$multi_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $multi_id ) )->add_role( 'contributor' );

		wp_set_current_user( $multi_id );
		\SimpleLogger()->info( 'Role test: by subscriber and contributor' );
		wp_set_current_user( $this->admin_id );

		$this->assertSame(
			[ 'Role test: by subscriber and contributor' ],
			$this->get_test_messages( [ 'user_roles' => [ 'subscriber' ] ] )
		);

		$this->assertSame(
			[ 'Role test: by subscriber and contributor' ],
			$this->get_test_messages( [ 'user_roles' => [ 'contributor' ] ] )
		);

		// Excluding either role leaves the event out.
		$this->assertNotContains(
			'Role test: by subscriber and contributor',
			$this->get_test_messages( [ 'exclude_user_roles' => [ 'contributor' ] ] )
		);
	}

	public function test_several_roles_are_stored_one_key_each() {
		$multi_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $multi_id ) )->add_role( 'contributor' );

		wp_set_current_user( $multi_id );
		$event_id = \SimpleLogger()->info( 'Role test: stored roles' )->last_insert_id;
		wp_set_current_user( $this->admin_id );

		$context = ( new \Simple_History\Event( $event_id ) )->get_context();

		$this->assertSame( 'subscriber', $context['_user_role'] );
		$this->assertSame( 'contributor', $context['_user_role_2'] );
		$this->assertArrayNotHasKey( '_user_role_3', $context );
	}

	/**
	 * Login and logout are logged with no current user; the extra roles of
	 * the user the logger names are stored too.
	 */
	public function test_several_roles_are_stored_when_the_logger_sets_the_user() {
		$multi_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $multi_id ) )->add_role( 'contributor' );

		wp_set_current_user( 0 );
		\SimpleLogger()->info(
			'Role test: logger set multi-role user',
			[
				'_initiator' => 'wp_user',
				'_user_id'   => $multi_id,
			]
		);
		wp_set_current_user( $this->admin_id );

		$this->assertContains(
			'Role test: logger set multi-role user',
			$this->get_test_messages( [ 'user_roles' => [ 'contributor' ] ] )
		);
	}

	/**
	 * A site that removes `_user_role` in log_insert_context to keep roles
	 * out of the log must not get the extra roles stored instead.
	 */
	public function test_extra_roles_are_dropped_when_a_filter_removes_the_role() {
		$multi_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		( new WP_User( $multi_id ) )->add_role( 'contributor' );

		$strip_role = static function ( $context ) {
			unset( $context['_user_role'] );

			return $context;
		};

		add_filter( 'simple_history/log_insert_context', $strip_role );

		wp_set_current_user( $multi_id );
		$event_id = \SimpleLogger()->info( 'Role test: role stripped' )->last_insert_id;
		wp_set_current_user( $this->admin_id );

		remove_filter( 'simple_history/log_insert_context', $strip_role );

		$context = ( new \Simple_History\Event( $event_id ) )->get_context();

		$this->assertArrayNotHasKey( '_user_role', $context );
		$this->assertArrayNotHasKey( '_user_role_2', $context );
	}

	public function test_single_role_user_gets_no_extra_role_keys() {
		$event_id = \SimpleLogger()->info( 'Role test: single role' )->last_insert_id;
		$context  = ( new \Simple_History\Event( $event_id ) )->get_context();

		$this->assertSame( 'administrator', $context['_user_role'] );
		$this->assertArrayNotHasKey( '_user_role_2', $context );
	}

	public function test_role_context_keys_follow_the_cap() {
		$keys = \Simple_History\Helpers::get_user_role_context_keys();

		$this->assertCount( \Simple_History\Helpers::MAX_STORED_USER_ROLES, $keys );
		$this->assertSame( '_user_role', $keys[0] );
		$this->assertSame( '_user_role_2', $keys[1] );
	}

	public function test_rest_api_user_roles_param() {
		$request = new WP_REST_Request( 'GET', $this->events_endpoint );
		$request->set_param( 'user_roles', [ 'editor' ] );
		$request->set_param( 'search', 'Role test' );
		$request->set_param( 'ungrouped', true );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[ 'Role test: by editor' ],
			wp_list_pluck( $response->get_data(), 'message' )
		);
	}

	/**
	 * A plain string is split on commas only, so a slug with a space stays
	 * one role. WordPress's default array handling also splits on spaces.
	 */
	public function test_rest_api_user_roles_string_keeps_spaces() {
		add_role( 'VIP Member', 'VIP Member' );
		$vip_id = $this->factory->user->create( [ 'role' => 'VIP Member' ] );

		wp_set_current_user( $vip_id );
		\SimpleLogger()->info( 'Role test: by vip member' );
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'GET', $this->events_endpoint );
		$request->set_query_params(
			[
				'user_roles' => 'VIP Member,editor',
				'search'     => 'Role test',
				'ungrouped'  => true,
			]
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEqualsCanonicalizing(
			[ 'Role test: by vip member', 'Role test: by editor' ],
			wp_list_pluck( $response->get_data(), 'message' )
		);
	}

	public function test_rest_api_exclude_user_roles_param() {
		$request = new WP_REST_Request( 'GET', $this->events_endpoint );
		$request->set_param( 'exclude_user_roles', [ 'administrator', 'editor' ] );
		$request->set_param( 'search', 'Role test' );
		$request->set_param( 'ungrouped', true );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[ 'Role test: by nobody' ],
			wp_list_pluck( $response->get_data(), 'message' )
		);
	}

	public function test_search_options_list_the_sites_roles() {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/simple-history/v1/search-options' ) );

		$this->assertSame( 200, $response->get_status() );

		$roles = wp_list_pluck( $response->get_data()['user_roles'], 'label', 'value' );

		$this->assertSame( 'Administrator', $roles['administrator'] ?? null );
		$this->assertSame( 'Editor', $roles['editor'] ?? null );
	}

	/**
	 * WordPress's own roles first, most capable first, then the rest by name.
	 */
	public function test_search_options_list_core_roles_first_then_by_name() {
		add_role( 'zz_first_registered', 'Aardvark keeper' );
		add_role( 'aa_second_registered', 'Zebra keeper' );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/simple-history/v1/search-options' ) );
		$slugs    = wp_list_pluck( $response->get_data()['user_roles'], 'value' );

		$this->assertSame(
			[ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ],
			array_slice( $slugs, 0, 5 )
		);

		$this->assertLessThan(
			array_search( 'aa_second_registered', $slugs, true ),
			array_search( 'zz_first_registered', $slugs, true )
		);
	}
}
